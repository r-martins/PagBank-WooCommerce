<?php

namespace RM_PagBank\Integrations\Dokan;

use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Receiver;
use RM_PagBank\Object\Split;
use WC_Order;
use WC_Order_Item;

/**
 * Class DokanSplitManager
 * 
 * Manages payment split functionality for Dokan marketplace integration
 */
class DokanSplitManager
{
    /**
     * Check if split should be applied to order
     *
     * @param WC_Order $order
     * @return bool
     */
    public static function shouldApplySplit(WC_Order $order): bool
    {
        // Check if Dokan is active
        if (!function_exists('dokan')) {
            \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Dokan não está ativo', 'info');
            return false;
        }

        // Check mutual exclusivity with Split Payments
        $gateway = new \RM_PagBank\Connect\Gateway();
        $split_payments_enabled = $gateway->get_option('split_payments_enabled', 'no');
        
        if ($split_payments_enabled === 'yes') {
            \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Divisão de Pagamentos está ativa, não aplicando Split Dokan', 'info');
            return false;
        }

        // Check if split is enabled
        $gateway_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
        $split_enabled = $gateway_settings['dokan_split_enabled'] ?? false;
        
        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Split enabled: ' . var_export($split_enabled, true), 'info');
        
        if ($split_enabled !== 'yes') {
            \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Split não está habilitado nas configurações', 'info');
            return false;
        }

        // Check if order has multiple vendors
        $vendors = self::getOrderVendors($order);
        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Vendedores encontrados: ' . count($vendors), 'info');
        
        if (empty($vendors)) {
            \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Nenhum vendedor encontrado', 'info');
            return false;
        }

        // Check if order total is greater than 0
        if ($order->get_total() <= 0) {
            \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Total do pedido é 0', 'info');
            return false;
        }

        // Check if all vendors have PagBank Account ID
        foreach ($vendors as $vendor) {
            $accountId = self::getVendorPagBankAccount($vendor['id']);
            if (!$accountId) {
                \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Vendedor ' . $vendor['id'] . ' não tem Account ID configurado', 'info');
                return false;
            }
            \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Vendedor ' . $vendor['id'] . ' tem Account ID: ' . substr($accountId, 0, 8) . '...', 'info');
        }

        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::shouldApplySplit - Split será aplicado!', 'info');
        return true;
    }

    /**
     * Get vendors from order items
     *
     * @param WC_Order $order
     * @return array
     */
    public static function getOrderVendors(WC_Order $order): array
    {
        $vendors = [];
        
        foreach ($order->get_items() as $item) {
            if (!$item->is_type('line_item')) {
                continue;
            }
            
            // Check if item has get_product method
            if (!method_exists($item, 'get_product')) {
                continue;
            }
            
            /** @var \WC_Product|false $product */
            $product = $item->get_product(); // @phpstan-ignore-line
            if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
                continue;
            }
            
            // @phpstan-ignore-next-line
            $product_id = $product->get_id();
            $vendor_id = get_post_field('post_author', $product_id);
            
            if (!$vendor_id) {
                continue;
            }
            
            // Check if vendor is a seller or admin
            if (!dokan_is_user_seller($vendor_id)) {
                // Check if it's an admin (marketplace)
                $user = get_userdata($vendor_id);
                if (!$user || !in_array('administrator', $user->roles)) {
                    continue;
                }
            }
            
            if (!isset($vendors[$vendor_id])) {
                $vendors[$vendor_id] = [
                    'id' => $vendor_id,
                    'items' => []
                ];
            }
            
            $vendors[$vendor_id]['items'][] = $item;
        }

