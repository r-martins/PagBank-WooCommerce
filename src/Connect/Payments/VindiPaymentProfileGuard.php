<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\OrderMeta;
use WC_Order;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;

/**
 * PB-1107: bind Vindi payment_profile_id to the current WC session / owned Woo token.
 *
 * Never trust rm-pagbank-payment-profile-id / rm-pagbank-vindi-customer-id from POST alone.
 */
class VindiPaymentProfileGuard
{
    private const SESSION_PROFILE = 'rm_pagbank_vindi_bound_profile_id';
    private const SESSION_CUSTOMER = 'rm_pagbank_vindi_bound_customer_id';
    private const SESSION_TS = 'rm_pagbank_vindi_bound_profile_ts';

    /** Session binding lifetime (seconds). */
    private const TTL = 3600;

    public static function bind(int $profileId, int $customerId = 0): void
    {
        if ($profileId <= 0 || !self::session()) {
            return;
        }
        self::ensureSessionCookie();
        WC()->session->set(self::SESSION_PROFILE, $profileId);
        WC()->session->set(self::SESSION_CUSTOMER, max(0, $customerId));
        WC()->session->set(self::SESSION_TS, time());
    }

    public static function clear(): void
    {
        if (!self::session()) {
            return;
        }
        WC()->session->set(self::SESSION_PROFILE, null);
        WC()->session->set(self::SESSION_CUSTOMER, null);
        WC()->session->set(self::SESSION_TS, null);
    }

    public static function isAllowed(int $profileId): bool
    {
        if ($profileId <= 0) {
            return false;
        }
        return self::isSessionBound($profileId) || self::ownedByCurrentUser($profileId);
    }

    public static function isSessionBound(int $profileId): bool
    {
        if ($profileId <= 0 || !self::session()) {
            return false;
        }
        $bound = (int) WC()->session->get(self::SESSION_PROFILE);
        $ts = (int) WC()->session->get(self::SESSION_TS);
        if ($bound <= 0 || $bound !== $profileId) {
            return false;
        }
        if ($ts > 0 && (time() - $ts) > self::TTL) {
            self::clear();
            return false;
        }
        return true;
    }

    public static function boundCustomerId(): int
    {
        if (!self::session()) {
            return 0;
        }
        $profile = (int) WC()->session->get(self::SESSION_PROFILE);
        $ts = (int) WC()->session->get(self::SESSION_TS);
        if ($profile <= 0 || ($ts > 0 && (time() - $ts) > self::TTL)) {
            return 0;
        }
        return (int) WC()->session->get(self::SESSION_CUSTOMER);
    }

    public static function ownedByCurrentUser(int $profileId): bool
    {
        return self::tokenForProfile($profileId) instanceof WC_Payment_Token_CC;
    }

    /**
     * Apply POST profile/customer only when bound to this session or owned Woo token.
     *
     * @throws \Exception When a non-empty untrusted profile id is posted.
     */
    public static function applyPostedIdsToOrder(WC_Order $order): void
    {
        $postedProfile = isset($_POST['rm-pagbank-payment-profile-id'])
            ? (int) wc_clean(wp_unslash($_POST['rm-pagbank-payment-profile-id']))
            : 0;
        $postedCustomer = isset($_POST['rm-pagbank-vindi-customer-id'])
            ? (int) wc_clean(wp_unslash($_POST['rm-pagbank-vindi-customer-id']))
            : 0;

        if ($postedProfile <= 0) {
            OrderMeta::delete($order, '_payment_profile_id');
            OrderMeta::delete($order, '_vindi_customer_id');
            return;
        }

        if (!self::isAllowed($postedProfile)) {
            Functions::log(
                'Rejected untrusted payment_profile_id=' . $postedProfile,
                'warning',
                [
                    'order_id' => $order->get_id(),
                    'user_id' => get_current_user_id(),
                ]
            );
            throw new \Exception(
                __('Perfil de pagamento inválido. Recarregue a página e tente novamente.', 'pagbank-connect')
            );
        }

        OrderMeta::set($order, '_payment_profile_id', $postedProfile, true);

        $customerId = 0;
        if (self::isSessionBound($postedProfile)) {
            $customerId = self::boundCustomerId();
            // Same request that created the profile may still echo customer_id; only then trust POST.
            if ($customerId <= 0 && $postedCustomer > 0) {
                $customerId = $postedCustomer;
            }
        } else {
            $token = self::tokenForProfile($postedProfile);
            if ($token instanceof WC_Payment_Token_CC) {
                $customerId = (int) $token->get_meta('vindi_customer_id');
            }
        }

        if ($customerId > 0) {
            OrderMeta::set($order, '_vindi_customer_id', $customerId, true);
        } else {
            OrderMeta::delete($order, '_vindi_customer_id');
        }
    }

    /**
     * Reject untrusted profile id for AJAX 3DS setup / change-card reuse.
     */
    public static function assertAllowedOrFail(int $profileId): void
    {
        if ($profileId <= 0 || self::isAllowed($profileId)) {
            return;
        }
        Functions::log(
            'Rejected untrusted payment_profile_id=' . $profileId . ' (ajax/change-card)',
            'warning',
            ['user_id' => get_current_user_id()]
        );
        throw new \Exception(
            __('Perfil de pagamento inválido. Recarregue a página e tente novamente.', 'pagbank-connect')
        );
    }

    private static function tokenForProfile(int $profileId): ?WC_Payment_Token_CC
    {
        $userId = get_current_user_id();
        if ($userId <= 0 || $profileId <= 0) {
            return null;
        }
        $tokens = WC_Payment_Tokens::get_customer_tokens($userId, 'rm-pagbank-cc');
        if (!is_array($tokens)) {
            $tokens = WC_Payment_Tokens::get_customer_tokens($userId);
        }
        if (!is_array($tokens)) {
            return null;
        }
        foreach ($tokens as $token) {
            if (!$token instanceof WC_Payment_Token_CC) {
                continue;
            }
            if ((int) $token->get_meta('vindi_payment_profile_id') === $profileId) {
                return $token;
            }
            // Numeric token value may itself be the Vindi profile id.
            if (ctype_digit((string) $token->get_token()) && (int) $token->get_token() === $profileId) {
                return $token;
            }
        }
        return null;
    }

    private static function session(): bool
    {
        return function_exists('WC') && WC()->session !== null;
    }

    private static function ensureSessionCookie(): void
    {
        if (!self::session()) {
            return;
        }
        if (method_exists(WC()->session, 'has_session') && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }
}
