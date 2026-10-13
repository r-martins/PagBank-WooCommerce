<?php

namespace RM_PagBank\Connect\Payments;

use Exception;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\TaxId;
use RM_PagBank\Partner\Capabilities;
use RM_PagBank\Partner\PartnerDetector;

/**
 * AJAX proxies for Vindi 3DS via API PB v1 (classic + Blocks checkout).
 */
class VindiThreeDs
{
    public static function register(): void
    {
        $actions = [
            'rm_pagbank_vindi_payment_profile' => 'ajaxPaymentProfile',
            'rm_pagbank_vindi_3ds_setup' => 'ajaxSetup',
            'rm_pagbank_vindi_3ds_enroll' => 'ajaxEnroll',
            'rm_pagbank_vindi_3ds_validate' => 'ajaxValidate',
        ];
        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, [self::class, $method]);
            add_action('wp_ajax_nopriv_' . $action, [self::class, $method]);
        }
        add_action('woocommerce_api_rm_pagbank_vindi_3ds_return', [self::class, 'challengeReturn']);
    }

    public static function challengeReturn(): void
    {
        $txid = isset($_GET['TransactionId'])
            ? sanitize_text_field(wp_unslash($_GET['TransactionId']))
            : (isset($_REQUEST['TransactionId']) ? sanitize_text_field(wp_unslash($_REQUEST['TransactionId'])) : '');
        header('Content-Type: text/html; charset=utf-8');
        $safe = esc_js($txid);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>3DS</title></head><body>';
        echo '<script>';
        echo 'try{';
        echo 'if(window.parent&&window.parent!==window){';
        echo 'window.parent.postMessage({source:"rm-pagbank-vindi-3ds",TransactionId:"' . $safe . '"},"*");';
        echo '}';
        echo '}catch(e){}';
        echo '</script>';
        echo '<p>Autenticação concluída. Você pode fechar esta janela.</p>';
        echo '</body></html>';
        exit;
    }

    public static function ajaxPaymentProfile(): void
    {
        self::assertNonce();
        self::assertVindi3dsAvailable();
        $body = self::jsonBody();
        $token = (string) ($body['gateway_token'] ?? '');
        $company = (string) ($body['payment_company_code'] ?? 'mastercard');
        $checkout = is_array($body['checkout'] ?? null) ? $body['checkout'] : [];
        if ($token === '') {
            wp_send_json_error(['message' => __('Token do cartão ausente.', 'pagbank-connect')], 400);
        }
        try {
            $api = new Api();
            $payload = [
                'gateway_token' => $token,
                'payment_company_code' => $company,
                'payment_method_code' => 'credit_card',
                'customer' => self::buildCustomerFromCheckout($checkout),
            ];
            $resp = $api->post('payment_profiles', $payload);
            $id = $resp['payment_profile']['id'] ?? ($resp['id'] ?? null);
            if (!$id) {
                wp_send_json_error(['message' => __('Não foi possível criar o perfil de pagamento.', 'pagbank-connect'), 'response' => $resp], 502);
            }
            wp_send_json_success([
                'payment_profile_id' => (int) $id,
                'customer_id' => (int) ($resp['customer_id'] ?? $resp['payment_profile']['customer_id'] ?? 0),
                'payment_profile' => $resp['payment_profile'] ?? $resp,
            ]);
        } catch (Exception $e) {
            Functions::log('Vindi 3DS payment_profile: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => $e->getMessage()], 502);
        }
    }

    public static function ajaxSetup(): void
    {
        self::assertNonce();
        self::assertVindi3dsAvailable();
        $body = self::jsonBody();
        $profileId = (int) ($body['payment_profile_id'] ?? 0);
        if ($profileId <= 0) {
            wp_send_json_error(['message' => __('payment_profile_id inválido.', 'pagbank-connect')], 400);
        }
        try {
            $api = new Api();
            // UML Vindi + orientação comercial: setup 3DS usa o método credit_card_3ds (não credit_card).
            $resp = $api->post('three-d-secure/sessions/setup', [
                'payment_profile_id' => $profileId,
                'payment_method_code' => 'credit_card_3ds',
            ]);
            $setup = $resp['setup'] ?? $resp;
            if (empty($setup['session_id']) || empty($setup['access_token'])) {
                wp_send_json_error(['message' => __('Resposta de setup 3DS inválida.', 'pagbank-connect'), 'response' => $resp], 502);
            }
            wp_send_json_success(['setup' => $setup]);
        } catch (Exception $e) {
            Functions::log('Vindi 3DS setup: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => $e->getMessage()], 502);
        }
    }

    public static function ajaxEnroll(): void
    {
        self::assertNonce();
        self::assertVindi3dsAvailable();
        $body = self::jsonBody();
        $sessionId = (string) ($body['session_id'] ?? '');
        $amount = (int) ($body['amount'] ?? 0);
        $installments = max(1, (int) ($body['installments'] ?? 1));
        $device = is_array($body['device_info'] ?? null) ? $body['device_info'] : [];
        $checkout = is_array($body['checkout'] ?? null) ? $body['checkout'] : [];

        // Checkout (clássico e blocos) manda centavos. Abaixo de R$ 1,00 não há 3DS.
        if ($sessionId === '' || $amount < 100) {
            wp_send_json_error(['message' => __('Dados de enroll incompletos.', 'pagbank-connect')], 400);
        }

        $payload = self::buildEnrollPayload($sessionId, $amount, $installments, $device, $checkout);
        try {
            Functions::log(
                'Vindi 3DS enroll amount: ' . $payload['amount'] . ' reais (checkout ' . $amount . ' centavos)',
                'debug'
            );
            $api = new Api();
            $resp = $api->post('three-d-secure/sessions/enroll', $payload);
            $enroll = $resp['enroll'] ?? $resp;
            // Some upstream shapes nest twice or put fields at the root.
            if (is_array($enroll) && isset($enroll['enroll']) && is_array($enroll['enroll'])) {
                $enroll = $enroll['enroll'];
            }
            Functions::log('Vindi 3DS enroll response: ' . wp_json_encode($enroll), 'debug');
            wp_send_json_success(['enroll' => $enroll, 'raw' => $resp]);
        } catch (Exception $e) {
            Functions::log('Vindi 3DS enroll: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => $e->getMessage()], 502);
        }
    }

    public static function ajaxValidate(): void
    {
        self::assertNonce();
        self::assertVindi3dsAvailable();
        $body = self::jsonBody();
        $txid = (string) ($body['authentication_transaction_id'] ?? '');
        $cardType = (string) ($body['card_type'] ?? 'visa');
        if ($txid === '') {
            wp_send_json_error(['message' => __('TransactionId ausente.', 'pagbank-connect')], 400);
        }
        try {
            $api = new Api();
            $resp = $api->post('three-d-secure/sessions/validate', [
                'authentication_transaction_id' => $txid,
                'card_type' => $cardType,
            ]);
            $validate = $resp['validate'] ?? $resp;
            wp_send_json_success(['validate' => $validate, 'raw' => $resp]);
        } catch (Exception $e) {
            Functions::log('Vindi 3DS validate: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => $e->getMessage()], 502);
        }
    }

    /**
     * @param int $amountCents Total do checkout em centavos (Woo Blocks `cartTotal` já vem assim).
     * @param array<string, mixed> $device
     * @param array<string, mixed> $checkout
     */
    private static function buildEnrollPayload(
        string $sessionId,
        int $amountCents,
        int $installments,
        array $device,
        array $checkout
    ): array {
        $customer = self::buildCustomerFromCheckout($checkout);
        $name = (string) ($customer['name'] ?? 'Cliente');
        $email = (string) ($customer['email'] ?? '');
        $taxId = (string) ($customer['registry_code'] ?? '');
        $phone = (string) ($customer['mobile_phone'] ?? '');
        $street = trim((string) ($checkout['billing_address_1'] ?? ''));
        $number = (string) ($checkout['billing_number'] ?? 'S/N');
        $city = (string) ($checkout['billing_city'] ?? '');
        $state = (string) ($checkout['billing_state'] ?? '');
        $postcode = preg_replace('/\D+/', '', (string) ($checkout['billing_postcode'] ?? ''));

        $ip = isset($device['ip_address']) ? (string) $device['ip_address'] : self::clientIp();
        $ua = isset($device['user_agent']) ? (string) $device['user_agent'] : (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0');

        // Enroll Vindi usa reais, como o billing. 10000 centavos enviados crus viram R$ 10.000,00 no desafio.
        $amountReais = round($amountCents / 100, 2);

        return [
            'session_id' => $sessionId,
            'amount' => $amountReais,
            'currency' => 'BRL',
            'installments' => $installments,
            'return_url' => home_url('/?wc-api=rm_pagbank_vindi_3ds_return'),
            'client_reference_code' => 'wc_' . time(),
            'device_info' => [
                'ip_address' => $ip,
                'user_agent' => $ua,
                'screen_height' => (int) ($device['screen_height'] ?? 1080),
                'screen_width' => (int) ($device['screen_width'] ?? 1920),
                'color_depth' => (int) ($device['color_depth'] ?? 24),
                'timezone_offset' => (int) ($device['timezone_offset'] ?? -180),
                'language' => (string) ($device['language'] ?? 'pt-BR'),
                'java_enabled' => !empty($device['java_enabled']),
            ],
            'billing_address' => [
                'name' => $name,
                'email' => $email !== '' ? $email : 'cliente@exemplo.test',
                'street' => $street !== '' ? $street . ($number !== '' ? ', ' . $number : '') : 'Nao informado',
                'city' => $city !== '' ? $city : 'Sao Paulo',
                'state' => $state !== '' ? $state : 'SP',
                'postal_code' => $postcode !== '' ? $postcode : '01310100',
                'country' => 'BR',
            ],
            'buyer_information' => [
                'registry_code' => $taxId !== '' ? $taxId : '00000000000',
                'mobile_phone' => $phone !== '' ? $phone : '5511999999999',
            ],
            'merchant_information' => [
                'merchant_name' => substr(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), 0, 40) ?: 'Loja',
                'url' => home_url('/'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $checkout
     * @return array{name: string, email: string, registry_code: string, mobile_phone: string}
     */
    private static function buildCustomerFromCheckout(array $checkout): array
    {
        // Defense in depth: never use PAN/CVV even if the client sent them by mistake.
        unset(
            $checkout['rm-pagbank-card-number'],
            $checkout['rm-pagbank-card-cvc'],
            $checkout['rm-pagbank-card-expiry'],
            $checkout['rm-pagbank-card-encrypted'],
            $checkout['card_number'],
            $checkout['card_cvv'],
            $checkout['card_expiration']
        );

        $name = trim((string) ($checkout['billing_first_name'] ?? '') . ' ' . (string) ($checkout['billing_last_name'] ?? ''));
        if ($name === '') {
            $name = (string) ($checkout['rm-pagbank-card-holder-name'] ?? 'Cliente');
        }
        $email = strtolower(trim((string) ($checkout['billing_email'] ?? '')));
        $taxId = TaxId::sanitizeForApi(
            (string) ($checkout['rm-pagbank-customer-document'] ?? $checkout['billing_cpf'] ?? $checkout['billing_cnpj'] ?? '')
        );
        $phone = preg_replace('/\D+/', '', (string) ($checkout['billing_phone'] ?? $checkout['billing_cellphone'] ?? ''));
        if ($phone !== '' && strpos($phone, '55') !== 0) {
            $phone = '55' . $phone;
        }

        return [
            'name' => $name !== '' ? $name : 'Cliente',
            'email' => $email,
            'registry_code' => $taxId,
            'mobile_phone' => $phone,
        ];
    }

    private static function assertNonce(): void
    {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'rm_pagbank_nonce')) {
            wp_send_json_error(['message' => __('Nonce inválido. Recarregue a página.', 'pagbank-connect')], 403);
        }
    }

    private static function assertVindi3dsAvailable(): void
    {
        $detector = PartnerDetector::fromSettings();
        if (!$detector->isVindi()) {
            wp_send_json_error(['message' => __('3DS Vindi indisponível para esta Connect Key.', 'pagbank-connect')], 400);
        }
        $caps = new Capabilities($detector);
        if (!$caps->has3ds() || !wc_string_to_bool(Params::getCcConfig('cc_3ds', 'no'))) {
            wp_send_json_error(['message' => __('3D Secure não está habilitado.', 'pagbank-connect')], 400);
        }
    }

    /** @return array<string, mixed> */
    private static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($_POST) ? wp_unslash($_POST) : [];
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', (string) $_SERVER[$key])[0];
                return trim($ip);
            }
        }
        return '127.0.0.1';
    }
}
