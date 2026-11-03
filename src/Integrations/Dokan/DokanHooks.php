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
        // Classic Dokan store settings (PHP form)
        add_action('dokan_settings_form_bottom', [__CLASS__, 'addVendorProfileFields'], 10, 2);
        add_action('dokan_store_profile_saved', [__CLASS__, 'saveVendorProfileFields'], 10, 2);

        // Dokan 5.3+ React store settings (schema API) — same Affiliate/Account ID + self-service links
        add_filter('dokan_get_vendor_settings_schema', [__CLASS__, 'injectVendorSettingsSchema'], 20, 2);
        add_action('dokan_after_saving_vendor_settings', [__CLASS__, 'saveVendorSettingsFromSchema'], 10, 3);
        
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
        if (is_object($current_user) && isset($current_user->ID)) {
            $user_id = $current_user->ID;
        } else {
            $user_id = (int) $current_user;
        }

        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        $metaKey = \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();
        $validatedKey = \RM_PagBank\Helpers\SplitIdentity::vendorValidatedMetaKey();
        $account_id = get_user_meta($user_id, $metaKey, true);
        $account_validated = get_user_meta($user_id, $validatedKey, true);
        $is_readonly = !empty($account_id);
        $label = \RM_PagBank\Helpers\SplitIdentity::fieldLabel();
        $registerUrl = \RM_PagBank\Helpers\SplitIdentity::selfServiceRegisterUrl();
        $lookupUrl = \RM_PagBank\Helpers\SplitIdentity::selfServiceLookupUrl();
        $fieldName = $isVindi ? 'vindi_affiliate_id' : 'pagbank_account_id';
        $pattern = $isVindi ? '\\d{1,12}' : 'ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}';
        $maxlength = $isVindi ? 12 : 41;
        $jsPattern = $isVindi
            ? '/^\\d{1,12}$/'
            : '/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/';

        ?>
        <div class="dokan-form-group">
            <label class="dokan-w3 dokan-control-label" for="<?php echo esc_attr($fieldName); ?>">
                <?php echo esc_html($label); ?>
                <?php if ($account_validated): ?>
                    <span style="color: green; margin-left: 5px;">✓ <?php _e('Validado', 'pagbank-connect'); ?></span>
                <?php endif; ?>
            </label>

            <div class="dokan-w5 dokan-text-left">
                <input
                    type="text"
                    class="dokan-form-control"
                    id="<?php echo esc_attr($fieldName); ?>"
                    name="<?php echo esc_attr($fieldName); ?>"
                    value="<?php echo esc_attr($account_id); ?>"
                    <?php echo $is_readonly ? 'readonly' : ''; ?>
                    pattern="<?php echo esc_attr($pattern); ?>"
                    maxlength="<?php echo (int) $maxlength; ?>"
                />
                <?php wp_nonce_field('dokan_pagbank_account', 'pagbank_account_nonce'); ?>

                <?php if (!$is_readonly): ?>
                    <p class="help-block" style="margin-top: 5px; color: #0073aa;">
                        <strong><?php _e('Primeira configuração:', 'pagbank-connect'); ?></strong>
                        <?php echo $isVindi
                            ? esc_html__('Você pode configurar seu Affiliate ID uma vez. Após configurado, apenas administradores poderão alterar.', 'pagbank-connect')
                            : esc_html__('Você pode configurar seu Account ID PagBank uma vez. Após configurado, apenas administradores poderão alterar.', 'pagbank-connect'); ?>
                    </p>
                <?php endif; ?>
                <div class="help-block" style="margin-top: 8px; padding: 10px; background: #f0f6fc; border-left: 3px solid #0073aa;">
                    <?php if ($isVindi): ?>
                        <p style="margin: 0 0 8px;">
                            <?php esc_html_e('Informe seu identificador de afiliado Vindi para receber sua parte das vendas. Cole o ID aqui com a conta ativa.', 'pagbank-connect'); ?>
                        </p>
                        <p style="margin: 0 0 4px;"><strong><?php esc_html_e('Links úteis:', 'pagbank-connect'); ?></strong></p>
                        <p style="margin: 0;">
                            <a class="dokan-btn dokan-btn-sm dokan-btn-theme" href="<?php echo esc_url($lookupUrl); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('Buscar meu Affiliate ID', 'pagbank-connect'); ?>
                            </a>
                            <a class="dokan-btn dokan-btn-sm" href="<?php echo esc_url($registerUrl); ?>" target="_blank" rel="noopener noreferrer" style="margin-left: 6px;">
                                <?php esc_html_e('Cadastrar afiliado Vindi', 'pagbank-connect'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p style="margin: 0 0 8px;">
                            <?php esc_html_e('Seu Account ID PagBank para receber pagamentos diretos via split. Formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx (41 caracteres)', 'pagbank-connect'); ?>
                        </p>
                        <p style="margin: 0;">
                            <a class="dokan-btn dokan-btn-sm dokan-btn-theme" href="<?php echo esc_url($registerUrl); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('Descobrir meu Account ID PagBank', 'pagbank-connect'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_js($fieldName); ?>').on('input', function() {
                var accountId = $(this).val();
                var pattern = <?php echo $jsPattern; ?>;
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
        if (!isset($_POST['pagbank_account_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['pagbank_account_nonce'], 'dokan_pagbank_account')) {
            return;
        }

        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        $metaKey = \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();
        $validatedKey = \RM_PagBank\Helpers\SplitIdentity::vendorValidatedMetaKey();
        $fieldName = $isVindi ? 'vindi_affiliate_id' : 'pagbank_account_id';

        $existing_account_id = get_user_meta($store_id, $metaKey, true);
        if (!empty($existing_account_id)) {
            return;
        }

        if (!isset($_POST[$fieldName]) || $_POST[$fieldName] === '') {
            return;
        }

        $account_id = sanitize_text_field(wp_unslash($_POST[$fieldName]));

        if (!\RM_PagBank\Helpers\SplitIdentity::validateFormat($account_id)) {
            $error_message = $isVindi
                ? __('Affiliate ID inválido. Use apenas números.', 'pagbank-connect')
                : __('Formato de Account ID inválido. Use o formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'pagbank-connect');
            if (defined('DOING_AJAX') && DOING_AJAX) {
                wp_send_json_error($error_message);
            }
            wc_add_notice($error_message, 'error');
            return;
        }

        if (!$isVindi) {
            $marketplace_account_id = get_option('woocommerce_rm-pagbank-integrations_marketplace_account_id');
            if ($marketplace_account_id && $account_id === $marketplace_account_id) {
                wc_add_notice(__('Este Account ID pertence ao marketplace. Use um Account ID diferente.', 'pagbank-connect'), 'error');
                return;
            }
        }

        if (self::isAccountIdAlreadyUsed($account_id, $store_id, $metaKey)) {
            wc_add_notice(
                $isVindi
                    ? __('Este Affiliate ID já está sendo usado por outro vendedor.', 'pagbank-connect')
                    : __('Este Account ID já está sendo usado por outro vendedor. Use um Account ID diferente.', 'pagbank-connect'),
                'error'
            );
            return;
        }

        $validation_result = \RM_PagBank\Helpers\SplitIdentity::validateRemote($account_id);
        if (is_wp_error($validation_result)) {
            $error_message = ($isVindi ? __('Erro ao validar Affiliate ID: ', 'pagbank-connect') : __('Erro ao validar Account ID: ', 'pagbank-connect'))
                . $validation_result->get_error_message();
            if (defined('DOING_AJAX') && DOING_AJAX) {
                wp_send_json_error($error_message);
            }
            wc_add_notice($error_message, 'error');
            return;
        }

        update_user_meta($store_id, $metaKey, $account_id);
        update_user_meta($store_id, $validatedKey, true);

        Functions::log(sprintf(
            __('Vendedor %d configurou %s: %s', 'pagbank-connect'),
            $store_id,
            $isVindi ? 'Affiliate ID' : 'Account ID PagBank',
            substr($account_id, 0, 8) . '...'
        ), 'info');

        self::notifyAdminAccountConfigured($store_id, $account_id);
        wc_add_notice(
            $isVindi
                ? __('Affiliate ID configurado com sucesso!', 'pagbank-connect')
                : __('Account ID PagBank configurado com sucesso!', 'pagbank-connect'),
            'success'
        );
    }

    /**
     * Dokan 5.3+ React Store settings: Affiliate ID (Vindi) / Account ID (PagBank) + self-service links.
     *
     * @param array $elements
     * @param int   $vendor_id
     * @return array
     */
    public static function injectVendorSettingsSchema(array $elements, int $vendor_id): array
    {
        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        $metaKey = \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();
        $accountId = (string) get_user_meta($vendor_id, $metaKey, true);
        $label = \RM_PagBank\Helpers\SplitIdentity::fieldLabel();
        $lookupUrl = \RM_PagBank\Helpers\SplitIdentity::selfServiceLookupUrl();
        $registerUrl = \RM_PagBank\Helpers\SplitIdentity::selfServiceRegisterUrl();

        // Dokan plugin-ui renders field/section `description` via wp.element RawHTML (anchors are first-class).
        if ($isVindi) {
            $description = sprintf(
                /* translators: 1: lookup URL 2: register URL */
                __(
                    'Informe o Affiliate ID numérico e cole aqui com a conta ativa.<br><br><strong>Links úteis:</strong><br>→ <a href="%1$s" target="_blank" rel="noopener noreferrer"><u>Buscar meu Affiliate ID</u></a><br>→ <a href="%2$s" target="_blank" rel="noopener noreferrer"><u>Cadastrar afiliado Vindi</u></a>',
                    'pagbank-connect'
                ),
                esc_url($lookupUrl),
                esc_url($registerUrl)
            );
            $placeholder = '123456';
            $sectionTitle = __('Receba via Vindi Pagamentos', 'pagbank-connect');
            $sectionDesc = __(
                'Informe seu identificador de afiliado Vindi para receber sua parte das vendas.',
                'pagbank-connect'
            );
        } else {
            $description = sprintf(
                /* translators: %s: authorize URL */
                __(
                    'Formato ACCO_….<br>→ <a href="%s" target="_blank" rel="noopener noreferrer"><u>Descobrir meu Account ID PagBank</u></a>',
                    'pagbank-connect'
                ),
                esc_url($registerUrl)
            );
            $placeholder = 'ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
            $sectionTitle = __('Recebimentos PagBank (Split)', 'pagbank-connect');
            $sectionDesc = __(
                'Configure seu Account ID PagBank para receber pagamentos via split.',
                'pagbank-connect'
            );
        }

        $fieldId = $isVindi ? 'pb_vindi_affiliate_id' : 'pb_pagbank_account_id';
        $locked = $accountId !== '';

        $elements[] = [
            'id'          => 'pb_split_receiver',
            'type'        => 'section',
            'subpage_id'  => 'store_settings',
            'tab_id'      => 'tab_general',
            'title'       => $sectionTitle,
            'description' => $sectionDesc,
            'collapsible' => true,
            'priority'    => 95,
        ];

        $elements[] = [
            'id'          => $fieldId,
            'type'        => 'field',
            'variant'     => 'text',
            'section_id'  => 'pb_split_receiver',
            'title'       => $label,
            'description' => $description,
            'placeholder' => $placeholder,
            'layout'      => 'full-width',
            'value'       => $accountId,
            'default'     => '',
            'non_meta'    => true, // stored in user meta, not dokan_profile_settings
            'required'    => false,
            'disabled'    => $locked,
            // Surface format/remote errors in the React Store UI (was silent before).
            'validation_func' => static function ($value, $all_values, $vendor_id) {
                return self::validateVendorSplitIdForSave((string) $value, (int) $vendor_id);
            },
        ];

        return $elements;
    }

    /**
     * Validate a new Affiliate/Account ID for the vendor React store settings save.
     * Returns true or an error message string (Dokan REST field contract).
     *
     * @return true|string
     */
    public static function validateVendorSplitIdForSave(string $accountId, int $vendor_id)
    {
        $accountId = sanitize_text_field($accountId);
        $metaKey = \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();
        $existing = (string) get_user_meta($vendor_id, $metaKey, true);

        if ($accountId === '' || $accountId === $existing) {
            return true;
        }

        // Vendors may set once; admins change via user profile.
        if ($existing !== '') {
            return __('Após configurado, apenas administradores podem alterar este ID.', 'pagbank-connect');
        }

        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        if (!\RM_PagBank\Helpers\SplitIdentity::validateFormat($accountId)) {
            return $isVindi
                ? __('Affiliate ID inválido. Use apenas números.', 'pagbank-connect')
                : __('Formato de Account ID inválido.', 'pagbank-connect');
        }

        if (!$isVindi) {
            $marketplace_account_id = get_option('woocommerce_rm-pagbank-integrations_marketplace_account_id');
            if ($marketplace_account_id && $accountId === $marketplace_account_id) {
                return __('Este Account ID pertence ao marketplace. Use um Account ID diferente.', 'pagbank-connect');
            }
        }

        if (self::isAccountIdAlreadyUsed($accountId, $vendor_id, $metaKey)) {
            return $isVindi
                ? __('Este Affiliate ID já está sendo usado por outro vendedor.', 'pagbank-connect')
                : __('Este Account ID já está sendo usado por outro vendedor.', 'pagbank-connect');
        }

        $validation = \RM_PagBank\Helpers\SplitIdentity::validateRemote($accountId);
        if (is_wp_error($validation)) {
            return $validation->get_error_message();
        }

        return true;
    }

    /**
     * Persist Affiliate/Account ID after Dokan React store settings save.
     *
     * @param int   $vendor_id
     * @param array $sanitized
     * @param array $fields_by_id
     */
    public static function saveVendorSettingsFromSchema(int $vendor_id, array $sanitized, array $fields_by_id): void
    {
        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        $fieldId = $isVindi ? 'pb_vindi_affiliate_id' : 'pb_pagbank_account_id';
        if (!array_key_exists($fieldId, $sanitized)) {
            return;
        }

        $metaKey = \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();
        $validatedKey = \RM_PagBank\Helpers\SplitIdentity::vendorValidatedMetaKey();
        $existing = (string) get_user_meta($vendor_id, $metaKey, true);
        $accountId = sanitize_text_field((string) $sanitized[$fieldId]);

        if ($accountId === '' || $accountId === $existing) {
            return;
        }

        // validation_func already ran on the REST path; re-check before write.
        $check = self::validateVendorSplitIdForSave($accountId, $vendor_id);
        if ($check !== true) {
            Functions::log(sprintf(
                'Dokan React settings: não salvou %s do vendedor %d: %s',
                $isVindi ? 'Affiliate ID' : 'Account ID',
                $vendor_id,
                $check
            ), 'warning');
            return;
        }

        update_user_meta($vendor_id, $metaKey, $accountId);
        update_user_meta($vendor_id, $validatedKey, true);
        self::notifyAdminAccountConfigured($vendor_id, $accountId);

        Functions::log(sprintf(
            __('Vendedor %d configurou %s via Dokan settings: %s', 'pagbank-connect'),
            $vendor_id,
            $isVindi ? 'Affiliate ID' : 'Account ID PagBank',
            substr($accountId, 0, 8) . '...'
        ), 'info');
    }

    /**
     * Check if Account ID is already used by another vendor
     *
     * @param string $account_id
     * @param int $exclude_user_id User ID to exclude from check
     * @return bool
     */
    protected static function isAccountIdAlreadyUsed(string $account_id, int $exclude_user_id = 0, ?string $meta_key = null): bool
    {
        global $wpdb;

        $meta_key = $meta_key ?: \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();

        $query = $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
            WHERE meta_key = %s
            AND meta_value = %s
            AND user_id != %d
            LIMIT 1",
            $meta_key,
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
        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        $metaKey = \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();
        $validatedKey = \RM_PagBank\Helpers\SplitIdentity::vendorValidatedMetaKey();
        $account_id = get_user_meta($user_id, $metaKey, true);
        $account_validated = get_user_meta($user_id, $validatedKey, true);
        $fieldName = $isVindi ? 'vindi_affiliate_id' : 'pagbank_account_id';
        $label = \RM_PagBank\Helpers\SplitIdentity::fieldLabel();
        $registerUrl = \RM_PagBank\Helpers\SplitIdentity::selfServiceRegisterUrl();
        $lookupUrl = \RM_PagBank\Helpers\SplitIdentity::selfServiceLookupUrl();

        ?>
        <tr>
            <th>
                <label for="<?php echo esc_attr($fieldName); ?>">
                    <?php echo esc_html($label); ?>
                    <?php if ($account_validated): ?>
                        <span style="color: green; margin-left: 5px;">✓ <?php _e('Validado', 'pagbank-connect'); ?></span>
                    <?php endif; ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    class="regular-text"
                    id="<?php echo esc_attr($fieldName); ?>"
                    name="<?php echo esc_attr($fieldName); ?>"
                    value="<?php echo esc_attr($account_id); ?>"
                    <?php if ($isVindi): ?>
                        pattern="\d{1,12}"
                        maxlength="12"
                    <?php else: ?>
                        pattern="ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}"
                        maxlength="41"
                    <?php endif; ?>
                />
                <?php if (empty($account_id)): ?>
                    <p class="description" style="color: #0073aa;">
                        <strong><?php _e('Primeira configuração:', 'pagbank-connect'); ?></strong>
                        <?php echo $isVindi
                            ? esc_html__('Configure o Affiliate ID do vendedor (busca ou cadastro self-service).', 'pagbank-connect')
                            : esc_html__('Configure o Account ID PagBank do vendedor. O vendedor também pode configurar uma vez no painel dele.', 'pagbank-connect'); ?>
                    </p>
                <?php endif; ?>

                <p class="description">
                    <?php if ($isVindi): ?>
                        <a href="<?php echo esc_url($lookupUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Buscar Affiliate ID (e-mail/CPF/CNPJ)', 'pagbank-connect'); ?>
                        </a>
                        —
                        <a href="<?php echo esc_url($registerUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Cadastrar afiliado', 'pagbank-connect'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url($registerUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Clique aqui para descobrir qual é o seu Account ID', 'pagbank-connect'); ?>
                        </a>
                    <?php endif; ?>
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
        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        $metaKey = \RM_PagBank\Helpers\SplitIdentity::vendorMetaKey();
        $validatedKey = \RM_PagBank\Helpers\SplitIdentity::vendorValidatedMetaKey();
        $fieldName = $isVindi ? 'vindi_affiliate_id' : 'pagbank_account_id';

        if (!isset($_POST[$fieldName])) {
            return;
        }

        $account_id = sanitize_text_field(wp_unslash($_POST[$fieldName]));

        if ($account_id === '') {
            delete_user_meta($user_id, $metaKey);
            delete_user_meta($user_id, $validatedKey);
            return;
        }

        if (!\RM_PagBank\Helpers\SplitIdentity::validateFormat($account_id)) {
            add_settings_error($fieldName, 'invalid_format', $isVindi
                ? __('Affiliate ID inválido.', 'pagbank-connect')
                : __('Formato de Account ID inválido.', 'pagbank-connect'));
            return;
        }

        if (!$isVindi) {
            $marketplace_account_id = get_option('woocommerce_rm-pagbank-integrations_marketplace_account_id');
            if ($marketplace_account_id && $account_id === $marketplace_account_id) {
                add_settings_error($fieldName, 'marketplace_id', __('Este Account ID pertence ao marketplace.', 'pagbank-connect'));
                return;
            }
        }

        if (self::isAccountIdAlreadyUsed($account_id, $user_id, $metaKey)) {
            add_settings_error($fieldName, 'already_used', __('Este ID já está sendo usado por outro vendedor.', 'pagbank-connect'));
            return;
        }

        $validation_result = \RM_PagBank\Helpers\SplitIdentity::validateRemote($account_id);
        if (is_wp_error($validation_result)) {
            add_settings_error(
                $fieldName,
                'validation_failed',
                ($isVindi ? __('Erro ao validar Affiliate ID: ', 'pagbank-connect') : __('Erro ao validar Account ID: ', 'pagbank-connect'))
                . $validation_result->get_error_message()
            );
            return;
        }

        update_user_meta($user_id, $metaKey, $account_id);
        update_user_meta($user_id, $validatedKey, true);

        Functions::log(sprintf(
            __('Admin configurou %s para vendedor %d: %s', 'pagbank-connect'),
            $isVindi ? 'Affiliate ID' : 'Account ID PagBank',
            $user_id,
            substr($account_id, 0, 8) . '...'
        ), 'info');
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
        
        $isVindi = \RM_PagBank\Helpers\SplitIdentity::isVindi();
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php echo $isVindi
                    ? esc_html__('PB Integrações — Affiliate ID do vendedor:', 'pagbank-connect')
                    : esc_html__('PB Integrações — Account ID do vendedor:', 'pagbank-connect'); ?></strong>
                <?php echo $isVindi
                    ? esc_html__('O vendedor configura sozinho em Dokan → Configurações da loja (busca/cadastro self-service). Admin também pode editar em', 'pagbank-connect')
                    : esc_html__('O vendedor configura sozinho em Dokan → Configurações da loja. Admin também pode editar em', 'pagbank-connect'); ?>
                <a href="<?php echo esc_url(admin_url('users.php')); ?>"><?php esc_html_e('Usuários', 'pagbank-connect'); ?></a>.
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
        // Vindi has no custody release API equivalent to PagBank SPLI_…
        if (\RM_PagBank\Helpers\SplitIdentity::isVindi()) {
            return;
        }
        DokanSplitManager::releaseCustody($order_id);
    }
}
