<?php

namespace RM_PagBank\Connect;

/**
 * Class OrderMetaBoxes
 * 
 * Manages meta boxes for order admin pages
 */
class OrderMetaBoxes
{
    /**
     * Initialize meta boxes
     */
    public static function init(): void
    {
        add_action('add_meta_boxes', [__CLASS__, 'addSplitMetaBox']);
    }

    /**
     * Add split payment meta box
     */
    public static function addSplitMetaBox(): void
    {
        // Get screen to check if we're on an order edit page
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'])) {
            return;
        }
        
        // Get order ID in a HPOS-compatible way
        $order_id = null;
        if (isset($_GET['post'])) {
            $order_id = absint($_GET['post']);
        } elseif (isset($_GET['id'])) {
            $order_id = absint($_GET['id']);
        }
        
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if ($order && $order->get_meta('_pagbank_split_applied')) {
            add_meta_box(
                'pagbank_split_details',
                __('PagBank - Detalhes do Split', 'pagbank-connect'),
                [__CLASS__, 'displaySplitMetaBox'],
                ['shop_order', 'woocommerce_page_wc-orders'],
                'side',
                'default'
            );
        }
    }

    /**
     * Display split payment meta box
     *
     * @param \WP_Post|object $post_or_order_object
     */
    public static function displaySplitMetaBox($post_or_order_object): void
    {
        // Get order in a HPOS-compatible way
        if ($post_or_order_object instanceof \WC_Order) {
            $order = $post_or_order_object;
        } elseif (isset($post_or_order_object->ID)) {
            $order = wc_get_order($post_or_order_object->ID);
        } else {
            return;
        }
        
        if (!$order) {
            return;
        }

        $split_data = $order->get_meta('_pagbank_split_data');
        if (!$split_data) {
            echo '<p>' . __('Dados de split não encontrados.', 'pagbank-connect') . '</p>';
            return;
        }

        echo '<div style="margin-top: 10px;">';
        
        // Split Method
        $method = $split_data['method'] ?? '';
        echo '<div style="margin-bottom: 15px;">';
        echo '<strong>' . __('Método de Split:', 'pagbank-connect') . '</strong> ';
        echo '<span style="color: #0073aa;">' . esc_html($method) . '</span>';
        echo '</div>';
        
        // Receivers
        echo '<h4 style="margin-top: 15px;">' . __('Receivers:', 'pagbank-connect') . '</h4>';
        
        if (isset($split_data['receivers']) && is_array($split_data['receivers'])) {
            foreach ($split_data['receivers'] as $index => $receiver) {
                $account_id = $receiver['account']['id'] ?? '';
                $amount_value = $receiver['amount']['value'] ?? 0;
                
                // For PERCENTAGE method, value is already a percentage (float)
                // For FIXED method, value is in cents and needs to be divided by 100
                if ($method === 'PERCENTAGE') {
                    $amount = $amount_value; // Already a percentage
                    $amount_display = number_format($amount, 2, ',', '.') . '%';
                } else {
                    $amount = $amount_value / 100; // Convert cents to currency
                    $amount_display = 'R$ ' . number_format($amount, 2, ',', '.');
                }
                
                $type = $receiver['type'] ?? '';
                $reason = $receiver['reason'] ?? '';
                $configurations = $receiver['configurations'] ?? [];
                
                $border_color = $type === 'PRIMARY' ? '#0073aa' : '#46b450';
                
                echo '<div style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border-left: 4px solid ' . $border_color . '; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                
                // Type and Account
                echo '<div style="margin-bottom: 8px;">';
                echo '<strong style="color: ' . $border_color . '; font-size: 13px;">' . esc_html($type) . '</strong>';
                echo '</div>';
                
                // Account ID
                echo '<div style="margin-bottom: 5px; font-size: 12px;">';
                echo '<strong>' . __('Account ID:', 'pagbank-connect') . '</strong> ';
                echo '<code style="background: #fff; padding: 2px 6px; border-radius: 3px;">' . esc_html($account_id) . '</code>';
                echo '</div>';
                
                // Amount
                echo '<div style="margin-bottom: 5px; font-size: 12px;">';
                echo '<strong>' . ($method === 'PERCENTAGE' ? __('Percentual:', 'pagbank-connect') : __('Valor:', 'pagbank-connect')) . '</strong> ';
                echo '<span style="color: #2ea44f; font-weight: 600;">' . $amount_display . '</span>';
                echo '</div>';
                
                // Reason
                echo '<div style="margin-bottom: 8px; font-size: 12px;">';
                echo '<strong>' . __('Motivo:', 'pagbank-connect') . '</strong> ';
                echo '<span style="color: #666;">' . esc_html($reason) . '</span>';
                echo '</div>';
                
                // Configurations
                if (!empty($configurations)) {
                    echo '<div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #ddd;">';
                    echo '<strong style="font-size: 11px; color: #666;">' . __('Configurações:', 'pagbank-connect') . '</strong>';
                    echo '<ul style="margin: 5px 0 0 0; padding-left: 20px; font-size: 11px;">';
                    
                    // Custody
                    if (isset($configurations['custody'])) {
                        $custody_apply = $configurations['custody']['apply'] ?? false;
                        $custody_scheduled = $configurations['custody']['release']['scheduled'] ?? null;
                        
                        echo '<li>';
                        echo '<strong>' . __('Custódia:', 'pagbank-connect') . '</strong> ';
                        if ($custody_apply) {
                            echo '<span style="color: #d63638;">✓ Ativa</span>';
                            if ($custody_scheduled) {
                                $date = date('d/m/Y H:i', strtotime($custody_scheduled));
                                echo ' (' . __('Liberação:', 'pagbank-connect') . ' ' . $date . ')';
                            }
                        } else {
                            echo '<span style="color: #666;">✗ Inativa</span>';
                        }
                        echo '</li>';
                    }
                    
                    // Liable
                    if (isset($configurations['liable'])) {
                        $liable = $configurations['liable'] ?? false;
                        echo '<li>';
                        echo '<strong>' . __('Responsável (Liable):', 'pagbank-connect') . '</strong> ';
                        echo $liable ? '<span style="color: #d63638;">✓ Sim</span>' : '<span style="color: #666;">✗ Não</span>';
                        echo '</li>';
                    }
                    
                    // Chargeback
                    if (isset($configurations['chargeback']['charge_transfer']['percentage'])) {
                        $chargeback_pct = $configurations['chargeback']['charge_transfer']['percentage'] ?? 0;
                        echo '<li>';
                        echo '<strong>' . __('Chargeback:', 'pagbank-connect') . '</strong> ';
                        echo '<span style="color: #666;">' . $chargeback_pct . '%</span>';
                        echo '</li>';
                    }
                    
                    echo '</ul>';
                    echo '</div>';
                }
                
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
}

