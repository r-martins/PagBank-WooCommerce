<?php

namespace RM_PagBank\Integrations;

use RM_PagBank\Object\Receiver;
use RM_PagBank\Object\Split;
use WC_Order;

/**
 * Class GeneralSplitManager
 * 
 * Manages general payment split functionality (not Dokan-specific)
 */
class GeneralSplitManager
{
    /**
     * Check if general split should be applied to order
     *
     * @param WC_Order $order
     * @return bool
     */
    public static function shouldApplySplit(WC_Order $order): bool
    {
        // Check if general split payments is enabled
        $gateway = new \RM_PagBank\Connect\Gateway();
        $split_enabled = $gateway->get_option('split_payments_enabled', 'no');
        
        \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::shouldApplySplit - Split enabled: ' . var_export($split_enabled, true), 'info');
        
        if ($split_enabled !== 'yes') {
            \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::shouldApplySplit - Split não está habilitado nas configurações', 'info');
            return false;
        }

        // Check mutual exclusivity with Dokan Split
        if (function_exists('dokan')) {
            $integrations_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
            $dokan_split_enabled = $integrations_settings['dokan_split_enabled'] ?? 'no';
            
            if ($dokan_split_enabled === 'yes') {
                \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::shouldApplySplit - Split Dokan está ativo, não aplicando Divisão de Pagamentos', 'info');
                return false;
            }
        }

        // Check if primary account ID is configured (should be set when split is enabled)
        $primary_account_id = $gateway->get_option('split_payments_primary_account_id', '');
        
        \RM_PagBank\Helpers\Functions::log(
            sprintf(
                'GeneralSplitManager::shouldApplySplit - Account ID principal: %s (vazio: %s)',
                $primary_account_id ?: '(vazio)',
                empty($primary_account_id) ? 'sim' : 'não'
            ),
            'info'
        );
        
        if (empty($primary_account_id)) {
            \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::shouldApplySplit - Account ID principal não configurado', 'info');
            return false;
        }
        
        // Check if we have receivers configured
        $receivers = $gateway->get_option('split_payments_receivers', []);
        
        if (empty($receivers) || !is_array($receivers)) {
            \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::shouldApplySplit - Nenhum recebedor secundário configurado', 'info');
            return false;
        }

        // Check if order total is greater than 0
        if ($order->get_total() <= 0) {
            \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::shouldApplySplit - Valor do pedido é zero ou negativo', 'info');
            return false;
        }

        \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::shouldApplySplit - Split deve ser aplicado', 'info');
        return true;
    }

    /**
     * Build split data for order
     *
     * @param WC_Order $order
     * @param string $payment_method_type
     * @return Split
     */
    public static function buildSplitData(WC_Order $order, string $payment_method_type): Split
    {
        \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::buildSplitData - Iniciando construção de split para pedido ' . $order->get_id(), 'info');
        
        $gateway = new \RM_PagBank\Connect\Gateway();
        $receivers_config = $gateway->get_option('split_payments_receivers', []);
        $isCreditCard = $payment_method_type === 'CREDIT_CARD';
        
        $split = new Split();
        $split->setMethod(Split::METHOD_PERCENTAGE);
        
        // Calculate total percentage from configured receivers
        $total_percentage = 0;
        foreach ($receivers_config as $receiver_config) {
            $total_percentage += floatval($receiver_config['percentage'] ?? 0);
        }
        
        // Primary receiver gets the remaining percentage (100% - total_percentage)
        $primary_percentage = 100 - $total_percentage;
        
        // Get primary account ID from settings (should be set when split is enabled)
        $primary_account_id = $gateway->get_option('split_payments_primary_account_id', '');
        
        if (empty($primary_account_id)) {
            \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::buildSplitData - Account ID principal não configurado', 'error');
            throw new \Exception('Account ID principal não configurado. Configure o Account ID Principal nas configurações de Divisão de Pagamentos.');
        }
        
        // Validate Account ID format
        $pattern = '/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/';
        if (!preg_match($pattern, $primary_account_id)) {
            \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::buildSplitData - Account ID principal com formato inválido: ' . $primary_account_id, 'error');
            throw new \Exception('Account ID principal com formato inválido.');
        }
        
        // Primary receiver (account from settings)
        $primaryReceiver = new Receiver();
        
        $primaryReceiver->setAccount(['id' => $primary_account_id]);
        // For PERCENTAGE method, amount.value is the percentage as float
        $primaryReceiver->setAmount(['value' => (float) $primary_percentage]);
        $primaryReceiver->setReason('Pagamento principal');
        $primaryReceiver->setType(Receiver::TYPE_PRIMARY);
        $primaryReceiver->setCustody(false);
        $primaryReceiver->setChargeback(0);
        
        // Set liable only for credit card (primary is always liable for general split)
        if ($isCreditCard) {
            // $primaryReceiver->setLiable(true);
        }
        
        $split->addReceiver($primaryReceiver);
        
        // Secondary receivers (from configuration)
        foreach ($receivers_config as $receiver_config) {
            $account_id = $receiver_config['account_id'] ?? '';
            $percentage = floatval($receiver_config['percentage'] ?? 0);
            
            if (empty($account_id) || $percentage <= 0) {
                continue;
            }
            
            $receiver = new Receiver();
            $receiver->setAccount(['id' => $account_id]);
            // For PERCENTAGE method, amount.value is the percentage as float
            $receiver->setAmount(['value' => $percentage]);
            $receiver->setReason('Divisão de pagamento');
            $receiver->setType(Receiver::TYPE_SECONDARY);
            $receiver->setCustody(false); // No custody for general split
            $receiver->setChargeback(0); // No chargeback transfer
            
            // Secondary receivers are never liable
            // $receiver->setLiable(false);
            
            $split->addReceiver($receiver);
        }
        
        \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::buildSplitData - Split construído com ' . count($split->getReceivers()) . ' recebedores', 'info');
        \RM_PagBank\Helpers\Functions::log('GeneralSplitManager::buildSplitData - Dados do split: ' . json_encode($split->jsonSerialize()), 'info');
        
        return $split;
    }
}

