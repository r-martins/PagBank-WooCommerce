<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Functions;
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
        $discountExcludesShipping = Params::getBoletoConfig('boleto_discount_excludes_shipping', false) == 'yes';

        if (($discountConfig = Params::getBoletoConfig('boleto_discount', 0)) && ! is_wc_endpoint_url('order-pay')) {
            $discount = floatval(Params::getDiscountValue($discountConfig, $this->order, $discountExcludesShipping));
            $orderTotal = $orderTotal - $discount;

            $fee = new \WC_Order_Item_Fee();
            $fee->set_name(__('Desconto para pagamento com Boleto', 'rm-pagbank'));

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
        $paymentMethod->setType('BOLETO');
        $boleto = new BoletoObj();
        // Calculate due_date in Brazil timezone (UTC-3)
        $brazilTimezone = new \DateTimeZone('America/Sao_Paulo');
        $dueDate = new \DateTime('now', $brazilTimezone);
        $dueDate->modify('+' . Params::getBoletoConfig('boleto_expiry_days', 7) . ' day');
        $boleto->setDueDate($dueDate->format('Y-m-d'));
        $instruction_lines = new InstructionLines();
        $instruction_lines->setLine1(
            Functions::applyOrderPlaceholders(
                Params::getBoletoConfig('boleto_line_1', 'Não aceitar após vencimento'),
                $this->order,
            )
        );
        $instruction_lines->setLine2(
            Functions::applyOrderPlaceholders(
                Params::getBoletoConfig('boleto_line_2', 'Obrigado por sua compra.'),
                $this->order,
            )
        );
        $boleto->setInstructionLines($instruction_lines);

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
        $holderAddressStreet =  Functions::stringClear($holderAddressStreet);
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
        $boleto->setHolder($holder);
        $paymentMethod->setType('BOLETO');
        $paymentMethod->setBoleto($boleto);
        $charge->setPaymentMethod($paymentMethod);

        //region Split Integration
        // Check Dokan Split first (if Dokan is active)
        if (\RM_PagBank\Integrations\Dokan\DokanSplitManager::shouldApplySplit($this->order)) {
            $splitManager = new \RM_PagBank\Integrations\Dokan\DokanSplitManager();
            $splitData = $splitManager->buildSplitData($this->order, 'BOLETO');
            $charge->setSplits($splitData->jsonSerialize());
        }
        // If Dokan Split is not applied, check General Split
        elseif (\RM_PagBank\Integrations\GeneralSplitManager::shouldApplySplit($this->order)) {
            $splitData = \RM_PagBank\Integrations\GeneralSplitManager::buildSplitData($this->order, 'BOLETO');
            $charge->setSplits($splitData->jsonSerialize());
        }
        //endregion

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
        $template_path = Functions::getTemplate('boleto-instructions.php');
        require $template_path;
    }

}
