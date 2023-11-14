<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Address;
use RM_PagBank\Object\Customer;
use RM_PagBank\Object\Item;
use RM_PagBank\Object\Phone;
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

        if ($this->order->has_shipping_address() && Params::getConfig('shipping_param', '') !== 'never'){
            $address = $this->getShippingAddress();
            $return['shipping']['address'] = $address;
            $helper = new Params();
            if (Params::getConfig('shipping_param', '') === 'validate' && ! $helper->isAddressValid($address)){
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
        $customer->setName($this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name());
        $customer->setEmail($this->order->get_billing_email());
        $customer->setTaxId(Params::removeNonNumeric($this->order->get_meta('_billing_cpf'))) ;
        $phone = new Phone();
        $number = Params::extractPhone($this->order);
        $phone->setArea((int)$number['area']);
        $phone->setNumber((int)$number['number']);
        $customer->setPhone([
            $phone,
        ]);
        return $customer;
    }

	/**
	 * Populates the items array with data from the order
	 * @return array
	 */
	public function getItemsData(): array
	{
        $items = [];
        /** @var WC_Order_Item_Product $item */
        foreach ($this->order->get_items() as $item) {
            $itemObj = new Item();
            $itemObj->setReferenceId($item['product_id']);
            $itemObj->setName($item['name']);
            $itemObj->setQuantity($item['quantity']);
            $itemObj->setUnitAmount(Params::convertToCents($item['line_subtotal']));
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
        $address->setStreet($this->order->get_shipping_address_1('edit'));
        $address->setNumber($this->order->get_meta('_shipping_number'));
        if( ! empty($this->order->get_meta('_shipping_complement')) )
            $address->setComplement($this->order->get_meta('_shipping_complement'));
        
        $address->setLocality($this->order->get_meta('_shipping_neighborhood'));
        
        $address->setCity($this->order->get_shipping_city('edit'));
        $address->setRegionCode($this->order->get_shipping_state('edit'));
        $address->setPostalCode(Params::removeNonNumeric($this->order->get_shipping_postcode('edit')));
        return $address;
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
                break;
            case 'boleto':
                $order->add_meta_data('pagbank_boleto_png', $response['charges'][0]['links'][1]['href'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_pdf', $response['charges'][0]['links'][0]['href'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_due_date', $response['charges'][0]['payment_method']['boleto']['due_date'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_barcode_formatted', $response['charges'][0]['payment_method']['boleto']['formatted_barcode'] ?? null, true);
                $order->add_meta_data('pagbank_boleto_barcode', $response['charges'][0]['payment_method']['boleto']['barcode'] ?? null, true);
                break;
			case 'credit_card':
				$order->add_meta_data('_pagbank_card_installments', $response['charges'][0]['payment_method']['installments'] ?? null);
				$order->add_meta_data('_pagbank_card_brand', $response['charges'][0]['payment_method']['card']['brand'] ?? null);
				$order->add_meta_data('_pagbank_card_first_digits', $response['charges'][0]['payment_method']['card']['first_digits'] ?? null);
				$order->add_meta_data('_pagbank_card_last_digits', $response['charges'][0]['payment_method']['card']['last_digits'] ?? null);
				$order->add_meta_data('_pagbank_card_holder', $response['charges'][0]['payment_method']['card']['holder']['name'] ?? null);
				$order->add_meta_data('_pagbank_card_exp_month', $response['charges'][0]['payment_method']['card']['exp_month'] ?? null);
				$order->add_meta_data('_pagbank_card_exp_year', $response['charges'][0]['payment_method']['card']['exp_year'] ?? null);
				$order->add_meta_data('_pagbank_card_response_reference', $response['charges'][0]['payment_response']['reference'] ?? null);
				break;
        }
		$order->add_meta_data('pagbank_order_id', $response['id'] ?? null, true);
		$order->add_meta_data('pagbank_order_charges', $response['charges'] ?? null, true);
		$order->add_meta_data('pagbank_is_sandbox', Params::getConfig('is_sandbox', false) ? 1 : 0);

		$order->update_status('pending', 'PagBank: Pagamento Pendente');

	}
}
