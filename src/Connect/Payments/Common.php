<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Address;
use RM_PagBank\Object\Customer;
use RM_PagBank\Object\Item;
use RM_PagBank\Object\Phone;
use WC_Customer;
use WC_Order;
use WC_Order_Item_Product;

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

    protected function getHashEmail()
    {
        $email = strtolower($this->order->get_billing_email());
        $hash = hash('md5', $email);
        return "{$hash}@pagbankconnect.pag";
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
        $customer->setEmail($this->isHashEmail() ? $this->getHashEmail() : $this->order->get_billing_email());
        
        $taxId = null;
        
        if (wc_string_to_bool($this->order->get_meta('_rm_pagbank_checkout_blocks'))) {
            $taxId = $this->order->get_meta('_rm_pagbank_customer_document');
        }

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


        $taxId = Params::removeNonNumeric($taxId);
        
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
            $itemObj->setName(Functions::sanitizeProductName($item['name']));
            $itemObj->setQuantity($item['quantity']);

            $amount = $item->get_subtotal('edit') / $item['quantity'];
            if ($product->get_meta('_recurring_enabled') == 'yes' && $product->get_meta('_recurring_trial_length') > 0) {
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

        switch ($order->get_meta('pagbank_payment_method')){
            case 'pix':
                $order->add_meta_data('pagbank_pix_qrcode', $response['qr_codes'][0]['links'][0]['href'] ?? null, true);
                $order->add_meta_data('pagbank_pix_qrcode_text', $response['qr_codes'][0]['text'] ?? null, true);
                $order->add_meta_data('pagbank_pix_qrcode_expiration', $response['qr_codes'][0]['expiration_date'] ?? null, true);
                $order->set_props(['payment_method' => 'rm-pagbank-pix']);
                break;
            case 'boleto':
                $order->add_meta_data('pagbank_boleto_png', $response['charges'][0]['links'][1]['href'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_pdf', $response['charges'][0]['links'][0]['href'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_due_date', $response['charges'][0]['payment_method']['boleto']['due_date'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_barcode_formatted', $response['charges'][0]['payment_method']['boleto']['formatted_barcode'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_barcode', $response['charges'][0]['payment_method']['boleto']['barcode'] ?? null, true);
                $order->set_props(['payment_method' => 'rm-pagbank-boleto']);
                break;
			case 'credit_card':
				$order->add_meta_data('_pagbank_card_installments', $response['charges'][0]['payment_method']['installments'] ?? null);
				$order->add_meta_data('Parcelas', $response['charges'][0]['payment_method']['installments'] ?? null);
				$order->add_meta_data('_pagbank_card_brand', $response['charges'][0]['payment_method']['card']['brand'] ?? null);
				$order->add_meta_data('_pagbank_card_first_digits', $response['charges'][0]['payment_method']['card']['first_digits'] ?? null);
				$order->add_meta_data('_pagbank_card_last_digits', $response['charges'][0]['payment_method']['card']['last_digits'] ?? null);
				$order->add_meta_data('_pagbank_card_holder', $response['charges'][0]['payment_method']['card']['holder']['name'] ?? null);
				$order->add_meta_data('_pagbank_card_exp_month', $response['charges'][0]['payment_method']['card']['exp_month'] ?? null);
				$order->add_meta_data('_pagbank_card_exp_year', $response['charges'][0]['payment_method']['card']['exp_year'] ?? null);
				$order->add_meta_data('_pagbank_card_response_reference', $response['charges'][0]['payment_response']['reference'] ?? null);
				$order->add_meta_data('_pagbank_card_3ds_status', $response['charges'][0]['payment_method']['authentication_method']['status'] ?? null);
                $order->set_props(['payment_method' => 'rm-pagbank-cc']);
				break;
            case 'redirect':
                $order->add_meta_data('pagbank_redirect_url', $response['links'][1]['href'] ?? null, true);
                $order->add_meta_data('pagbank_redirect_expiration', $response['expiration_date'] ?? null, false);
                $order->add_meta_data('pagbank_checkout_id', $response['id'] ?? null, true);
                $order->set_props(['payment_method' => 'rm-pagbank-redirect']);
                break;
                
        }
        if (isset($response['id']) && substr($response['id'], 0, 4) != 'CHEC'){ //if not a pagbank checkout code
            $order->add_meta_data('pagbank_order_id', $response['id'], true);
        }
		$order->add_meta_data('pagbank_order_charges', $response['charges'] ?? null, true);
		$order->add_meta_data('pagbank_is_sandbox', Params::getConfig('is_sandbox', false) ? 1 : 0);
		
		// Save split data if present (for Pix, split data comes directly in response)
		if (!empty($response['qr_codes'][0]['splits'])) {
		    $order->add_meta_data('_pagbank_split_data', $response['qr_codes'][0]['splits'], true);
		    $order->add_meta_data('_pagbank_split_applied', true, true);
		} elseif (!empty($response['charges'][0]['payment_method']['splits'])) {
		    $order->add_meta_data('_pagbank_split_data', $response['charges'][0]['payment_method']['splits'], true);
		    $order->add_meta_data('_pagbank_split_applied', true, true);
		}
		
		// For credit card payments, split details come via a link (SPLIT)
		// We need to fetch the split details from the API
		if (!empty($response['charges'][0]['links'])) {
		    foreach ($response['charges'][0]['links'] as $link) {
		        if (isset($link['rel']) && $link['rel'] === 'SPLIT' && !empty($link['href'])) {
		            // Extract split_id from href (last part of URL)
		            $split_id = basename(parse_url($link['href'], PHP_URL_PATH));
		            $order->add_meta_data('_pagbank_split_id', $split_id, true);
		            Functions::log('Split ID encontrado no pedido ' . $order->get_id() . ': ' . $split_id, 'info');
		            
		            // Fetch split details from the API
		            try {
		                $split_details = self::fetchSplitDetails($link['href']);
		                if (!empty($split_details)) {
		                    // Save split data in the same format as Pix
		                    $order->add_meta_data('_pagbank_split_data', $split_details, true);
		                    $order->add_meta_data('_pagbank_split_applied', true, true);
		                    Functions::log('Detalhes do split obtidos e salvos para o pedido ' . $order->get_id(), 'info');
		                }
		            } catch (\Exception $e) {
		                Functions::log('Erro ao buscar detalhes do split para o pedido ' . $order->get_id() . ': ' . $e->getMessage(), 'error');
		            }
		            break;
		        }
		    }
		}

		$order->update_status('pending', 'PagBank: Pagamento Pendente');

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
