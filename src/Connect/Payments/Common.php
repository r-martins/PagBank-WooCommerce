<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use RM_PagBank\Helpers\TaxId;
use RM_PagBank\Object\Address;
use RM_PagBank\Object\Customer;
use RM_PagBank\Object\Item;
use RM_PagBank\Object\Phone;
use WC_Customer;
use WC_Order;
use WC_Order_Item_Product;
use RM_PagBank\Helpers\OrderMeta;

/**
 * Common methods shared between payment methods
 *
 * @author    Ricardo Martins
 * @package   RM_PagBank\Connect
 */
class Common
{
    /**
     * @var WC_Order $order
     */
    protected WC_Order $order;

    /**
     * @param WC_Order $order
     */
    public function __construct(WC_Order $order)
    {
        $this->order = $order;
    }

    /**
     * Returns an array with reference_id, customer, items, notification_urls and shipping
     * to be used in the /orders request
     * @return array
     */
    public function getDefaultParameters(): array
    {
        $return = [
            'reference_id' => $this->order->get_id(),
            'customer' => $this->getCustomerData(),
            'items' => $this->getItemsData(),
            'enable_proxy' => $this->getEnableProxy(),
        ];
        
        if (empty($return['items'])){
            unset($return['items']);
        }

        if ($this->order->has_shipping_address() && Params::getConfig('shipping_param') !== 'never' && $this->order->get_shipping_method()){
            $address = $this->getShippingAddress();
            $return['shipping']['address'] = $address;
            $helper = new Params();
            if (Params::getConfig('shipping_param') === 'validate' && ! $helper->isAddressValid($address)){
                unset($return['shipping']);
            }
        }
        $return['notification_urls'] = $this->getNotificationUrls();

        return $return;
    }

    /**
     * hash email is active
     * @return bool
     */
    protected function isHashEmail()
    {
        // Static method of Params class design
        return Params::getConfig('hash_email_active') === 'yes';
    }

