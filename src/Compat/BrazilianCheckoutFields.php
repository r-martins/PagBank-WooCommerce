<?php

namespace RM_PagBank\Compat;

use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\TaxId;

/**
 * CNPJ alfanumérico compatibility for Brazilian Market on WooCommerce
 * (woocommerce-extra-checkout-fields-for-brazil).
 */
class BrazilianCheckoutFields
{
    private const PLUGIN_FILE = 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php';

    private static bool $legacyCnpjValidationEnabled = false;

    private static bool $settingsFilterAdded = false;

    public static function init(): void
    {
        if (! self::isEnabled()) {
            return;
        }

        add_filter('wcbcf_billing_fields', [self::class, 'filterBillingFields']);
        add_action('woocommerce_checkout_process', [self::class, 'normalizePostedCnpj'], 5);
        add_action('woocommerce_checkout_process', [self::class, 'prepareLegacyValidationBypass'], 9);
        add_action('woocommerce_checkout_process', [self::class, 'validateCheckoutCnpj'], 11);
        add_action('woocommerce_save_account_details', [self::class, 'validateAccountCnpj'], 10);

        add_action('woocommerce_after_checkout_form', [self::class, 'enqueueScripts'], 20);
        add_action('woocommerce_after_edit_account_address_form', [self::class, 'enqueueScripts'], 20);
    }

    public static function isPluginActive(): bool
    {
        if (! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(self::PLUGIN_FILE);
    }

    public static function isEnabled(): bool
    {
        $enabled = Params::getIntegrationsConfig('wcbcf_alnum_cnpj_compat', '');

        // Migrate legacy value saved under general settings before Integrações tab.
        if ($enabled === '' && wc_string_to_bool(Params::getConfig('wcbcf_alnum_cnpj_compat', 'no'))) {
            $enabled = 'yes';
        }

        if (! wc_string_to_bool($enabled)) {
            return false;
        }

        return self::isPluginActive();
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function filterBillingFields(array $fields): array
    {
        if (! isset($fields['billing_cnpj'])) {
            return $fields;
        }

        $fields['billing_cnpj']['type'] = 'text';
        $fields['billing_cnpj']['custom_attributes'] = array_merge(
            $fields['billing_cnpj']['custom_attributes'] ?? [],
            [
                'autocapitalize' => 'characters',
                'maxlength'      => '18',
                'inputmode'      => 'text',
            ]
        );

        return $fields;
    }

    public static function normalizePostedCnpj(): void
    {
        if (empty($_POST['billing_cnpj'])) {
            return;
        }

        $_POST['billing_cnpj'] = TaxId::formatForDisplay(
            wp_unslash((string) $_POST['billing_cnpj'])
        );
    }

    public static function prepareLegacyValidationBypass(): void
    {
        $settings = get_option('wcbcf_settings', []);
        self::$legacyCnpjValidationEnabled = is_array($settings) && isset($settings['validate_cnpj']);

        if (! self::$settingsFilterAdded) {
            add_filter('option_wcbcf_settings', [self::class, 'stripLegacyCnpjValidationSetting']);
            self::$settingsFilterAdded = true;
        }
    }

    /**
     * @param mixed $settings
     * @return mixed
     */
    public static function stripLegacyCnpjValidationSetting($settings)
    {
        if (! is_array($settings)) {
            return $settings;
        }

        unset($settings['validate_cnpj']);

        return $settings;
    }

    public static function validateCheckoutCnpj(): void
    {
        if (! self::$legacyCnpjValidationEnabled || ! self::isCnpjFieldInScope()) {
            return;
        }

        if (empty($_POST['billing_cnpj'])) {
            return;
        }

        $cnpj = wp_unslash((string) $_POST['billing_cnpj']);

        if (TaxId::isValidCnpj($cnpj)) {
            return;
        }

        wc_add_notice(
            sprintf(
                '<strong>%s</strong> %s.',
                esc_html__('CNPJ', 'pagbank-connect'),
                esc_html__('is not valid', 'pagbank-connect')
            ),
            'error'
        );
    }

    public static function validateAccountCnpj(int $userId): void
    {
        if (empty($_POST['billing_cnpj'])) {
            return;
        }

        $settings = get_option('wcbcf_settings', []);
        if (! is_array($settings) || ! isset($settings['validate_cnpj'])) {
            return;
        }

        $cnpj = wp_unslash((string) $_POST['billing_cnpj']);

        if (TaxId::isValidCnpj($cnpj)) {
            return;
        }

        wc_add_notice(
            sprintf(
                '<strong>%s</strong> %s.',
                esc_html__('CNPJ', 'pagbank-connect'),
                esc_html__('is not valid', 'pagbank-connect')
            ),
            'error'
        );
    }

    public static function enqueueScripts(): void
    {
        if (Functions::isCheckoutBlocks()) {
            return;
        }

        if (! wp_script_is('woocommerce-extra-checkout-fields-for-brazil-front', 'enqueued')) {
            return;
        }

        wp_enqueue_script(
            'pagseguro-connect-tax-id',
            plugins_url('public/js/tax-id-legacy.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            [],
            WC_PAGSEGURO_CONNECT_VERSION,
            true
        );

        wp_enqueue_script(
            'pagseguro-connect-wcbcf-cnpj-compat',
            plugins_url('public/js/wcbcf-cnpj-compat.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            ['jquery', 'woocommerce-extra-checkout-fields-for-brazil-front', 'pagseguro-connect-tax-id'],
            WC_PAGSEGURO_CONNECT_VERSION,
            true
        );
    }

    private static function isCnpjFieldInScope(): bool
    {
        $settings = get_option('wcbcf_settings', []);
        if (! is_array($settings)) {
            return false;
        }

        $personType = (int) ($settings['person_type'] ?? 0);
        if (0 === $personType) {
            return false;
        }

        $onlyBrazil = isset($settings['only_brazil']);
        $countryIsNotBr = isset($_POST['billing_country'])
            && 'BR' !== sanitize_text_field(wp_unslash((string) $_POST['billing_country']));

        if ($onlyBrazil && $countryIsNotBr) {
            return false;
        }

        $billingPersonType = isset($_POST['billing_persontype'])
            ? (int) wp_unslash((string) $_POST['billing_persontype'])
            : 0;

        return (1 === $personType && 2 === $billingPersonType) || 3 === $personType;
    }
}
