<?php

namespace RM_PagBank\Integrations\Dokan;

use RM_PagBank\Helpers\Functions;

/**
 * Class DokanHooks
 * 
 * Manages Dokan-related hooks and actions
 */
class DokanHooks
{
    /**
     * Initialize hooks
     */
    public static function init(): void
    {
        // Add Account ID field to vendor dashboard (Settings > Store)
        add_action('dokan_settings_form_bottom', [__CLASS__, 'addVendorProfileFields'], 10, 2);
        add_action('dokan_store_profile_saved', [__CLASS__, 'saveVendorProfileFields'], 10, 2);
        
        // Add Account ID field to admin user profile (wp-admin/user-edit.php)
        add_action('dokan_seller_meta_fields', [__CLASS__, 'addDokanAdminProfileFields'], 10, 1);
        add_action('dokan_process_seller_meta_fields', [__CLASS__, 'saveDokanAdminProfileFields'], 10, 1);
        
        // Add admin notice on Dokan pages
        add_action('admin_notices', [__CLASS__, 'addAccountIdAdminNotice']);
        
        // Release custody when order is completed
        add_action('woocommerce_order_status_completed', [__CLASS__, 'releaseCustodyOnOrderComplete']);
    }

    /**
     * Add vendor profile fields for PagBank Account ID
     *
     * @param int $current_user User ID
     * @param array $profile_info Profile information
     */
    public static function addVendorProfileFields($current_user, array $profile_info): void
    {
        // Handle both WP_User object and int
        if (is_object($current_user) && isset($current_user->ID)) {
            $user_id = $current_user->ID;
        } else {
            $user_id = (int) $current_user;
        }
        
        $account_id = get_user_meta($user_id, 'pagbank_account_id', true);
        $account_validated = get_user_meta($user_id, 'pagbank_account_validated', true);
        $is_readonly = !empty($account_id); // Read-only if already configured
        
        ?>
        <div class="dokan-form-group">
            <label class="dokan-w3 dokan-control-label" for="pagbank_account_id">
                <?php _e('PagBank Account ID', 'pagbank-connect'); ?>
                <?php if ($account_validated): ?>
                    <span style="color: green; margin-left: 5px;">✓ <?php _e('Validado', 'pagbank-connect'); ?></span>
                <?php endif; ?>
            </label>
            
            <div class="dokan-w5 dokan-text-left">
                <input 
                    type="text" 
                    class="dokan-form-control" 
                    id="pagbank_account_id" 
                    name="pagbank_account_id" 
                    value="<?php echo esc_attr($account_id); ?>"
                    <?php echo $is_readonly ? 'readonly' : ''; ?>
                    pattern="ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}"
                    maxlength="41"
                />
                <?php wp_nonce_field('dokan_pagbank_account', 'pagbank_account_nonce'); ?>
                
                <?php if (!$is_readonly): ?>
                    <p class="help-block" style="margin-top: 5px; color: #0073aa;">
                        <strong><?php _e('Primeira configuração:', 'pagbank-connect'); ?></strong> 
                        <?php _e('Você pode configurar seu Account ID PagBank uma vez. Após configurado, apenas administradores poderão alterar.', 'pagbank-connect'); ?>
                    </p>
                <?php endif; ?>
                <p class="help-block">
                    <?php _e('Seu Account ID PagBank para receber pagamentos diretos via split. Formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx (41 caracteres)', 'pagbank-connect'); ?>
                    <br>
                    <a href="https://ws.pbintegracoes.com/pspro/v7/connect/account-id/authorize" target="_blank" rel="noopener noreferrer">
                        <?php _e('Clique aqui para descobrir qual é o seu Account ID', 'pagbank-connect'); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Validate Account ID format on input
            $('#pagbank_account_id').on('input', function() {
                var accountId = $(this).val();
                var pattern = /^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/;
                
                if (accountId && !pattern.test(accountId)) {
                    $(this).css('border-color', 'red');
                } else {
                    $(this).css('border-color', '');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save vendor profile fields - allow initial configuration only
     *
     * @param int $store_id
     * @param array $dokan_settings
     */
    public static function saveVendorProfileFields(int $store_id, array $dokan_settings): void
    {
        // Check if nonce is present and valid
        if (!isset($_POST['pagbank_account_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['pagbank_account_nonce'], 'dokan_pagbank_account')) {
            return;
        }

        // Check if Account ID is already configured
        $existing_account_id = get_user_meta($store_id, 'pagbank_account_id', true);
        if (!empty($existing_account_id)) {
            // Account ID already configured - vendor cannot change it
            return;
        }

        // Check if vendor is trying to configure Account ID
        if (!isset($_POST['pagbank_account_id']) || empty($_POST['pagbank_account_id'])) {
            return;
        }

        $account_id = sanitize_text_field($_POST['pagbank_account_id']);
        
        // Validate format
        if (!preg_match('/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/', $account_id)) {
            $error_message = __('Formato de Account ID inválido. Use o formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'pagbank-connect');
            
            if (defined('DOING_AJAX') && DOING_AJAX) {
                wp_send_json_error($error_message);
            }
            
            wc_add_notice($error_message, 'error');
            return;
        }

        // Validate against marketplace Account ID
        $marketplace_account_id = get_option('woocommerce_rm-pagbank-integrations_marketplace_account_id');
        if ($marketplace_account_id && $account_id === $marketplace_account_id) {
            wc_add_notice(__('Este Account ID pertence ao marketplace. Use um Account ID diferente.', 'pagbank-connect'), 'error');
            return;
        }

        // Check if Account ID is already used by another vendor
        if (self::isAccountIdAlreadyUsed($account_id, $store_id)) {
            wc_add_notice(__('Este Account ID já está sendo usado por outro vendedor. Use um Account ID diferente.', 'pagbank-connect'), 'error');
            return;
        }

        // Validate Account ID format
        $validation_result = \RM_PagBank\Helpers\Functions::validateAccountId($account_id);
        
        if (is_wp_error($validation_result)) {
            $error_message = __('Erro ao validar Account ID: ', 'pagbank-connect') . $validation_result->get_error_message();
            
            // For AJAX requests, send JSON error
            if (defined('DOING_AJAX') && DOING_AJAX) {
                wp_send_json_error($error_message);
            }
            
            wc_add_notice($error_message, 'error');
            return;
        }

        // Save Account ID
        update_user_meta($store_id, 'pagbank_account_id', $account_id);
        
        // Mark as validated since API validation passed
        update_user_meta($store_id, 'pagbank_account_validated', true);
        
        // Log the configuration
        Functions::log(sprintf(__('Vendedor %d configurou Account ID PagBank: %s', 'pagbank-connect'), $store_id, substr($account_id, 0, 8) . '...' . substr($account_id, -4)), 'info');
        
        // Send notification to admin
        self::notifyAdminAccountConfigured($store_id, $account_id);
        
        wc_add_notice(__('Account ID PagBank configurado com sucesso!', 'pagbank-connect'), 'success');
    }

    /**
     * Check if Account ID is already used by another vendor
     *
     * @param string $account_id
     * @param int $exclude_user_id User ID to exclude from check
     * @return bool
     */
    protected static function isAccountIdAlreadyUsed(string $account_id, int $exclude_user_id = 0): bool
    {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'pagbank_account_id' 
            AND meta_value = %s 
            AND user_id != %d 
            LIMIT 1",
            $account_id,
            $exclude_user_id
        );
        
        $existing = $wpdb->get_var($query);
        return !empty($existing);
    }

    /**
     * Notify admin when a vendor configures their Account ID
     *
     * @param int $vendor_id
     * @param string $account_id
     */
    protected static function notifyAdminAccountConfigured(int $vendor_id, string $account_id): void
    {
        $gateway_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
        $notify_enabled = $gateway_settings['split_notifications'] ?? 'no';
        
        if ($notify_enabled !== 'yes') {
            return;
        }

        $vendor = dokan()->vendor->get($vendor_id);
        $admin_email = get_option('admin_email');
        
        $subject = sprintf(__('[%s] Vendedor configurou Account ID PagBank', 'pagbank-connect'), get_bloginfo('name'));
        $message = sprintf(
            __("O vendedor %s configurou seu Account ID PagBank.\n\nVendedor: %s\nAccount ID: %s\n\nData: %s", 'pagbank-connect'),
            $vendor->get_shop_name(),
            $vendor->get_name(),
            substr($account_id, 0, 8) . '...' . substr($account_id, -4),
            current_time('mysql')
        );
        
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Add Dokan admin profile fields
     *
     * @param \WP_User $user
     * @return void
     */
    public static function addDokanAdminProfileFields(\WP_User $user): void
    {
        $user_id = $user->ID;
        $account_id = get_user_meta($user_id, 'pagbank_account_id', true);
        $account_validated = get_user_meta($user_id, 'pagbank_account_validated', true);
        
        ?>
        <tr>
            <th>
                <label for="pagbank_account_id">
                    <?php _e('PagBank Account ID', 'pagbank-connect'); ?>
                    <?php if ($account_validated): ?>
                        <span style="color: green; margin-left: 5px;">✓ <?php _e('Validado', 'pagbank-connect'); ?></span>
                    <?php endif; ?>
                </label>
            </th>
            <td>
                <input 
                    type="text" 
                    class="regular-text" 
                    id="pagbank_account_id" 
                    name="pagbank_account_id" 
                    value="<?php echo esc_attr($account_id); ?>"
                    pattern="ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}"
                    maxlength="41"
                />
                <?php if (empty($account_id)): ?>
                    <p class="description" style="color: #0073aa;">
                        <strong><?php _e('Primeira configuração:', 'pagbank-connect'); ?></strong> 
                        <?php _e('Configure o Account ID PagBank do vendedor. O vendedor também pode configurar uma vez no painel dele.', 'pagbank-connect'); ?>
                    </p>
                <?php endif; ?>
                
                <p class="description">
                    <?php _e('Formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx (41 caracteres)', 'pagbank-connect'); ?>
                    <br>
                    <a href="https://ws.pbintegracoes.com/pspro/v7/connect/account-id/authorize" target="_blank" rel="noopener noreferrer">
                        <?php _e('Clique aqui para descobrir qual é o seu Account ID', 'pagbank-connect'); ?>
                    </a>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save Dokan admin profile fields
     *
     * @param int $user_id
     * @param array $data Optional
     */
    public static function saveDokanAdminProfileFields(int $user_id, array $data = []): void
    {
        // Check if Account ID is being updated
        if (!isset($_POST['pagbank_account_id'])) {
            return;
        }

        $account_id = sanitize_text_field($_POST['pagbank_account_id']);
        
        // If empty, remove
        if (empty($account_id)) {
            delete_user_meta($user_id, 'pagbank_account_id');
            delete_user_meta($user_id, 'pagbank_account_validated');
            return;
        }
        
        // Validate format
        if (!preg_match('/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/', $account_id)) {
            add_settings_error('pagbank_account_id', 'invalid_format', __('Formato de Account ID inválido.', 'pagbank-connect'));
            return;
        }

        // Validate against marketplace Account ID
        $marketplace_account_id = get_option('woocommerce_rm-pagbank-integrations_marketplace_account_id');
        if ($marketplace_account_id && $account_id === $marketplace_account_id) {
            add_settings_error('pagbank_account_id', 'marketplace_id', __('Este Account ID pertence ao marketplace.', 'pagbank-connect'));
            return;
        }

        // Check if Account ID is already used by another vendor
        if (self::isAccountIdAlreadyUsed($account_id, $user_id)) {
            add_settings_error('pagbank_account_id', 'already_used', __('Este Account ID já está sendo usado por outro vendedor.', 'pagbank-connect'));
            return;
        }

        // Validate Account ID format
        $validation_result = \RM_PagBank\Helpers\Functions::validateAccountId($account_id);
        
        if (is_wp_error($validation_result)) {
            add_settings_error('pagbank_account_id', 'validation_failed', __('Erro ao validar Account ID: ', 'pagbank-connect') . $validation_result->get_error_message());
            return;
        }

        // Save Account ID
        update_user_meta($user_id, 'pagbank_account_id', $account_id);
        update_user_meta($user_id, 'pagbank_account_validated', true);
        
        // Log the configuration
        Functions::log(sprintf(__('Admin configurou Account ID PagBank para vendedor %d: %s', 'pagbank-connect'), $user_id, substr($account_id, 0, 8) . '...' . substr($account_id, -4)), 'info');
    }

    /**
     * Add admin notice on Dokan pages about where to configure Account ID
     */
    public static function addAccountIdAdminNotice(): void
    {
        $screen = get_current_screen();
        
        // Only show on Dokan vendor pages
        if (!$screen || strpos($screen->id, 'dokan') === false) {
            return;
        }
        
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php _e('PagBank Connect - Account ID do Vendedor:', 'pagbank-connect'); ?></strong>
                <?php _e('Para configurar o Account ID PagBank de um vendedor, vá em', 'pagbank-connect'); ?>
                <a href="<?php echo admin_url('users.php'); ?>"><?php _e('Usuários', 'pagbank-connect'); ?></a>
                <?php _e('e edite o vendedor desejado.', 'pagbank-connect'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Release custody when order is completed
     *
     * @param int $order_id
     */
    public static function releaseCustodyOnOrderComplete(int $order_id): void
    {
        DokanSplitManager::releaseCustody($order_id);
    }
}
