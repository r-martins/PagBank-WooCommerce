<?php

namespace RM_PagBank\Connect\Payments;

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
        $customer->setTaxId(Params::removeNonNumeric($this->order->get_meta('_billing_cpf')));
        $phone = new Phone();
        $number = Params::extractPhone($this->order);
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
            $itemObj->setUnitAmount(Params::convertToCents($item['line_subtotal']));
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
        $address->setPostalCode(Params::removeNonNumeric($this->order->get_meta('_shipping_postcode')));
        return $address;
    }
    
    public function getNotificationUrls(): array
    {
        $hash = Api::getOrderHash($this->order);
        return [
//            get_site_url() . '/?wc-api=rm_pagseguro_notification'
            'https://webhook.site/57730f29-ac28-4580-a92c-2f3d8fe004b5'
            . '/?wc_api=rm_ps_notif&hash=' . $hash
            ];
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
        }
        $order->update_status('pending');
        
        $order->add_meta_data('pagbank_order_id', $response['id'] ?? null, true);
        $order->add_meta_data('pagbank_order_charges', $response['charges'] ?? null, true);
        
    }
}