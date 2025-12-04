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
        add_action('wp_ajax_pagbank_get_split_details', [__CLASS__, 'ajaxGetSplitDetails']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueScripts']);
    }
    
    /**
     * Enqueue scripts and styles for split details modal
     */
    public static function enqueueScripts($hook): void
    {
        // Only enqueue on order edit pages
        if (!in_array($hook, ['post.php', 'post-new.php', 'woocommerce_page_wc-orders'])) {
            return;
        }
        
        // Check if we're on an order edit page
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'])) {
            return;
        }
        
        // Get order ID
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
        if (!$order || !$order->get_meta('_pagbank_split_applied')) {
            return;
        }
        
        // Enqueue Thickbox (WordPress built-in)
        wp_enqueue_script('thickbox');
        wp_enqueue_style('thickbox');
        
        // Enqueue our custom script
        wp_enqueue_script(
            'pagbank-split-details-modal',
            plugins_url('public/js/admin/split-details-modal.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            ['jquery', 'thickbox'],
            WC_PAGSEGURO_CONNECT_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'pagbank-split-details-modal',
            'pagbankSplitDetails',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pagbank_get_split_details')
            ]
        );
        
        // Enqueue custom CSS
        wp_add_inline_style('thickbox', self::getModalStyles());
    }
    
    /**
     * Get inline CSS for modal styling
     */
    private static function getModalStyles(): string
    {
        return '
            #TB_window.pagbank-split-modal {
                max-width: 90% !important;
            }
            #TB_ajaxContent.pagbank-split-modal-content {
                padding: 20px !important;
                max-height: 80vh !important;
                overflow-y: auto;
            }
            .pagbank-split-details-container h3 {
                margin-top: 0;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #ddd;
            }
            .pagbank-split-details-container table.widefat {
                margin-top: 10px;
            }
            .pagbank-split-details-container table.widefat td {
                padding: 8px 12px;
                vertical-align: top;
            }
            .pagbank-split-details-container code {
                background: #f0f0f0;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
            }
            .pagbank-split-details-container pre {
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            .status-done {
                color: #46b450;
                font-weight: bold;
            }
            .status-pending {
                color: #f56e28;
                font-weight: bold;
            }
            .status-cancelled {
                color: #dc3232;
                font-weight: bold;
            }
        ';
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
        
        // Get total payment amount to calculate real values for percentage splits
        $total_payment_value = 0;
        if (isset($split_data['payment']['amount']['value'])) {
            $total_payment_value = floatval($split_data['payment']['amount']['value']) / 100; // Convert cents to currency
        } elseif ($order) {
            // Fallback: use order total if payment amount is not available in split data
            $total_payment_value = floatval($order->get_total());
        }
        
        // Receivers
        echo '<h4 style="margin-top: 15px;">' . __('Receivers:', 'pagbank-connect') . '</h4>';
        
        if (isset($split_data['receivers']) && is_array($split_data['receivers'])) {
            foreach ($split_data['receivers'] as $index => $receiver) {
                $account_id = $receiver['account']['id'] ?? '';
                $amount_value = $receiver['amount']['value'] ?? 0;
                
                // For PERCENTAGE method, value is already a percentage (float)
                // For FIXED method, value is in cents and needs to be divided by 100
                if ($method === 'PERCENTAGE') {
                    $percentage = floatval($amount_value); // Already a percentage
                    $amount_display = number_format($percentage, 2, ',', '.') . '%';
                    
                    // Calculate real value paid based on percentage
                    $real_value = ($total_payment_value * $percentage) / 100;
                    $real_value_display = 'R$ ' . number_format($real_value, 2, ',', '.');
                } else {
                    $amount = $amount_value / 100; // Convert cents to currency
                    $amount_display = 'R$ ' . number_format($amount, 2, ',', '.');
                    $real_value_display = $amount_display; // For FIXED, amount is already the real value
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
                
                // Amount (percentage or fixed)
                echo '<div style="margin-bottom: 5px; font-size: 12px;">';
                echo '<strong>' . ($method === 'PERCENTAGE' ? __('Percentual:', 'pagbank-connect') : __('Valor:', 'pagbank-connect')) . '</strong> ';
                echo '<span style="color: #2ea44f; font-weight: 600;">' . $amount_display . '</span>';
                echo '</div>';
                
                // Real value paid (for PERCENTAGE method, show calculated value; for FIXED, same as amount)
                if ($method === 'PERCENTAGE' && $total_payment_value > 0) {
                    echo '<div style="margin-bottom: 5px; font-size: 12px;">';
                    echo '<strong>' . __('Valor Pago:', 'pagbank-connect') . '</strong> ';
                    echo '<span style="color: #0073aa; font-weight: 600;">' . $real_value_display . '</span>';
                    echo '</div>';
                }
                
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
        
        // Add button to view full split details
        // Show button if split is applied (either with split_id for credit card or with split_data for Pix)
        $split_id = $order->get_meta('_pagbank_split_id');
        if ($split_data || $split_id) {
            echo '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
            echo '<button type="button" class="button button-secondary pagbank-view-split-details" data-order-id="' . esc_attr($order->get_id()) . '" data-split-id="' . esc_attr($split_id ?: '') . '" data-has-split-data="' . ($split_data ? '1' : '0') . '">';
            echo '<span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span> ';
            echo __('Ver Detalhes Completos do Split', 'pagbank-connect');
            echo '</button>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * AJAX handler to fetch split details from PagBank API
     */
    public static function ajaxGetSplitDetails(): void
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pagbank_get_split_details')) {
            wp_send_json_error(['message' => __('Verificação de segurança falhou.', 'pagbank-connect')]);
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Você não tem permissão para realizar esta ação.', 'pagbank-connect')]);
            return;
        }
        
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $split_id = isset($_POST['split_id']) ? sanitize_text_field($_POST['split_id']) : '';
        $has_split_data = isset($_POST['has_split_data']) ? absint($_POST['has_split_data']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Parâmetros inválidos.', 'pagbank-connect')]);
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Pedido não encontrado.', 'pagbank-connect')]);
            return;
        }
        
        try {
            $split_data = null;
            
            // If we have split_id, fetch from API (credit card payments)
            if ($split_id) {
                // Check if we're in sandbox mode
                $is_sandbox = $order->get_meta('pagbank_is_sandbox') == 1;
                
                // Build split URL for fetchSplitDetails
                // In production, fetchSplitDetails will use authenticated API
                // In sandbox, it will use the direct URL
                $base_url = $is_sandbox 
                    ? 'https://sandbox.api.pagseguro.com' 
                    : 'https://api.pagseguro.com';
                $split_url = $base_url . '/splits/' . $split_id;
                
                // Use the fetch logic from Common.php
                $split_data = \RM_PagBank\Connect\Payments\Common::fetchSplitDetails($split_url);
                
                if (!$split_data) {
                    throw new \Exception('Nenhum dado retornado da API.');
                }
            } 
            // If no split_id but we have split_data saved (Pix payments), use saved data
            elseif ($has_split_data) {
                $split_data = $order->get_meta('_pagbank_split_data');
                
                if (!$split_data) {
                    throw new \Exception('Dados do split não encontrados no pedido.');
                }
            } else {
                throw new \Exception('Nenhum dado de split disponível.');
            }
            
            wp_send_json_success([
                'data' => $split_data,
                'json_formatted' => wp_json_encode($split_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
}

