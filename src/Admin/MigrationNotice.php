<?php

namespace RM_PagBank\Admin;

use RM_PagBank\Partner\Capabilities;
use RM_PagBank\Partner\Cutoff;
use RM_PagBank\Partner\PartnerDetector;

/**
 * Admin CTAs for PagBank → Vindi migration. Never hard-blocks checkout.
 */
class MigrationNotice
{
    public static function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $detector = PartnerDetector::fromSettings();
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $onWc = $screen && isset($screen->id) && (
            strpos((string) $screen->id, 'woocommerce') !== false
            || strpos((string) $screen->id, 'pagbank') !== false
            || $screen->id === 'dashboard'
        );
        if (!$onWc) {
            return;
        }

        if ($detector->isPagBank() && $detector->getConnectKey() !== '') {
            self::renderPagBankCutoff();
        }

        if ($detector->isVindi() && get_option('rm_pagbank_recurring_needs_retoken') === 'yes') {
            self::renderRecurringRetoken();
        }

        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

        // Checkout PagBank (redirect) is PagBank-only — surface that on its settings tab.
        if ($detector->isVindi() && $section === 'rm-pagbank-redirect') {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__(
                'Checkout PagBank não está disponível com Connect Key Vindi. '
                . 'Este meio de pagamento (redirecionamento hospedado) é exclusivo do PagBank. '
                . 'Use PIX, boleto ou cartão na loja.',
                'pagbank-connect'
            );
            echo '</p></div>';
            return;
        }

        // Soft 3DS info only on credit-card settings (not on redirect / PIX / boleto).
        if ($detector->isVindi()
            && $section === 'rm-pagbank-cc'
            && ! (new Capabilities($detector))->has3ds()
        ) {
            echo '<div class="notice notice-info"><p>';
            echo esc_html__(
                '3D Secure (Vindi) não está ativo nesta conta. Abra um chamado com a Vindi/PB Integrações se precisar habilitar. Enquanto isso, o checkout segue sem 3DS.',
                'pagbank-connect'
            );
            echo '</p></div>';
        }
    }

    private static function renderPagBankCutoff(): void
    {
        $url = esc_url(Cutoff::MIGRATE_URL);
        $past = Cutoff::isPast();

        echo '<div class="notice notice-' . ($past ? 'error' : 'warning') . '"><p>';
        echo '<strong>' . esc_html__('PagBank - Ação Necessária', 'pagbank-connect') . '</strong><br/>';
        if ($past) {
            echo esc_html__(
                'O prazo para migrar sua Connect Key já passou. Migre agora para continuar vendendo com melhores taxas e mais aprovações.',
                'pagbank-connect'
            );
        } else {
            $days = (int) floor(Cutoff::secondsRemaining() / 86400);
            echo esc_html(
                sprintf(
                    /* translators: %d: approximate days remaining until cutoff */
                    __('Você tem cerca de %d dias para migrar sua Connect Key. Melhores taxas e mais aprovações.', 'pagbank-connect'),
                    $days
                )
            );
        }
        echo ' <a class="button button-primary" style="margin-left:8px" href="' . $url . '/?utm_source=woocommerce&utm_medium=notice&utm_campaign=migrate-now&utm_content=cutoff-notice" target="_blank" rel="noopener noreferrer">';
        echo esc_html__('Migrar agora', 'pagbank-connect');
        echo '</a></p></div>';
    }

    private static function renderRecurringRetoken(): void
    {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>' . esc_html__('Assinaturas / cartões salvos', 'pagbank-connect') . '</strong><br/>';
        echo esc_html__(
            'Tokens de cartão PagBank não funcionam na Vindi. Peça aos assinantes que atualizem o cartão na área da conta antes da próxima cobrança.',
            'pagbank-connect'
        );
        echo ' <a href="' . esc_url(admin_url('admin.php?page=rm-pagbank-subscriptions')) . '">';
        echo esc_html__('Ver assinaturas', 'pagbank-connect');
        echo '</a>';
        echo ' | <a href="' . esc_url(wp_nonce_url(admin_url('admin.php?rm_pagbank_dismiss_retoken=1'), 'rm_pagbank_dismiss_retoken')) . '">';
        echo esc_html__('Dispensar', 'pagbank-connect');
        echo '</a></p></div>';
    }

    public static function maybeDismiss(): void
    {
        if (!isset($_GET['rm_pagbank_dismiss_retoken']) || !current_user_can('manage_woocommerce')) {
            return;
        }
        check_admin_referer('rm_pagbank_dismiss_retoken');
        delete_option('rm_pagbank_recurring_needs_retoken');
        wp_safe_redirect(remove_query_arg(['rm_pagbank_dismiss_retoken', '_wpnonce']));
        exit;
    }

    /**
     * WooCommerce core shows a “Connect your store to WooCommerce.com” notice on every
     * wc-settings screen when the store isn’t linked and has any Woo.com plugins.
     * That notice competes with our migration CTA and is unrelated to PagBank Connect
     * (wordpress.org). Hide it only on our gateway sections.
     */
    public static function suppressWooConnectNoticeOnOurScreens(): void
    {
        if (!self::isOurWcSettingsSection()) {
            return;
        }

        // WP matches remove_action by exact callback string — use the same form as
        // PluginsHelper::init() (no leading "\"). Also remove from admin_notices at
        // priority 1 in case current_screen ran too early on some WC setups.
        $helper = \Automattic\WooCommerce\Admin\PluginsHelper::class;
        if (!class_exists($helper)) {
            return;
        }
        remove_action('admin_notices', [$helper, 'maybe_show_connect_notice_in_plugin_list']);
        remove_action('admin_enqueue_scripts', [$helper, 'maybe_enqueue_scripts_for_connect_notice']);
    }

    /** Whether the current request is a PagBank Connect checkout settings section. */
    private static function isOurWcSettingsSection(): bool
    {
        if (!is_admin()) {
            return false;
        }
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
        if ($page !== 'wc-settings' || $tab !== 'checkout') {
            return false;
        }
        return $section !== '' && strpos($section, 'rm-pagbank') === 0;
    }
}
