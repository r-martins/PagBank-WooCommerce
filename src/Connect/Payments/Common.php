<?php

namespace RM_PagSeguro\Connect\Payments;

use RM_PagSeguro\Helpers\Params;
use RM_PagSeguro\Object\Address;
use RM_PagSeguro\Object\Customer;
use RM_PagSeguro\Object\Item;
use RM_PagSeguro\Object\Phone;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Common methods shared between payment methods
 *
 * @author    Ricardo Martins
 * @package   RM_PagSeguro\Connect
 */
class Common
{
    /**
     * @var WC_Order $order
     */
    protected $order;

    /**
     * @param WC_Order $order
     */
    public function __construct($order)
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
        
        if ($this->order->has_shipping_address()){
            $return['shipping']['address'] = $this->getShippingAddress();
        }
        
        $return['notification_urls'] = $this->getNotificationUrls();
        
        return $return;
    }
    
    public function getCustomerData(){
        $customer = new Customer();
        $customer->setName($this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name());
        $customer->setEmail($this->order->get_billing_email());
        $customer->setTaxId(Params::remove_non_numeric($this->order->get_meta('_billing_cpf')));
        $phone = new Phone();
        $number = Params::extract_phone($this->order);
        $phone->setArea((int)$number['area']);
        $phone->setNumber((int)$number['number']);
        $customer->setPhone([
            $phone,
        ]);
        return $customer;
    }
    
    public function getItemsData(){
        $items = [];
        /** @var WC_Order_Item_Product $item */
        foreach ($this->order->get_items() as $item) {
            $itemObj = new Item();
            $itemObj->setReferenceId($item['product_id']);
            $itemObj->setName($item['name']);
            $itemObj->setQuantity($item['quantity']);
            $itemObj->setUnitAmount(Params::convert_to_cents($item['line_subtotal']));
            $items[] = $itemObj;
        }
        return $items;
    }

    public function getShippingAddress(){
        $address = new Address();
        $address->setStreet($this->order->get_meta('_shipping_address_1'));
        $address->setNumber($this->order->get_meta('_shipping_number'));
        if($this->order->get_meta('_shipping_complement'))
            $address->setComplement($this->order->get_meta('_shipping_complement'));
        $address->setLocality($this->order->get_meta('_shipping_neighborhood'));
        $address->setCity($this->order->get_meta('_shipping_city'));
        $address->setRegionCode($this->order->get_meta('_shipping_state'));
        $address->setPostalCode(Params::remove_non_numeric($this->order->get_meta('_shipping_postcode')));
        return $address;
    }
    
    public function getNotificationUrls(): array
    {
        return [get_site_url() . '/?wc-api=rm_pagseguro_notification'];
    }
    /**
     * @return WC_Order
     */
    public function getOrder(): WC_Order
    {
        return $this->order;
    }

    /**
     * @param WC_Order $order
     */
    public function setOrder(WC_Order $order): void
    {
        $this->order = $order;
    }
//    public function getTitle(){
//        return $this->title;
//    }

    /**
     * @param WC_Order $order
     * @param array $response
     *
     * @return void
     */
    public function process_response($order, $response) {
        
        switch ($order->get_meta('pagseguro_payment_method')){
            case 'pix':
                $order->add_meta_data('pagseguro_pix_qrcode', $response['qr_codes'][0]['links'][0]['href'] ?? null);
                $order->add_meta_data('pagseguro_pix_qrcode_text', $response['qr_codes'][0]['text'] ?? null);
                $order->add_meta_data('pagseguro_pix_qrcode_expiration', $response['qr_codes'][0]['expiration_date'] ?? null);
                break;
            case 'boleto':
                $order->add_meta_data('pagseguro_boleto_png', $response['charges'][0]['links'][1]['href'] ?? null);
                $order->add_meta_data('pagseguro_boleto_pdf', $response['charges'][0]['links'][0]['href'] ?? null);
                $order->add_meta_data('pagseguro_boleto_due_date', $response['charges'][0]['payment_method']['boleto']['due_date'] ?? null);
                $order->add_meta_data('pagseguro_boleto_barcode_formatted', $response['charges'][0]['payment_method']['boleto']['formatted_barcode'] ?? null);
                $order->add_meta_data('pagseguro_boleto_barcode', $response['charges'][0]['payment_method']['boleto']['barcode'] ?? null);
                break;
        }
        
        $order->add_meta_data('pagseguro_order_id', $response['id'] ?? null);
        $order->add_meta_data('pagseguro_order_charges', $response['charges'] ?? null);
        
    }
}