<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Address;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\Boleto as BoletoObj;
use RM_PagBank\Object\Charge;
use RM_PagBank\Object\Holder;
use RM_PagBank\Object\InstructionLines;
use RM_PagBank\Object\PaymentMethod;
use WC_Data_Exception;
use WC_Order;

/**
 * Class Boleto
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class Boleto extends Common
{
    public string $code = 'boleto';


	/**
	 * Prepare order params for Boleto
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
        $discountExcludesShipping = Params::getConfig('boleto_discount_excludes_shipping', false) == 'yes';

        if (($discountConfig = Params::getConfig('boleto_discount', 0)) && ! is_wc_endpoint_url('order-pay')) {
            $discount = floatval(Params::getDiscountValue($discountConfig, $this->order, $discountExcludesShipping));
            $this->order->set_discount_total(floatval($this->order->get_discount_total()) + $discount);
            $this->order->set_total($this->order->get_total() - $discount);
            $orderTotal = $orderTotal - $discount;
        }

        $amount->setValue(Params::convertToCents($orderTotal));
        $charge->setAmount($amount);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setType('BOLETO');
        $boleto = new BoletoObj();
        $boleto->setDueDate(gmdate('Y-m-d', strtotime('+' . Params::getConfig('boleto_expiry_days', 7) . 'day')));
        $instruction_lines = new InstructionLines();
        $instruction_lines->setLine1(Params::getConfig('boleto_line_1', 'Não aceitar após vencimento'));
        $instruction_lines->setLine2(Params::getConfig('boleto_line_2', 'Obrigado por sua compra.'));
        $boleto->setInstructionLines($instruction_lines);

        //cpf or cnpj
        $customerData = $this->getCustomerData();
        $taxId = $customerData->getTaxId();
        
        $holder = new Holder();
        $holder->setName($this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name());
        $holder->setTaxId($taxId);
        $holder->setEmail($this->order->get_billing_email());

        $address = $this->getBillingAddress();
        $holderAddress = new Address();
        $holderAddress->setCountry('BRA');
        $holderAddress->setCity($address->getCity());
        $holderAddress->setPostalCode($address->getPostalCode());
        $locality = $address->getLocality();
        
        $holderAddress->setLocality($locality);
        $holderAddress->setStreet($address->getStreet());
        $holderAddress->setNumber($address->getNumber());
        $holderAddress->setRegionCode($address->getRegionCode());

        if($address->getComplement())
            $holderAddress->setComplement($address->getComplement());
        $holder->setAddress($holderAddress);
        $boleto->setHolder($holder);
        $paymentMethod->setType('BOLETO');
        $paymentMethod->setBoleto($boleto);
        $charge->setPaymentMethod($paymentMethod);

        $charges = ['charges' => [$charge]];

        return array_merge($return, $charges);

    }

	/**
	 * Set some variables and requires the template with boleto instructions for the success page
	 * @param $order_id
	 *
	 * @return void
	 * @noinspection SpellCheckingInspection
	 */
	public function getThankyouInstructions($order_id){
        $order = new WC_Order($order_id);
        $boleto_barcode = $order->get_meta('pagbank_boleto_barcode');
        $boleto_barcode_formatted = $order->get_meta('pagbank_boleto_barcode_formatted');
        $boleto_due_date = $order->get_meta('pagbank_boleto_due_date');
        $boleto_pdf = $order->get_meta('pagbank_boleto_pdf');
        $boleto_png = $order->get_meta('pagbank_boleto_png');
        require_once dirname(__FILE__) . '/../../templates/boleto-instructions.php';
    }

}