        return array_values($vendors);
    }

    /**
     * Get vendor's PagBank Account ID
     *
     * @param int $vendor_id
     * @return string|null
     */
    public static function getVendorPagBankAccount(int $vendor_id): ?string
    {
        // Check if this is the marketplace (admin user)
        $user = get_userdata($vendor_id);
        if ($user && in_array('administrator', $user->roles)) {
            // For marketplace/admin, get Account ID from plugin settings
            $gateway_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
            $marketplace_account_id = $gateway_settings['marketplace_account_id'] ?? '';
            
            if (!empty($marketplace_account_id)) {
                \RM_PagBank\Helpers\Functions::log(
                    'DokanSplitManager::getVendorPagBankAccount - Marketplace (ID: ' . $vendor_id . ') usando Account ID das configurações',
                    'info'
                );
                return $marketplace_account_id;
            }
        }
        
        // For regular vendors, get from user meta
        $account_id = get_user_meta($vendor_id, 'pagbank_account_id', true);
        $validated = get_user_meta($vendor_id, 'pagbank_account_validated', true);
        
        // Para desenvolvimento: permitir Account IDs não validados
        // Em produção, use: return ($account_id && $validated) ? $account_id : null;
        return $account_id ?: null;
    }

    /**
     * Calculate marketplace commission amount
     * For marketplace products: 100% of the product value (proportional to discount)
     * For vendor products: admin commission only (proportional to discount)
     *
     * @param WC_Order $order
     * @return int Amount in cents
     */
    public static function calculateMarketplaceAmount(WC_Order $order): int
    {
        $total_without_discount = 0;
        $marketplace_total = 0;
        $vendors = self::getOrderVendors($order);
        $marketplace_vendor_ids = [];
        
        // Identify marketplace vendors (admin users)
        foreach ($vendors as $vendor) {
            $user = get_userdata($vendor['id']);
            if ($user && in_array('administrator', $user->roles)) {
                $marketplace_vendor_ids[] = $vendor['id'];
            }
        }
        
        // First pass: calculate totals without discount
        foreach ($order->get_items() as $item) {
            if (!$item->is_type('line_item')) {
                continue;
            }
            
            $product = $item->get_product(); // @phpstan-ignore-line
            if ($product && is_object($product) && method_exists($product, 'get_id')) {
                $vendor_id = get_post_field('post_author', $product->get_id());
                $item_total = $item->get_total(); // @phpstan-ignore-line
                
                $total_without_discount += $item_total;
                
                if (in_array($vendor_id, $marketplace_vendor_ids)) {
                    // Marketplace product: full item total
                    $marketplace_total += $item_total;
                } else {
                    // Vendor product: admin commission only
                    $commission_result = dokan()->commission->get_commission([
                        'order_item_id' => $item->get_id(),
                    ]);
                    $marketplace_total += $commission_result->get_admin_commission();
                }
            }
        }
        
        // Apply proportional discount to marketplace amount
        $order_total = $order->get_total();
        if ($total_without_discount > 0) {
            $discount_factor = $order_total / $total_without_discount;
            $marketplace_total = $marketplace_total * $discount_factor;
        }
        
        return Params::convertToCents($marketplace_total);
    }

    /**
     * Calculate vendor commission amount
     *
     * @param WC_Order_Item $item
     * @return int Amount in cents
     */
    public static function calculateVendorCommission(WC_Order_Item $item): int
    {
        // Use new get_commission method with order_item_id
        $commission_result = dokan()->commission->get_commission([
            'order_item_id' => $item->get_id(),
        ]);
        
        return Params::convertToCents($commission_result->get_vendor_earning());
    }

    /**
     * Build split data for PagBank API
     *
     * @param WC_Order $order
     * @param string $payment_method_type
     * @return Split
     */
    public static function buildSplitData(WC_Order $order, string $payment_method_type): Split
    {
        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::buildSplitData - Iniciando construção de split para pedido ' . $order->get_id(), 'info');
        
        $vendors = self::getOrderVendors($order);
        $isCreditCard = $payment_method_type === 'CREDIT_CARD';
        $gateway_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
        $marketplaceReason = $gateway_settings['split_marketplace_reason'] ?? 'Comissão do Marketplace';
        
        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::buildSplitData - Vendedores: ' . count($vendors) . ', Método: ' . $payment_method_type, 'info');
        
        $split = new Split();
        $split->setMethod(Split::METHOD_FIXED);
        
        // Calculate discount factor
        $total_without_discount = 0;
        foreach ($order->get_items() as $item) {
            if ($item->is_type('line_item')) {
                $total_without_discount += $item->get_total(); // @phpstan-ignore-line
            }
        }
        $order_total = $order->get_total();
        $discount_factor = ($total_without_discount > 0) ? ($order_total / $total_without_discount) : 1;
        
        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::buildSplitData - Fator de desconto: ' . $discount_factor . ' (Total: ' . $order_total . ', Sem desconto: ' . $total_without_discount . ')', 'info');
        
        // Identify marketplace vendors (admin users)
        $marketplace_vendor_ids = [];
        foreach ($vendors as $vendor) {
            $user = get_userdata($vendor['id']);
            if ($user && in_array('administrator', $user->roles)) {
                $marketplace_vendor_ids[] = $vendor['id'];
            }
        }
        
        // Marketplace as primary receiver
        $marketplaceReceiver = new Receiver();
        $marketplaceReceiver->setAccount(['id' => $gateway_settings['marketplace_account_id'] ?? '']);
        $marketplaceReceiver->setAmount(['value' => self::calculateMarketplaceAmount($order)]);
        $marketplaceReceiver->setReason($marketplaceReason);
        $marketplaceReceiver->setType(Receiver::TYPE_PRIMARY);
        
        // Get liability configuration
        $gateway_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
        $liability_config_raw = $gateway_settings['split_chargeback_liability'] ?? 'auto';
        $liability_config = is_string($liability_config_raw) ? trim($liability_config_raw) : 'auto';
        
        \RM_PagBank\Helpers\Functions::log(
            sprintf(
                'DokanSplitManager::buildSplitData - Configuração de liable lida: "%s" (tipo: %s, valor bruto: %s)',
                $liability_config,
                gettype($liability_config),
                var_export($gateway_settings['split_chargeback_liability'] ?? 'não definido', true)
            ),
            'info'
        );
        
        // Count non-marketplace vendors
        $non_marketplace_vendors = array_filter($vendors, function($v) use ($marketplace_vendor_ids) {
            return !in_array($v['id'], $marketplace_vendor_ids);
        });
        $vendor_count = count($non_marketplace_vendors);
        
        // Determine who is liable (only for credit card)
        $marketplace_is_liable = false;
        $single_vendor_id = null;
        
        if ($isCreditCard) {
            // Use strict comparison and handle both 'marketplace' and possible variations
            if ($liability_config === 'marketplace' || strtolower($liability_config) === 'marketplace') {
                // Always marketplace is liable - explicitly set single_vendor_id to null
                $marketplace_is_liable = true;
                $single_vendor_id = null; // Ensure no vendor is marked as liable
            } else {
                // Auto: single vendor = vendor is liable, 2+ vendors = marketplace is liable
                if ($vendor_count === 1) {
                    $marketplace_is_liable = false;
                    $single_vendor_id = reset($non_marketplace_vendors)['id'];
                } else {
                    $marketplace_is_liable = true;
                    $single_vendor_id = null; // Ensure no vendor is marked as liable when marketplace is liable
                }
            }
        }
        
        \RM_PagBank\Helpers\Functions::log(
            sprintf(
                'DokanSplitManager::buildSplitData - Configuração de liable: %s, Marketplace liable: %s, Single vendor ID: %s, Vendor count: %d',
                $liability_config,
                $marketplace_is_liable ? 'true' : 'false',
                $single_vendor_id ?? 'null',
                $vendor_count
            ),
            'info'
        );
        
        // Marketplace configurations
        $marketplaceReceiver->setCustody(false); // No custody for marketplace
        $marketplaceReceiver->setChargeback(0); // No chargeback transfer
        
        // Set liable only for credit card payments
        if ($isCreditCard) {
            $marketplaceReceiver->setLiable($marketplace_is_liable);
            \RM_PagBank\Helpers\Functions::log(
                sprintf(
                    'DokanSplitManager::buildSplitData - Marketplace marcado como liable: %s (config: %s, %d vendedores não-marketplace)',
                    $marketplace_is_liable ? 'SIM' : 'NÃO',
                    $liability_config,
                    $vendor_count
                ),
                'info'
            );
        } else {
            \RM_PagBank\Helpers\Functions::log(
                'DokanSplitManager::buildSplitData - Método de pagamento não é cartão de crédito, liable não será aplicado',
                'info'
            );
        }
        
        // Don't set statement - removed until PagBank fixes their API
        // $marketplaceReceiver->setStatement(true);
        
        $split->addReceiver($marketplaceReceiver);
        
        // Vendors as secondary receivers (excluding marketplace)
        foreach ($vendors as $vendor) {
            // Skip marketplace vendors - they are already added as PRIMARY
            if (in_array($vendor['id'], $marketplace_vendor_ids)) {
                continue;
            }
            $vendorAmount = 0;
            foreach ($vendor['items'] as $item) {
                $vendorAmount += self::calculateVendorCommission($item);
            }
            
            // Apply discount factor to vendor amount
            $vendorAmount = (int) round($vendorAmount * $discount_factor);
            
            $vendorReceiver = new Receiver();
            $vendorReceiver->setAccount(['id' => self::getVendorPagBankAccount($vendor['id'])]);
            $vendorReceiver->setAmount(['value' => $vendorAmount]);
            $vendorReceiver->setReason(self::buildVendorReason($order, $vendor['id']));
            $vendorReceiver->setType(Receiver::TYPE_SECONDARY);
            
            // Vendor configurations
            $vendorReceiver->setCustody(true, self::calculateCustodyReleaseDate($order));
            // Chargeback transfer should always be 0% for secondary receivers
            // The marketplace (PRIMARY) is responsible for the payment, so chargeback is handled by them
            $vendorReceiver->setChargeback(0);
            
            // Single vendor is liable (only for credit card, when marketplace is NOT liable)
            // IMPORTANT: If marketplace is liable, NO vendor should be marked as liable
            // Only set liable=true for the single vendor when marketplace is NOT liable
            if ($isCreditCard && !$marketplace_is_liable && $single_vendor_id !== null && $vendor['id'] === $single_vendor_id) {
                $vendorReceiver->setLiable(true);
                \RM_PagBank\Helpers\Functions::log(
                    sprintf(
                        'DokanSplitManager::buildSplitData - Vendedor %d marcado como liable (config: %s, marketplace liable: false)',
                        $vendor['id'],
                        $liability_config
                    ),
                    'info'
                );
            }
            // Note: We don't explicitly set liable=false for vendors when marketplace is liable
            // because the API should not receive the liable property at all for those vendors
            
            // Don't set statement - removed until PagBank fixes their API
            // $vendorReceiver->setStatement(false);
            
            $split->addReceiver($vendorReceiver);
        }
        
        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::buildSplitData - Split construído com ' . count($split->getReceivers()) . ' recebedores', 'info');
        \RM_PagBank\Helpers\Functions::log('DokanSplitManager::buildSplitData - Dados do split: ' . json_encode($split->jsonSerialize()), 'info');
        
        return $split;
    }

    /**
     * Build vendor reason with product SKUs
     *
     * @param WC_Order $order
     * @param int $vendor_id
     * @return string
     */
    protected static function buildVendorReason(WC_Order $order, int $vendor_id): string
    {
        $skus = [];
        
        foreach ($order->get_items() as $item) {
            if (!$item->is_type('line_item')) {
                continue;
            }
            
            // Check if item has get_product method
            if (!method_exists($item, 'get_product')) {
                continue;
            }
            
            /** @var \WC_Product|false $product */
            $product = $item->get_product(); // @phpstan-ignore-line
            if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
                continue;
            }
            
            // @phpstan-ignore-next-line
            $product_id = $product->get_id();
            if (is_object($product) && method_exists($product, 'get_id') && get_post_field('post_author', $product->get_id()) == $vendor_id) {
                $sku = $product->get_sku();
                if ($sku) {
                    $skus[] = $sku;
                }
            }
        }
        
        if (empty($skus)) {
            $vendor = dokan()->vendor->get($vendor_id);
            $reason = sprintf(__('Venda %s', 'pagbank-connect'), $vendor->get_shop_name());
        } else {
            $reason = sprintf(__('Venda dos produtos %s', 'pagbank-connect'), implode(' ', $skus));
        }
        
        // Remove non-alphanumeric characters (PagBank only accepts alphanumeric)
        return preg_replace('/[^a-zA-Z0-9\s]/', '', $reason);
    }

    /**
     * Calculate custody release date
     *
     * @param WC_Order $order
     * @return string ISO 8601 date
     */
    protected static function calculateCustodyReleaseDate(WC_Order $order): string
    {
        $gateway_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
        $custody_days = $gateway_settings['split_custody_days'] ?? 7;
        
        // Calculate release date from order date
        $order_date = $order->get_date_created();
        $release_date = $order_date->add(new \DateInterval('P' . $custody_days . 'D'));
        
        return $release_date->format('c'); // ISO 8601
    }

    /**
     * Release custody for order
     *
     * @param int $order_id
     * @return bool
     */
    public static function releaseCustody(int $order_id): bool
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        // Check if order has split
        $has_split = $order->get_meta('_pagbank_split_applied');
        $split_id = $order->get_meta('_pagbank_split_id');
        $parent_order = null;
        
        // If not found in current order, check parent order (Dokan suborder case)
        if (!$has_split || !$split_id) {
            $parent_order_id = $order->get_parent_id();
            if ($parent_order_id) {
                $parent_order = wc_get_order($parent_order_id);
                if ($parent_order) {
                    $has_split = $parent_order->get_meta('_pagbank_split_applied');
                    $split_id = $parent_order->get_meta('_pagbank_split_id');
                    
                    \RM_PagBank\Helpers\Functions::log(
                        sprintf(
                            'DokanSplitManager::releaseCustody - Subpedido %d verificando pedido pai %d para custódia',
                            $order_id,
                            $parent_order_id
                        ),
                        'info'
                    );
                }
            }
        }
        
        if (!$has_split) {
            \RM_PagBank\Helpers\Functions::log(
                'DokanSplitManager::releaseCustody - Pedido ' . $order_id . ' não possui split aplicado',
                'debug'
            );
            return false;
        }

        // Get split ID from order
        if (!$split_id) {
            \RM_PagBank\Helpers\Functions::log(
                'DokanSplitManager::releaseCustody - Pedido ' . $order_id . ' não possui split_id',
                'debug'
            );
            return false;
        }

        // Get vendor Account ID for this specific order
        $vendor_account_id = null;
        
        // If it's a suborder, get the vendor from the order
        if ($parent_order) {
            // Get vendor ID from suborder
            $vendor_id = dokan_get_seller_id_by_order($order_id);
            if ($vendor_id) {
                $vendor_account_id = self::getVendorPagBankAccount($vendor_id);
            }
        }
        
        // If no vendor found or it's the main order, we don't release (marketplace handles this differently)
        if (!$vendor_account_id) {
            \RM_PagBank\Helpers\Functions::log(
                'DokanSplitManager::releaseCustody - Nenhum vendedor específico encontrado para liberar custódia no pedido ' . $order_id,
                'debug'
            );
            return false;
        }

        // Call PagBank API to release custody for specific vendor
        try {
            $api = new \RM_PagBank\Helpers\Api();
            $payload = [
                'receivers' => [
                    [
                        'account' => [
                            'id' => $vendor_account_id
                        ]
                    ]
                ]
            ];
            
            $response = $api->post('splits/' . $split_id . '/custody/release', $payload);
            
            \RM_PagBank\Helpers\Functions::log(
                sprintf(
                    'DokanSplitManager::releaseCustody - Custódia liberada para pedido %d (split_id: %s, vendor: %s)',
                    $order_id,
                    $split_id,
                    substr($vendor_account_id, 0, 15) . '...'
                ),
                'info'
            );
            
            // Mark custody as released in the current order
            $order->update_meta_data('_pagbank_custody_released', true);
            $order->update_meta_data('_pagbank_custody_released_date', current_time('mysql'));
            $order->update_meta_data('_pagbank_custody_released_vendor', $vendor_account_id);
            $order->save();
            
            return true;
        } catch (\Exception $e) {
            \RM_PagBank\Helpers\Functions::log(
                sprintf(
                    'DokanSplitManager::releaseCustody - Erro ao liberar custódia para pedido %d: %s',
                    $order_id,
                    $e->getMessage()
                ),
                'error'
            );
            return false;
        }
    }
}

