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
        $customer->setEmail($this->order->get_billing_email());
        
        //cpf or cnpj
        $taxId = Functions::getParamFromOrderMetaOrPost($this->order, '_billing_cpf', 'billing_cpf');
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

        if (wc_string_to_bool($this->order->get_meta('_rm_pagbank_checkout_blocks'))) {
            $taxId = $this->order->get_meta('_rm_pagbank_customer_document');
        }

        $taxId = Params::removeNonNumeric($taxId);
        
        if (!empty($taxId)) {
            $customer->setTaxId($taxId);
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

    protected function isHideItems()
    {
        return Params::getConfig('hide_items') == 'yes';
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
        $itemObj->setName('Compra em ' . get_bloginfo('name') ?? 'PagBank');
        $itemObj->setQuantity(1);
        $itemObj->setUnitAmount($amount);
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
            $itemObj->setName($item['name']);
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
	 * @return Address
	 */
	public function getShippingAddress(): Address
	{
        $address = new Address();
        $address->setStreet(substr($this->order->get_shipping_address_1('edit'), 0, 120));
        //Usually virtual orders don't have shipping address' attributes replicated. So we use billing address instead.
        $billingNumber = Functions::getParamFromOrderMetaOrPost($this->order, '_billing_number', 'billing_number');
        $shippingNumber = Functions::getParamFromOrderMetaOrPost($this->order, '_shipping_number', 'shipping_number');
        $shippingComplement = Functions::getParamFromOrderMetaOrPost(
            $this->order,
            '_shipping_complement',
            'shipping_complement'
        );
        $shippingComplement = substr($shippingComplement, 0, 40);
        $billingComplement = substr($this->order->get_billing_address_2('edit'), 0, 40);
        $billingNeighborhood = Functions::getParamFromOrderMetaOrPost(
            $this->order,
            '_billing_neighborhood',
            'billing_neighborhood'
        );
        $shippingNeighborhood = Functions::getParamFromOrderMetaOrPost(
            $this->order,
            '_shipping_neighborhood',
            'shipping_neighborhood'
        );
        $billingNeighborhood = substr($billingNeighborhood, 0, 60);
        $shippingNeighborhood = substr($shippingNeighborhood, 0, 60);

        $billingNumber = !empty($billingNumber) ? $billingNumber : '...';
        $address->setNumber($billingNumber);
        if (!empty($shippingNumber)) {
            $address->setNumber($shippingNumber);
        }

        $billingComplement = !empty($billingComplement) ? $billingComplement : '...';
        $address->setComplement($billingComplement);
        if (!empty($shippingComplement)) {
            $address->setComplement($shippingComplement);
        }

        $billingNeighborhood = !empty($billingNeighborhood) ? $billingNeighborhood : '...';
        $address->setLocality($billingNeighborhood);
        if (!empty($shippingNeighborhood)) {
            $address->setLocality($shippingNeighborhood);
        }
        
        $address->setCity(substr($this->order->get_shipping_city('edit'), 0, 60));
        $address->setRegionCode($this->order->get_shipping_state('edit'));
        $address->setPostalCode(Params::removeNonNumeric($this->order->get_shipping_postcode('edit')));

        return apply_filters('pagbank_connect_shipping_address', $address, $this->order);
    }

    /**
     * Populates the address object with data from the order
     * @return Address
     */
    public function getBillingAddress(): Address
    {
        $address = new Address();
        $address->setStreet($this->order->get_billing_address_1('edit'));
        $billingNumber = Functions::getParamFromOrderMetaOrPost($this->order, '_billing_number', 'billing_number');
        $shippingNumber = Functions::getParamFromOrderMetaOrPost($this->order, '_billing_number', 'billing_number');
        $billingComplement = Functions::getParamFromOrderMetaOrPost(
            $this->order,
            '_billing_complement',
            'billing_complement'
        );
        $billingNeighborhood = Functions::getParamFromOrderMetaOrPost(
            $this->order,
            '_billing_neighborhood',
            'billing_neighborhood'
        );
        $shippingNeighborhood = Functions::getParamFromOrderMetaOrPost(
            $this->order,
            '_billing_neighborhood',
            'billing_neighborhood'
        );

        $address->setNumber($billingNumber);
        if (!empty($shippingNumber)) {
            $address->setNumber($shippingNumber);
        }

        $address->setComplement($billingComplement);
        
        $address->setLocality($billingNeighborhood);
        if (!empty($shippingNeighborhood)) {
            $address->setLocality($shippingNeighborhood);
        }

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

		$order->update_status('pending', 'PagBank: Pagamento Pendente');

        do_action('pagbank_connect_after_proccess_response', $order, $response);
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
                    $order_id
                ),
                'successBehaviorJs'  => json_encode(
                    Functions::applyOrderPlaceholders(Params::getConfig('success_behavior_js', ''), $order_id)
                ),
            );

            // Pass the variables to the JavaScript file
            wp_localize_script('pagseguro-connect-success', 'pagbankVars', $jsVars);
        });
    }
}