    /**
     * True when this order will charge a Vindi payment_profile (saved card or 3DS profile).
     */
    protected function orderUsesVindiPaymentProfile(): bool
    {
        if ((int) OrderMeta::get($this->order, '_payment_profile_id') > 0) {
            return true;
        }
        $tokenId = OrderMeta::get($this->order, '_card_token_id');
        if (!$tokenId || $tokenId === 'new') {
            return false;
        }
        try {
            $api = new Api();
            if (!$api->partner()->isVindi()) {
                return false;
            }
            $token = \RM_PagBank\Connect\Payments\CreditCard::getCcToken($tokenId);
            if (!$token) {
                return false;
            }
            return (int) ($token->get_meta('vindi_payment_profile_id') ?: $token->get_token()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function getHashEmail()
    {
        $email = strtolower($this->order->get_billing_email());
        $hash = hash('md5', $email);
        return "{$hash}@pbintegracoes.cc";
    }
	/**
	 * Populates the customer object with data from the order
	 * @return Customer
	 */
	public function getCustomerData(): Customer
	{
        $customer = new Customer();
        //truncate
        $firstName = substr($this->order->get_billing_first_name(), 0, 60);
        $lastName = substr($this->order->get_billing_last_name(), 0, 59);
        $customer->setName($firstName . ' ' . $lastName);
        // Vindi payment_profile.id is bound to the customer email used at profile create.
        // Hashing (or mismatch) makes billing reject the profile as "identificador não encontrado".
        $hashEmail = $this->isHashEmail() && !$this->orderUsesVindiPaymentProfile();
        $customer->setEmail($hashEmail ? $this->getHashEmail() : $this->order->get_billing_email());
        
        $taxId = null;

        // Blocks and classic may both store document on this meta — prefer it whenever present.
        $taxId = $this->order->get_meta('_rm_pagbank_customer_document');

        if(empty($taxId)){
            //cpf or cnpj
            $taxId = Functions::getParamFromOrderMetaOrPost($this->order, '_billing_cpf', 'billing_cpf');
        }
        
        if (empty($taxId)) {
            $taxId = Functions::getParamFromOrderMetaOrPost($this->order, '_billing_cnpj', 'billing_cnpj');
        }
        
        if (empty($taxId)) {
            //probably is coming from the /order-pay page
            $wcCustomer = new WC_Customer($this->order->get_customer_id());
            $taxId = $wcCustomer->get_meta('billing_cpf');
            if (empty($taxId)) {
                $taxId = $wcCustomer->get_meta('billing_cnpj');
            }
        }


        $taxId = TaxId::sanitizeForApi($taxId);
        
        if (!empty($taxId)) {
            $customer->setTaxId($taxId);
            $this->order->add_meta_data('_rm_pagbank_customer_document', $taxId, true);
        }
        $phone = new Phone();
        $number = Params::extractPhone($this->order);
        if (!empty($number['area']) && !empty($number['number'])) {
            $phone->setCountry($number['country']);
            $phone->setArea((int)$number['area']);
            $phone->setNumber((int)$number['number']);
            $customer->setPhone([
                $phone,
            ]);
        }
        // Vindi /v1/billing requires customer.address. Virtual/subscription renewals often
        // omit shipping (no shipping method), so attach billing address on the customer.
        $api = new Api();
        if ($api->partner()->isVindi()) {
            $customer->setAddress($this->getBillingAddress());
        }
        return $customer;
    }

    /**
     * hide items is active
     * @return bool
     */
    protected function isHideItems()
    {
        // Static method of Params class design
        return Params::getConfig('hide_items') === 'yes';
    }
	/**
	 * Populates the items array with data from the order
	 * @return array
	 */
	public function getItemsData(): array
	{
        return apply_filters('pagbank_connect_items_data',
            $this->isHideItems() ? $this->getItemDefault(
                $this->order->get_total() - ($this->order->get_shipping_total() ?? 0)
            ) : $this->getItems(
                $this->order->get_items()
            )
        );
    }

    /**
     * 
     * @param mixed $amount
     * @return Item[]
     */
    protected function getItemDefault($amount)
    {
        $items = [];
        $itemObj = new Item();
        $itemObj->setReferenceId(1);
        $itemObj->setName(Functions::sanitizeProductName('Compra em ' . get_bloginfo('name') ?? 'PagBank'));
        $itemObj->setQuantity(1);
        $unitAmount = number_format($amount, 2, '', '');
        $itemObj->setUnitAmount($unitAmount);
        $items[] = $itemObj;
        return $items;
    }

    /**
     * Formats order items into Item objects with ID, name, quantity, and unit amount.
     *
     * Skips items with zero subtotal and handles recurring product pricing.
     * PagBank requires integer quantity between 1 and 99999. When a plugin allows
     * decimal qty < 1 (e.g. 0.854), int cast would become 0 and fail with 40002 —
     * in that case we send quantity 1 and append the original qty to the item name.
     *
     * @param array<WC_Order_Item_Product> $get_items
     * @return Item[]
     */
    protected function getItems($get_items)
    {
        $items = [];
         foreach ($get_items as $item) {
            $product = $item->get_product();
            $itemObj = new Item();
            $itemObj->setReferenceId($item['product_id']);

            $quantity = (float) $item['quantity'];
            $name = $item['name'];
            $subtotal = (float) $item->get_subtotal('edit');

            // Decimal qty < 1 cannot be sent as PagBank quantity (min 1, integer only).
            // Normalize to 1 and keep the original qty in the name; unit_amount then uses line subtotal.
            if ($quantity > 0 && $quantity < 1) {
                $qtyLabel = rtrim(rtrim(sprintf('%.4f', $quantity), '0'), '.');
                $name = sprintf('%s (%s)', $name, $qtyLabel);
                $quantity = 1;
            }

            $itemObj->setName(Functions::sanitizeProductName($name));
            $itemObj->setQuantity((int) $quantity);
            $amount = $quantity > 0 ? $subtotal / $quantity : $subtotal;

            $recurringHelper = new RecurringHelper();
            if (
                $recurringHelper->isProductRecurring($product)
                && (int) $recurringHelper->getRecurringMeta($product, '_recurring_trial_length') > 0
            ) {
                $amount = $product->get_price();
            }

            $unitAmount = number_format($amount, 2, '', '');
            $itemObj->setUnitAmount($unitAmount);
            
            if ($item['line_subtotal'] == 0 && $amount == 0) {
                continue;
            }
            $items[] = $itemObj;
        }

        return $items;
    }

	/**
	 * Populates the address object with data from the order
	 * Compatible with HPOS and legacy storage
	 * @return Address
	 */
	public function getShippingAddress(): Address
	{
        $address = new Address();
        
        // Street - using WC_Order method (HPOS/Legacy compatible)
        $shippingStreet = $this->order->get_shipping_address_1('edit');
        $address->setStreet(substr($shippingStreet, 0, 120));
        
        // Usually virtual orders don't have shipping address' attributes replicated. So we use billing address instead.
        // Number - prefer meta fields, fallback to address_2 (HPOS/Legacy compatible)
        $shippingNumber = $this->order->get_meta('_shipping_number');
        $billingNumber = $this->order->get_meta('_billing_number');
        $shippingAddress2 = $this->order->get_shipping_address_2('edit');
        $billingAddress2 = $this->order->get_billing_address_2('edit');
        
        // Complement - using get_meta() (HPOS/Legacy compatible)
        $shippingComplement = $this->order->get_meta('_shipping_complement');
        $billingComplement = $this->order->get_meta('_billing_complement');
        
        // Neighborhood - using get_meta() (HPOS/Legacy compatible)
        $shippingNeighborhood = $this->order->get_meta('_shipping_neighborhood');
        $billingNeighborhood = $this->order->get_meta('_billing_neighborhood');
        
        // Apply sanitization and length limits
        $shippingComplement = substr($shippingComplement, 0, 40);
        $billingComplement = substr($billingComplement, 0, 40);
        $shippingAddress2 = substr($shippingAddress2, 0, 40);
        $billingAddress2 = substr($billingAddress2, 0, 40);
        $billingNeighborhood = substr($billingNeighborhood, 0, 60);
        $shippingNeighborhood = substr($shippingNeighborhood, 0, 60);
        
        // Number: prefer shipping meta, then billing meta, then shipping address_2, then billing address_2
        $hasNumberMeta = !empty($shippingNumber) || !empty($billingNumber);
        $number = !empty($shippingNumber) 
            ? $shippingNumber 
            : (!empty($billingNumber) 
                ? $billingNumber 
                : (!empty($shippingAddress2) 
                    ? $shippingAddress2 
                    : (!empty($billingAddress2) 
                        ? $billingAddress2 
                        : '...')));
        $address->setNumber($number);

        // Complement: if number came from meta, use address_2 as complement fallback
        // Otherwise, use meta complement fields only
        // Only set complement if there's a valid value
        if ($hasNumberMeta) {
            // Number came from meta, so address_2 can be used as complement
            $complement = !empty($shippingComplement) 
                ? $shippingComplement 
                : (!empty($billingComplement) 
                    ? $billingComplement 
                    : (!empty($shippingAddress2) 
                        ? $shippingAddress2 
                        : (!empty($billingAddress2) ? $billingAddress2 : '')));
        } else {
            // Number came from address_2, so only use meta complement fields
            $complement = !empty($shippingComplement) ? $shippingComplement : (!empty($billingComplement) ? $billingComplement : '');
        }
        
        if (!empty($complement)) {
            $address->setComplement($complement);
        }

        // Neighborhood: prefer shipping, fallback to billing
        $neighborhood = !empty($shippingNeighborhood) ? $shippingNeighborhood : (!empty($billingNeighborhood) ? $billingNeighborhood : '...');
        $address->setLocality($neighborhood);
        
        // City, State, Postal Code - using WC_Order methods (HPOS/Legacy compatible)
        $address->setCity(substr($this->order->get_shipping_city('edit'), 0, 60));
        $address->setRegionCode($this->order->get_shipping_state('edit'));
        $address->setPostalCode(Params::removeNonNumeric($this->order->get_shipping_postcode('edit')));

        return apply_filters('pagbank_connect_shipping_address', $address, $this->order);
    }

    /**
     * Populates the address object with data from the order
     * Compatible with HPOS and legacy storage
     * @return Address
     */
    public function getBillingAddress(): Address
    {
        $address = new Address();
        
        // Street - using WC_Order method (HPOS/Legacy compatible)
        $address->setStreet($this->order->get_billing_address_1('edit'));
        
        // Number - prefer meta field, fallback to address_2 (HPOS/Legacy compatible)
        $billingNumber = $this->order->get_meta('_billing_number');
        $billingAddress2 = $this->order->get_billing_address_2('edit');
        
        // Complement - using get_meta() (HPOS/Legacy compatible)
        $billingComplement = $this->order->get_meta('_billing_complement');
        
        // Neighborhood - using get_meta() (HPOS/Legacy compatible)
        $billingNeighborhood = $this->order->get_meta('_billing_neighborhood');
        
        // Apply sanitization and length limits
        $billingComplement = substr($billingComplement, 0, 40);
        $billingAddress2 = substr($billingAddress2, 0, 40);
        $billingNeighborhood = substr($billingNeighborhood, 0, 60);

        // Set address fields
        // Number: prefer meta field, fallback to address_2
        $hasNumberMeta = !empty($billingNumber);
        $number = !empty($billingNumber) ? $billingNumber : (!empty($billingAddress2) ? $billingAddress2 : '...');
        $address->setNumber($number);
        
        // Complement: if number came from meta, use address_2 as complement fallback
        // Only set complement if there's a valid value
        if ($hasNumberMeta) {
            $complement = !empty($billingComplement) ? $billingComplement : (!empty($billingAddress2) ? $billingAddress2 : '');
        } else {
            // Number came from address_2, so only use meta complement field
            $complement = !empty($billingComplement) ? $billingComplement : '';
        }
        
        if (!empty($complement)) {
            $address->setComplement($complement);
        }
        $address->setLocality(!empty($billingNeighborhood) ? $billingNeighborhood : '...');

        // City, State, Postal Code - using WC_Order methods (HPOS/Legacy compatible)
        $address->setCity($this->order->get_billing_city('edit'));
        $address->setRegionCode($this->order->get_billing_state('edit'));
        $address->setPostalCode(Params::removeNonNumeric($this->order->get_billing_postcode('edit')));
        
        return apply_filters('pagbank_connect_billing_address', $address, $this->order);
    }

	/**
	 * Returns an array with the notification urls with the hash validation parameter
	 * @return string[]
	 */
	public function getNotificationUrls(): array
    {
        $hash = Api::getOrderHash($this->order);
		//Note that PagBank API currently supports only one URL
        return [
            get_site_url() . '/?wc-api=rm_ps_notif&hash=' . $hash
        ];
    }

    public function getEnableProxy(): bool
    {
        $caps = new \RM_PagBank\Partner\Capabilities();
        if ($caps->forceNotificationProxy()) {
            return true;
        }
        return Params::getConfig('enable_proxy', false);
    }


    /**
	 * Process response from the API and add the metadata to the order
     * @param WC_Order $order
     * @param array    $response
     *
     * @return void
     */
    public function process_response(WC_Order $order, array $response) {

        $api = new Api();
        OrderMeta::set($order, 'partner', $api->partner()->getPartner(), true);
        OrderMeta::set($order, 'connect_key_fp', $api->partner()->fingerprint(), true);

        switch (OrderMeta::get($order, 'payment_method')){
            case 'pix':
                OrderMeta::set($order, 'pix_qrcode', $response['qr_codes'][0]['links'][0]['href'] ?? null, true);
                OrderMeta::set($order, 'pix_qrcode_text', $response['qr_codes'][0]['text'] ?? null, true);
                OrderMeta::set($order, 'pix_qrcode_expiration', $response['qr_codes'][0]['expiration_date'] ?? null, true);
                $order->set_props(['payment_method' => 'rm-pagbank-pix']);
                break;
            case 'boleto':
                $boletoPdf = $response['charges'][0]['links'][0]['href'] ?? ($response['links'][0]['href'] ?? null);
                $boletoPng = $response['charges'][0]['links'][1]['href'] ?? null;
                // Vindi/Yapay: print_url is a single download page — no separate PNG; avoid duplicate "Imprimir".
                if ($api->partner()->isVindi()) {
                    $boletoPng = null;
                }
                OrderMeta::set($order, 'boleto_png', $boletoPng, true);
                OrderMeta::set($order, 'boleto_pdf', $boletoPdf, true);
                OrderMeta::set($order, 'boleto_due_date', $response['charges'][0]['payment_method']['boleto']['due_date'] ?? null, true);
                OrderMeta::set($order, 'boleto_barcode_formatted', $response['charges'][0]['payment_method']['boleto']['formatted_barcode']
                        ?? ($response['boleto']['formatted_barcode'] ?? null), true);
                OrderMeta::set($order, 'boleto_barcode', $response['charges'][0]['payment_method']['boleto']['barcode']
                        ?? ($response['boleto']['barcode'] ?? null), true);
                $order->set_props(['payment_method' => 'rm-pagbank-boleto']);
                break;
			case 'credit_card':
				OrderMeta::set($order, '_card_installments', $response['charges'][0]['payment_method']['installments'] ?? null);
				$order->add_meta_data('Parcelas', $response['charges'][0]['payment_method']['installments'] ?? null);
				CreditCard::applyChargeInstallmentMeta($order, $response['charges'][0] ?? []);
				$card = is_array($response['charges'][0]['payment_method']['card'] ?? null)
					? $response['charges'][0]['payment_method']['card']
					: [];
				$brand = (string) ($card['brand'] ?? OrderMeta::get($order, '_payment_company_code') ?: '');
				$first = (string) ($card['first_digits'] ?? OrderMeta::get($order, '_card_first_digits') ?: '');
				$last = (string) ($card['last_digits'] ?? OrderMeta::get($order, 'card_last4') ?: '');
				$holder = (string) ($card['holder']['name'] ?? OrderMeta::get($order, '_card_holder_name') ?: '');
				OrderMeta::set($order, '_card_brand', $brand !== '' ? $brand : null, false);
				OrderMeta::set($order, '_card_first_digits', $first !== '' ? $first : null, false);
				OrderMeta::set($order, '_card_last_digits', $last !== '' ? $last : null, false);
				if ($last !== '') {
					OrderMeta::set($order, 'card_last4', substr($last, -4, false), true);
				}
				OrderMeta::set($order, '_card_holder', $holder !== '' ? $holder : null);
				OrderMeta::set($order, '_card_exp_month', $card['exp_month'] ?? null, false);
				OrderMeta::set($order, '_card_exp_year', $card['exp_year'] ?? null, false);
				OrderMeta::set($order, '_card_response_reference', $response['charges'][0]['payment_response']['reference'] ?? null, false);
				$threeDsMeta = $this->resolveCard3dsMeta($order, $response['charges'][0] ?? []);
				OrderMeta::set($order, '_card_3ds_status', $threeDsMeta['status'], false);
				if ($threeDsMeta['id'] !== null) {
					OrderMeta::set($order, '_card_3ds_id', $threeDsMeta['id'], false);
				}
                $order->set_props(['payment_method' => 'rm-pagbank-cc']);
				break;
            case 'redirect':
                OrderMeta::set($order, 'redirect_url', $response['links'][1]['href'] ?? null, true);
                OrderMeta::set($order, 'redirect_expiration', $response['expiration_date'] ?? null, false);
                OrderMeta::set($order, 'checkout_id', $response['id'] ?? null, true);
                $order->set_props(['payment_method' => 'rm-pagbank-redirect']);
                break;
                
        }
        if (isset($response['id']) && substr($response['id'], 0, 4) != 'CHEC'){ //if not a pagbank checkout code
            OrderMeta::set($order, 'order_id', $response['id'], true);
        }
        $chargeId = $response['charges'][0]['id']
            ?? ($response['partner_charge_id'] ?? null);
        if ($chargeId !== null && $chargeId !== '') {
            OrderMeta::set($order, 'charge_id', (string) $chargeId, true);
        }
        $billId = $response['partner_bill_id'] ?? null;
        if ($billId !== null && $billId !== '') {
            OrderMeta::set($order, 'partner_bill_id', (string) $billId, true);
        }
		OrderMeta::set($order, 'order_charges', $response['charges'] ?? null, true);
		OrderMeta::set($order, 'is_sandbox', Params::getConfig('is_sandbox', false) ? 1 : 0);
		
		// Save split data if present (for Pix, split data comes directly in response)
		if (!empty($response['qr_codes'][0]['splits'])) {
		    OrderMeta::set($order, '_split_data', $response['qr_codes'][0]['splits'], true);
		    OrderMeta::set($order, '_split_applied', true, true);
		} elseif (!empty($response['charges'][0]['payment_method']['splits'])) {
		    OrderMeta::set($order, '_split_data', $response['charges'][0]['payment_method']['splits'], true);
		    OrderMeta::set($order, '_split_applied', true, true);
		}
		
		// For credit card payments, split details come via a link (SPLIT)
		// We need to fetch the split details from the API
		if (!empty($response['charges'][0]['links'])) {
		    foreach ($response['charges'][0]['links'] as $link) {
		        if (isset($link['rel']) && $link['rel'] === 'SPLIT' && !empty($link['href'])) {
		            // Extract split_id from href (last part of URL)
		            $split_id = basename(parse_url($link['href'], PHP_URL_PATH));
		            OrderMeta::set($order, '_split_id', $split_id, true);
		            Functions::log('Split ID encontrado no pedido ' . $order->get_id() . ': ' . $split_id, 'info');
		            
		            // Fetch split details from the API
		            try {
		                $split_details = self::fetchSplitDetails($link['href']);
		                if (!empty($split_details)) {
		                    // Save split data in the same format as Pix
		                    OrderMeta::set($order, '_split_data', $split_details, true);
		                    OrderMeta::set($order, '_split_applied', true, true);
		                    Functions::log('Detalhes do split obtidos e salvos para o pedido ' . $order->get_id(), 'info');
		                }
		            } catch (\Exception $e) {
		                Functions::log('Erro ao buscar detalhes do split para o pedido ' . $order->get_id() . ': ' . $e->getMessage(), 'error');
		            }
		            break;
		        }
		    }
		}

		$order->update_status('pending', \RM_PagBank\Partner\Branding::notePrefix($order) . ': Pagamento Pendente');

        do_action('pagbank_connect_after_proccess_response', $order, $response);
	}

    /**
     * Fetch split details from PagBank API
     * Static method that can be called from anywhere
     * 
     * In production: Uses authenticated API endpoint
     * In sandbox: Uses direct URL (no authentication required)
     *
     * @param string $split_url Full URL to the split endpoint
     * @return array|null Split details or null on error
     */
    public static function fetchSplitDetails(string $split_url): ?array
    {
        $is_sandbox = Params::getConfig('is_sandbox', false);
        
        // In production, use authenticated API endpoint
        if (!$is_sandbox) {
            // Extract split_id from URL (e.g., https://api.pagseguro.com/splits/SPLI_xxx)
            $split_id = basename(parse_url($split_url, PHP_URL_PATH));
            
            if (empty($split_id)) {
                throw new \Exception('Não foi possível extrair o Split ID da URL: ' . $split_url);
            }
            
            try {
                // Use authenticated API endpoint
                $api = new Api();
                $split_data = $api->get('ws/splits/' . $split_id, [], 5);
                
                if (empty($split_data)) {
                    throw new \Exception('Resposta vazia ao buscar detalhes do split');
                }
                
                return $split_data;
            } catch (\Exception $e) {
                throw new \Exception('Erro ao buscar detalhes do split via API autenticada: ' . $e->getMessage());
            }
        }
        
        // In sandbox, use direct URL (replace with internal domain)
        if (strpos($split_url, 'https://sandbox.api.pagseguro.com') !== false) {
            $split_url = str_replace(
                'https://sandbox.api.pagseguro.com',
                'https://internal.sandbox.api.pagseguro.com',
                $split_url
            );
        }
        
        // Make GET request without authentication headers (sandbox only)
        $response = wp_remote_get($split_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'user-agent' => 'WooCommerce / PagBank Integracoes',
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Erro ao buscar detalhes do split: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            throw new \Exception('Erro ao buscar detalhes do split. Status: ' . $status_code);
        }

        if (empty($body)) {
            throw new \Exception('Resposta vazia ao buscar detalhes do split');
        }

        $split_data = json_decode($body, true);
        if ($split_data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta inválida ao buscar detalhes do split: ' . json_last_error_msg());
        }

        return $split_data;
    }

    /**
     * Resolve 3DS status/id for admin display (PagBank authentication_method or Vindi three_ds / CREDIT_CARD_3DS).
     *
     * @param \WC_Order $order
     * @param array<string, mixed> $charge
     * @return array{status: ?string, id: ?string}
     */
    private function resolveCard3dsMeta($order, array $charge): array
    {
        $pm = is_array($charge['payment_method'] ?? null) ? $charge['payment_method'] : [];
        $auth = is_array($pm['authentication_method'] ?? null) ? $pm['authentication_method'] : [];
        $threeDs = is_array($pm['three_ds'] ?? null) ? $pm['three_ds'] : [];

        $status = isset($auth['status']) ? (string) $auth['status'] : '';
        $id = isset($auth['id']) ? (string) $auth['id'] : '';

        if ($status === '' && isset($threeDs['status'])) {
            $status = (string) $threeDs['status'];
        }
        if ($id === '' && isset($threeDs['authentication_transaction_id'])) {
            $id = (string) $threeDs['authentication_transaction_id'];
        }

        if (($status === '' || $id === '') && (string) ($pm['type'] ?? '') === 'CREDIT_CARD_3DS') {
            $payloadRaw = OrderMeta::get($order, '_card_3ds_payload');
            $payload = is_string($payloadRaw) ? json_decode($payloadRaw, true) : null;
            if (is_array($payload)) {
                if ($status === '' && !empty($payload['status'])) {
                    $status = (string) $payload['status'];
                }
                if ($id === '' && !empty($payload['authentication_transaction_id'])) {
                    $id = (string) $payload['authentication_transaction_id'];
                }
            }
            if ($status === '') {
                $status = 'AUTHENTICATED';
            }
        }

        return [
            'status' => $status !== '' ? $status : null,
            'id' => $id !== '' ? $id : null,
        ];
    }

    public function getThankyouInstructions($order_id){
        $alreadyEnqueued = wp_script_is('pagseguro-connect-success');
        if ($alreadyEnqueued) {
            return;
        }

        add_action('wp_footer', function() use ($order_id) {
            wp_enqueue_script(
                'pagseguro-connect-success',
                plugins_url('public/js/success.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );

            // Define the variables
            $order = new WC_Order($order_id);

            $jsVars = array(
                'orderId'            => $order_id,
                'orderStatus'        => $order->get_status(),
                'successBehavior'    => Params::getConfig('success_behavior', ''),
                'successBehaviorUrl' => Functions::applyOrderPlaceholders(
                    Params::getConfig('success_behavior_url', wc_get_page_permalink('myaccount')),
                    $order
                ),
                'successBehaviorJs'  => json_encode(
                    Functions::applyOrderPlaceholders(
                    Params::getConfig('success_behavior_js', ''), 
                    $order
                    )
                ),
            );

            // Pass the variables to the JavaScript file
            wp_localize_script('pagseguro-connect-success', 'pagbankVars', $jsVars);
        });
    }
}
