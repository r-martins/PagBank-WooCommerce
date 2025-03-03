<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Address;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\Boleto as BoletoObj;
use RM_PagBank\Object\Charge;
use RM_PagBank\Object\Customer;
use RM_PagBank\Object\Holder;
use RM_PagBank\Object\InstructionLines;
use RM_PagBank\Object\PaymentMethod;
use WC_Data_Exception;
use WC_Order;

/**
 * Class Redirect
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class Redirect extends Common
{
    public string $code = 'redirect';


	/**
	 * Prepare order params for Redirect
	 *
	 * @return array
	 * @throws WC_Data_Exception
	 */
    public function prepare(): array
    {
        $return = $this->getDefaultParameters();

        $charge = new Charge();
        $charge->setReferenceId($this->order->get_id());

        $amount = new Amount();
        $orderTotal = $this->order->get_total();
        $discountExcludesShipping = Params::getRedirectConfig('redirect_discount_excludes_shipping', false) == 'yes';

        if (($discountConfig = Params::getRedirectConfig('redirect_discount', 0)) && ! is_wc_endpoint_url('order-pay')) {
            $discount = floatval(Params::getDiscountValue($discountConfig, $this->order, $discountExcludesShipping));
            $orderTotal = $orderTotal - $discount;

            $fee = new \WC_Order_Item_Fee();
            $fee->set_name(__('Desconto para pagamento com Redirect', 'rm-pagbank'));

            // Define the fee amount, negative number to discount
            $fee->set_amount(-$discount);
            $fee->set_total(-$discount);

            // Define the tax class for the fee
            $fee->set_tax_class('');
            $fee->set_tax_status('none');

            // Add the fee to the order
            $this->order->add_item($fee);

            // Recalculate the order
            $this->order->calculate_totals();
        }

        $amount->setValue(Params::convertToCents($orderTotal));
        $charge->setAmount($amount);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setType('REDIRECT');

        //cpf or cnpj
        $customerData = $this->getCustomerData();
        $taxId = $customerData->getTaxId();
        if (wc_string_to_bool($this->order->get_meta('_rm_pagbank_checkout_blocks'))) {
            $taxId = $this->order->get_meta('_rm_pagbank_customer_document');
        }
        
        $holder = new Holder();
        $holder->setName($this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name());
        $holder->setTaxId($taxId);
        $holder->setEmail($this->order->get_billing_email());

        $address = $this->getBillingAddress();
        $holderAddress = new Address();
        $holderAddress->setCountry('BRA');
        $holderAddress->setCity(substr($address->getCity(), 0, 60));
        $holderAddress->setPostalCode($address->getPostalCode());

        $holderAddressStreet = $address->getStreet();
        //remove non A-Z 0-9 characters
        $holderAddressStreet = preg_replace('/[^A-Za-z0-9\ ]/', '', $holderAddressStreet);
        $holderAddress->setStreet(substr($holderAddressStreet, 0, 100));
        $holderAddress->setRegionCode($address->getRegionCode());

        $holderAddressNumber = !empty($address->getNumber()) ? $address->getNumber() : '...';
        $holderAddress->setNumber(substr($holderAddressNumber, 0, 20));
        $locality = !empty($address->getLocality()) ? $address->getLocality() : '...';
        $holderAddress->setLocality($locality);

        if($address->getComplement()) {
            $holderAddress->setComplement(substr($address->getComplement(), 0, 40));
        }

        $holder->setAddress($holderAddress);
        $charge->setPaymentMethod($paymentMethod);

        $customerModifiable = ['customer_modifiable' => true];
        $redirectUrl = ['redirect_url' => $this->order->get_checkout_order_received_url()];

        return array_merge($return, $customerModifiable, $redirectUrl);
    }

	/**
	 * Set some variables and requires the template with redirect instructions for the success page
	 * @param $order_id
	 *
	 * @return void
	 * @noinspection SpellCheckingInspection
	 */
	public function getThankyouInstructions($order_id){
        $order = new WC_Order($order_id);
        $redirect_link = $order->get_meta('pagbank_redirect_link');
        require_once dirname(__FILE__) . '/../../templates/redirect-instructions.php';
    }
    
    public function getCustomerData(): Customer
    {
        $customer = parent::getCustomerData();
        $phone = $customer->getPhone();
        $customer->setPhone($phone[0]);
        return $customer;
    }

}
