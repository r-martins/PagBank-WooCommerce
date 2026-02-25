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

    /** Fee ID for Boleto discount (cart and order). Use this to detect our fee; do not match by name. */
    public const DISCOUNT_FEE_ID = 'pagbank_boleto_discount';


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
        $amount->setValue(Params::convertToCents($this->order->get_total()));
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
        $billingCompany = $this->order->get_billing_company();
        $taxId = null;
        
        // Always check for CNPJ first (independent of company name)
        $taxId = Functions::getParamFromOrderMetaOrPost($this->order, '_billing_cnpj', 'billing_cnpj');
        
        // If CNPJ not found in order meta/post, try from customer meta (only if customer exists)
        if (empty($taxId) && $this->order->get_customer_id()) {
            $wcCustomer = new \WC_Customer($this->order->get_customer_id());
            $taxId = $wcCustomer->get_meta('billing_cnpj');
        }
        
        // If still no taxId (no CNPJ found), use the standard customer data method (checks CPF first, then CNPJ)
        if (empty($taxId)) {
            $customerData = $this->getCustomerData();
            $taxId = $customerData->getTaxId();
            if (wc_string_to_bool($this->order->get_meta('_rm_pagbank_checkout_blocks'))) {
                $taxId = $this->order->get_meta('_rm_pagbank_customer_document');
            }
        }
        
        // Clean taxId (remove non-numeric characters)
        if (!empty($taxId)) {
            $taxId = Params::removeNonNumeric($taxId);
        }
        
        $holder = new Holder();
        // Use company name if available, otherwise use person name
        if (!empty($billingCompany)) {
            $holder->setName(trim($billingCompany));
        } else {
            $holder->setName($this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name());
        }
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
        
        // Verificar se o charge está DECLINED
        $charges = $order->get_meta('pagbank_order_charges');
        $is_declined = false;
        if (!empty($charges) && is_array($charges)) {
            foreach ($charges as $charge) {
                if (isset($charge['status']) && $charge['status'] === 'DECLINED') {
                    $is_declined = true;
                    break;
                }
            }
        }
        
        // Verificar se há boleto válido (código de barras e links)
        $has_valid_boleto = !empty($boleto_barcode) || !empty($boleto_barcode_formatted);
        $has_valid_links = !empty($boleto_pdf) || !empty($boleto_png);
        
        // Só exibir se não estiver DECLINED e houver boleto válido
        if ($is_declined || (!$has_valid_boleto && !$has_valid_links)) {
            return;
        }
        
        $template_path = Functions::getTemplate('boleto-instructions.php');
        require $template_path;
    }

}
