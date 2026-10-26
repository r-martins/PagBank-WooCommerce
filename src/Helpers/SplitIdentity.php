<?php

namespace RM_PagBank\Helpers;

use RM_PagBank\Partner\Capabilities;
use RM_PagBank\Partner\PartnerDetector;

/**
 * Partner-aware split identity: PagBank ACCO_… vs Vindi affiliate_id.
 */
class SplitIdentity
{
    public const META_PAGBANK = 'pagbank_account_id';
    public const META_PAGBANK_VALIDATED = 'pagbank_account_validated';
    public const META_VINDI = 'vindi_affiliate_id';
    public const META_VINDI_VALIDATED = 'vindi_affiliate_validated';
    /**
     * Stored as array `{url, key_fp}` (legacy: plain URL string without fingerprint).
     */
    public const OPTION_INVITE_URL = 'woocommerce_rm-pagbank_vindi_affiliate_invite_url';

    public static function detector(): PartnerDetector
    {
        return PartnerDetector::fromSettings();
    }

    /** Fingerprint of the Connect Key that owns the cached invite. */
    public static function connectKeyFingerprint(?string $connectKey = null): string
    {
        if ($connectKey === null || $connectKey === '') {
            $connectKey = (new Api())->getConnectKey();
        }
        return hash('sha256', trim((string) $connectKey));
    }

    /** Drop cached self-service invite (e.g. after Connect Key change). */
    public static function clearInviteCache(): void
    {
        delete_option(self::OPTION_INVITE_URL);
    }

    public static function isVindi(): bool
    {
        return self::detector()->isVindi();
    }

    public static function vendorMetaKey(): string
    {
        return self::isVindi() ? self::META_VINDI : self::META_PAGBANK;
    }

    public static function vendorValidatedMetaKey(): string
    {
        return self::isVindi() ? self::META_VINDI_VALIDATED : self::META_PAGBANK_VALIDATED;
    }

    /**
     * Split receiver id for a Dokan vendor (or null).
     */
    public static function getVendorReceiverId(int $vendor_id): ?string
    {
        $user = get_userdata($vendor_id);
        if ($user && in_array('administrator', (array) $user->roles, true)) {
            if (self::isVindi()) {
                // Marketplace is the Connect Key owner — no affiliate id required.
                return 'marketplace';
            }
            $gateway_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
            $marketplace_account_id = $gateway_settings['marketplace_account_id'] ?? '';
            return $marketplace_account_id !== '' ? (string) $marketplace_account_id : null;
        }

        $id = get_user_meta($vendor_id, self::vendorMetaKey(), true);
        return $id !== '' && $id !== null ? (string) $id : null;
    }

    public static function validateFormat(string $id): bool
    {
        if (self::isVindi()) {
            return (bool) preg_match('/^\d{1,12}$/', $id);
        }
        return (bool) preg_match(
            '/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/',
            $id
        );
    }

    /**
     * Remote validation for Vindi affiliate (active + enabled). PagBank stays format-only.
     *
     * @return true|\WP_Error
     */
    public static function validateRemote(string $id)
    {
        if (!self::validateFormat($id)) {
            return new \WP_Error(
                'invalid_format',
                self::isVindi()
                    ? __('Affiliate ID inválido. Use apenas números.', 'pagbank-connect')
                    : __('Formato de Account ID inválido.', 'pagbank-connect')
            );
        }

        if (!self::isVindi()) {
            return Functions::validateAccountId($id);
        }

        try {
            $api = new Api();
            $resp = $api->get('affiliates/' . (int) $id, [], 0);
            if (empty($resp['ready_for_split'])) {
                $status = (string) ($resp['status'] ?? 'unknown');
                return new \WP_Error(
                    'affiliate_not_ready',
                    sprintf(
                        /* translators: %s: Vindi affiliate status */
                        __('Afiliado não está pronto para split (status: %s). Conclua a validação em portal.vindi.com.br.', 'pagbank-connect'),
                        $status
                    )
                );
            }
            return true;
        } catch (\Exception $e) {
            return new \WP_Error('affiliate_lookup_failed', $e->getMessage());
        }
    }

    /**
     * Create or reuse a self-service invite URL (Connect Key stays on the server).
     * Cache is bound to the current Connect Key fingerprint — sandbox↔prod or key
     * rotation forces a new invite so vendors never hit the wrong merchant.
     *
     * @return array{url: string, token?: string, expires_at?: string}|\WP_Error
     */
    public static function getOrCreateInviteUrl(bool $forceNew = false)
    {
        if (!self::isVindi()) {
            return new \WP_Error('not_vindi', 'Invite is only for Vindi');
        }

        $api = new Api();
        $fp = self::connectKeyFingerprint($api->getConnectKey());
        $stored = get_option(self::OPTION_INVITE_URL, null);

        if (!$forceNew && is_array($stored)) {
            $storedUrl = (string) ($stored['url'] ?? '');
            $storedFp = (string) ($stored['key_fp'] ?? '');
            if ($storedUrl !== '' && $storedFp !== '' && hash_equals($storedFp, $fp)) {
                return ['url' => $storedUrl];
            }
        }
        // Legacy plain-string cache (no key_fp) or mismatched key → regenerate.

        try {
            $resp = $api->post('affiliates/invites', []);
            $url = (string) ($resp['url'] ?? '');
            if ($url === '') {
                return new \WP_Error('invite_empty', __('Não foi possível gerar o link de cadastro.', 'pagbank-connect'));
            }
            update_option(self::OPTION_INVITE_URL, [
                'url' => $url,
                'key_fp' => $fp,
            ], false);
            return [
                'url' => $url,
                'token' => $resp['token'] ?? null,
                'expires_at' => $resp['expires_at'] ?? null,
            ];
        } catch (\Exception $e) {
            return new \WP_Error('invite_failed', $e->getMessage());
        }
    }

    public static function selfServiceRegisterUrl(): string
    {
        return self::selfServiceUrl('cadastrar');
    }

    /** Lookup tab (email/CPF/CNPJ) — default mode on the page. */
    public static function selfServiceLookupUrl(): string
    {
        return self::selfServiceUrl('buscar');
    }

    private static function selfServiceUrl(string $mode): string
    {
        if (!self::isVindi()) {
            return 'https://ws.pbintegracoes.com/pspro/v7/connect/account-id/authorize';
        }
        $invite = self::getOrCreateInviteUrl(false);
        if (is_wp_error($invite)) {
            $base = 'https://pbintegracoes.com/connect/vindi-afiliado';
            return $base . '?mode=' . rawurlencode($mode);
        }
        $url = (string) $invite['url'];
        $sep = str_contains($url, '?') ? '&' : '?';
        // Avoid duplicating mode=
        if (preg_match('/[?&]mode=/', $url)) {
            return preg_replace('/([?&])mode=[^&]*/', '$1mode=' . rawurlencode($mode), $url) ?: $url;
        }
        return $url . $sep . 'mode=' . rawurlencode($mode);
    }

    public static function fieldLabel(): string
    {
        return self::isVindi()
            ? __('Vindi Affiliate ID', 'pagbank-connect')
            : __('PagBank Account ID', 'pagbank-connect');
    }
}
