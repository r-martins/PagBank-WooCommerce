<?php

namespace RM_PagBank\Helpers;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Object\Amount;
use RM_PagBank\Partner\PartnerDetector;
use WC_Order;
use WC_Payment_Gateways;
use WP_Error;
use RM_PagBank\Helpers\OrderMeta;

/**
 * Class Api
 * Helper methods to consume the API
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Helpers
 */
class Api
{
    // Saiba mais em https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/18183910009869
    // Override via filter `rm_pagbank_ws_url` / `rm_pagbank_api_url` or constants RM_PAGBANK_WS_URL / RM_PAGBANK_API_URL
    const WS_URL = 'https://ws.pbintegracoes.com/pspro/v7/connect/';
    const API_V1_URL = 'https://api.pbintegracoes.com/v1/';
    const EF_URL = 'https://ws.pbintegracoes.com/pspro/v7/ef/';

    public string $connect_key;

    protected bool $is_sandbox;

    /** @var PartnerDetector|null */
    protected $partnerDetector;

    public function __construct()
    {
        $gateway = self::getPaymentGateway();
        $connect_key = $gateway->get_option('connect_key') ?? null;

        $this->setConnectKey($connect_key ?? '');
    }

    public static function getWsUrl(): string
    {
        if (defined('RM_PAGBANK_WS_URL') && RM_PAGBANK_WS_URL) {
            $url = RM_PAGBANK_WS_URL;
        } else {
            $url = self::WS_URL;
        }
        return (string) apply_filters('rm_pagbank_ws_url', $url);
    }

    public static function getApiV1Url(): string
    {
        if (defined('RM_PAGBANK_API_URL') && RM_PAGBANK_API_URL) {
            $url = RM_PAGBANK_API_URL;
        } else {
            $url = self::API_V1_URL;
        }
        return (string) apply_filters('rm_pagbank_api_url', $url);
    }

    public function partner(): PartnerDetector
    {
        if (!$this->partnerDetector) {
            $this->partnerDetector = PartnerDetector::fromKey($this->connect_key ?? '');
        }
        return $this->partnerDetector;
    }

    /**
     * Map legacy v7-relative paths to API v1 when using a Vindi Connect Key.
     */
    public function mapEndpoint(string $endpoint): string
    {
        if (!$this->partner()->isVindi()) {
            return $endpoint;
        }
        $map = [
            'ws/orders' => 'orders',
            'ws/charges/fees/calculate' => 'charges/fees/calculate',
            'ws/public-keys' => 'connectInfo',
            'connectInfo' => 'connectInfo',
            'tokenize/config' => 'tokenize/config',
            'payment_methods' => 'payment_methods',
            'payment_profiles' => 'payment_profiles',
            'three-d-secure/sessions/setup' => 'three-d-secure/sessions/setup',
            'three-d-secure/sessions/enroll' => 'three-d-secure/sessions/enroll',
            'three-d-secure/sessions/validate' => 'three-d-secure/sessions/validate',
        ];
        if (isset($map[$endpoint])) {
            return $map[$endpoint];
        }
        if (preg_match('#^ws/orders/(.+)$#', $endpoint, $m)) {
            return 'orders/' . $m[1];
        }
        if (preg_match('#^ws/charges/(.+)/cancel$#', $endpoint, $m)) {
            return 'charges/' . $m[1] . '/cancel';
        }
        // Do not map PagBank-only token routes for Vindi (would 404 as tokens/cards).
        if ($endpoint === 'ws/tokens/cards' || $endpoint === 'tokens/cards') {
            throw new Exception(
                'Salvar cartão Vindi usa payment_profiles, não tokens/cards. Atualize o plugin.'
            );
        }
        // Strip ws/ prefix as last resort
        if (strpos($endpoint, 'ws/') === 0) {
            return substr($endpoint, 3);
        }
        return $endpoint;
    }

    protected function baseUrlForRequest(): string
    {
        return $this->partner()->isVindi() ? self::getApiV1Url() : self::getWsUrl();
    }

    /** @return array<string, mixed> */
    protected function requestOptions(array $extra = []): array
    {
        $opts = array_merge([
            'timeout' => 60,
            'user-agent' => 'WooCommerce / PagBank Integracoes',
        ], $extra);
        // Constant is safe in wp-config.php (add_filter is not — WP is not loaded yet).
        $ssl = true;
        if (defined('RM_PAGBANK_SSLVERIFY')) {
            $ssl = (bool) RM_PAGBANK_SSLVERIFY;
        }
        $ssl = (bool) apply_filters('rm_pagbank_sslverify', $ssl, $this->baseUrlForRequest());
        if ($ssl === false) {
            $opts['sslverify'] = false;
        }
        return $opts;
    }

    /**
     * @throws Exception
     */
    public function get(string $endpoint, array $params = [], int $cacheMin = 0): array
    {
        $endpoint = $this->mapEndpoint($endpoint);
        if (!$this->partner()->isVindi()) {
            $params['isSandbox'] = $this->is_sandbox ? '1' : '0';
        }
        $url = $this->baseUrlForRequest() . $endpoint . '?' . http_build_query($params);

        $header = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->connect_key,
			'Referer' => get_site_url(),
        ];

        $transientKey = 'cache_' . md5($url . serialize($header));
        // Match post(): only serve cache when caller asked for TTL (cacheMin > 0).
        // Otherwise force-refresh callers (e.g. connectInfo) keep hitting a stale transient.
        if ($cacheMin > 0) {
            $cached = get_transient($transientKey);
            if ($cached !== false) {
                Functions::log(
                    'Response from '.$endpoint.' (cached): '.wp_json_encode($cached, JSON_PRETTY_PRINT),
                    'debug'
                );
                return $cached;
            }
        }

        Functions::log('GET Request to '.$endpoint.' with params: '.wp_json_encode($params, JSON_PRETTY_PRINT), 'debug');

        $resp = wp_remote_get($url, $this->requestOptions([
            'headers' => $header,
        ]));

		if (is_wp_error($resp)) {
            Functions::log(
                'Erro na requisição GET: ' . $resp->get_error_message(),
                'error',
                ['endpoint' => $endpoint, 'params' => $params]
            );
			throw new Exception('Erro na requisição: ' . esc_attr($resp->get_error_message()));
		}

		$response = wp_remote_retrieve_body($resp);
        $responseCode = (int) wp_remote_retrieve_response_code($resp);
        $responseMessage = wp_remote_retrieve_response_message($resp);
		if (empty($response)) {
            Functions::log(
                'Resposta inválida da API (GET): '.$responseCode . ' - ' . $responseMessage,
                'error',
                ['endpoint' => $endpoint]
            );
			throw new Exception('Resposta inválida da API: ""');
		};

        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            Functions::log(
                'Resposta inválida da API (GET): '.$response,
                'error',
                ['endpoint' => $endpoint]
            );
            throw $this->buildInvalidApiResponseException($response, $responseCode);
        }

        Functions::log(
            'Response from '.$endpoint.' (' . $responseCode . '-' . $responseMessage . '): '
            . wp_json_encode($decoded_response, JSON_PRETTY_PRINT),
            'debug'
        );

        if ($cacheMin > 0) {
            set_transient($transientKey, $decoded_response, $cacheMin * 60);
        }
        
        return $decoded_response;
    }

    /**
     * @throws Exception
     */
    public function getEf(string $endpoint, array $params = [], int $cacheMin = 0): array
    {
        $params['isSandbox'] = $this->is_sandbox ? '1': '0';
        $url = self::EF_URL . $endpoint .'?' .http_build_query($params);

        $header = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->connect_key,
            'Referer' => get_site_url(),
        ];

        $transientKey = 'cache_' . md5($url . serialize($header));
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            return $cached;
        }

        $resp = wp_remote_get($url, [
            'headers' => $header,
            'timeout' => 60,
            'user-agent' => 'WooCommerce / PagBank Integracoes',
        ]);

        if (is_wp_error($resp)) {
            throw new Exception('Erro na requisição: ' . esc_attr($resp->get_error_message()));
        }

        $response = wp_remote_retrieve_body($resp);
        if (empty($response)) {
            throw new Exception('Resposta inválida da API: ""');
        };

        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            throw $this->buildInvalidApiResponseException($response, (int) wp_remote_retrieve_response_code($resp));
        }

        if ($cacheMin > 0) {
            set_transient($transientKey, $decoded_response, $cacheMin * 60);
        }

        return $decoded_response;
    }

    /**
     * @param string $endpoint
     * @param array  $params
     * @param int    $cacheMin cache response time for this request in minutes                    
     *
     * @return mixed
     * @throws Exception
     */
    public function post(string $endpoint, array $params = [], int $cacheMin = 0)
    {
        $endpoint = $this->mapEndpoint($endpoint);
		$isSandbox = (!$this->partner()->isVindi() && $this->is_sandbox) ? '?isSandbox=1' : '';
        // Vindi: always request upstream debug so WC logs / support see partner request+response.
        if ($this->partner()->isVindi()
            && ($endpoint === 'orders' || $endpoint === 'ws/orders')) {
            $isSandbox = ($isSandbox === '' ? '?' : $isSandbox . '&') . 'debug=1';
        }
        $url = $this->baseUrlForRequest() . $endpoint . $isSandbox;
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->connect_key,
            'Platform' => 'WooCommerce',
            'Extra-Version' => WC()->version,
            'Platform-Version' => get_bloginfo('version'),
            'Module-Version' => WC_PAGSEGURO_CONNECT_VERSION,
			'Referer' => get_site_url(),
        ];
        
        $transientKey = 'cache_' . md5($url);

        if ($cacheMin > 0){
            $cached = get_transient($transientKey);
            if ($cached !== false){
                Functions::log(
                    'Response from '.$endpoint.' (cached): '.wp_json_encode($cached, JSON_PRETTY_PRINT),
                    'debug'
                );
                return $cached;
            }
        }

		Functions::log('POST Request to '.$endpoint . $isSandbox .' with params: '.wp_json_encode($params, JSON_PRETTY_PRINT), 'debug');

		$response = wp_remote_post($url, $this->requestOptions([
			'headers' => $headers,
			'body' => wp_json_encode($params),
		]));


		if (is_wp_error($response)){
            Functions::log(
                'Erro na requisição: ' . $response->get_error_message(),
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw new Exception('Erro na requisição: ' . $response->get_error_message());
        }

        $responseBody = wp_remote_retrieve_body($response);
        $responseCode = wp_remote_retrieve_response_code($response);
        $responseMessage = wp_remote_retrieve_response_message($response);

        if (empty($responseBody) && $responseCode >= 400) {
            Functions::log(
                'Resposta inválida da API: '.$responseCode . ' - ' . $responseMessage,
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw new Exception('Resposta inválida da API: ' . $responseCode . ' - ' . $responseMessage);
        }

        $response = $responseBody;
        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            $logBody = $response === '' ? __('"Resposta vazia"', 'pagbank-connect') : $response;
            Functions::log(
                'Resposta inválida da API: '.$logBody,
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw $this->buildInvalidApiResponseException($response, (int) $responseCode);
        }

        Functions::log('Response from '.$endpoint.' (' . $responseCode . '-' . $responseMessage . '): ' . wp_json_encode($decoded_response, JSON_PRETTY_PRINT), 'debug');

        if ($responseCode >= 400 || $this->isApiErrorPayload($decoded_response)) {
            $msg = $this->formatApiErrorMessage($decoded_response, (int) $responseCode, $responseMessage);
            $upstreamStatus = null;
            if (is_array($decoded_response) && isset($decoded_response['upstream']['status'])) {
                $upstreamStatus = (int) $decoded_response['upstream']['status'];
            }
            $logLine = 'API error on '.$endpoint.' (HTTP '.$responseCode;
            if ($upstreamStatus !== null) {
                $logLine .= ', upstream '.$upstreamStatus;
            }
            $logLine .= '): '.$msg;
            Functions::log(
                $logLine,
                'error',
                [
                    'request' => $params,
                    'endpoint' => $endpoint,
                    'http_status' => (int) $responseCode,
                    'upstream_status' => $upstreamStatus,
                    'response' => $decoded_response,
                ]
            );
            throw new Exception($msg, (int) $responseCode >= 400 ? (int) $responseCode : 400);
        }

        if ($cacheMin > 0){
            set_transient($transientKey, $decoded_response, $cacheMin * 60);
        }
        return $decoded_response;
    }

    /**
     * @param mixed $decoded
     */
    protected function isApiErrorPayload($decoded): bool
    {
        if (!is_array($decoded)) {
            return false;
        }
        if (!empty($decoded['error_messages']) && is_array($decoded['error_messages'])) {
            return true;
        }
        // API PB v1 / partner style: { "error": "UPSTREAM_ERROR", "message": "..." }
        if (!empty($decoded['error']) && is_string($decoded['error'])) {
            return true;
        }
        return false;
    }

    /**
     * @param mixed $decoded
     */
    protected function formatApiErrorMessage($decoded, int $responseCode, string $responseMessage): string
    {
        if (!is_array($decoded)) {
            return 'Erro na API: ' . $responseCode . ' - ' . $responseMessage;
        }
        if (!empty($decoded['error_messages']) && is_array($decoded['error_messages'])) {
            $parts = array_map(static function ($error) {
                if (!is_array($error)) {
                    return (string) $error;
                }
                $code = $error['code'] ?? '';
                $desc = $error['description'] ?? ($error['message'] ?? '');
                return trim($code . ' - ' . $desc, ' -');
            }, $decoded['error_messages']);
            return implode('; ', array_filter($parts)) ?: 'Erro na API';
        }
        $msg = (string) ($decoded['message'] ?? $responseMessage);
        if (!empty($decoded['upstream']['body']['errors']) && is_array($decoded['upstream']['body']['errors'])) {
            $upstream = [];
            foreach ($decoded['upstream']['body']['errors'] as $e) {
                if (!is_array($e)) {
                    $upstream[] = (string) $e;
                    continue;
                }
                $param = (string) ($e['parameter'] ?? '');
                $detail = $this->flattenUpstreamErrorMessage($e['message'] ?? '');
                $upstream[] = trim($param . ': ' . $detail, ': ');
            }
            $upstream = array_filter($upstream);
            if ($upstream !== []) {
                // Prefer upstream fragment when the top-level message already embeds it (avoid "msg (msg)").
                $joined = implode('; ', $upstream);
                if ($msg === '' || stripos($msg, $joined) === false) {
                    $msg .= ($msg !== '' ? ' (' : '') . $joined . ($msg !== '' ? ')' : '');
                }
            }
        }
        $msg = $this->humanizeCustomerFacingApiError($msg);
        return $msg !== '' ? $msg : ('Erro na API: ' . $responseCode);
    }

    /**
     * Translate partner field jargon (registry_code, etc.) into labels customers understand.
     */
    protected function humanizeCustomerFacingApiError(string $msg): string
    {
        if ($msg === '') {
            return $msg;
        }

        $haystack = function_exists('mb_strtolower') ? mb_strtolower($msg) : strtolower($msg);
        $mentionsTaxId = strpos($haystack, 'registry_code') !== false
            || strpos($haystack, 'tax_id') !== false;

        if ($mentionsTaxId) {
            $blank = strpos($haystack, 'não pode ficar em branco') !== false
                || strpos($haystack, 'nao pode ficar em branco') !== false
                || strpos($haystack, 'cannot be blank') !== false
                || strpos($haystack, 'must not be blank') !== false
                || strpos($haystack, 'cannot be empty') !== false
                || strpos($haystack, 'obrigatório') !== false
                || strpos($haystack, 'obrigatorio') !== false;
            if ($blank) {
                return __(
                    'Informe o CPF ou CNPJ para continuar o pagamento.',
                    'pagbank-connect'
                );
            }
            $invalid = strpos($haystack, 'inválid') !== false
                || strpos($haystack, 'invalid') !== false;
            if ($invalid) {
                return __('CPF ou CNPJ inválido.', 'pagbank-connect');
            }
            $msg = (string) preg_replace('/\bregistry_code\b/i', 'CPF/CNPJ', $msg);
            $msg = (string) preg_replace('/\btax_id\b/i', 'CPF/CNPJ', $msg);
        }

        return $msg;
    }

    /**
     * Vindi may nest messages as { "code": ["já está em uso"] } instead of a string.
     *
     * @param mixed $message
     */
    protected function flattenUpstreamErrorMessage($message): string
    {
        if (is_string($message) || is_numeric($message)) {
            return (string) $message;
        }
        if (!is_array($message)) {
            return '';
        }
        $parts = [];
        foreach ($message as $key => $value) {
            $flat = $this->flattenUpstreamErrorMessage($value);
            if ($flat === '') {
                continue;
            }
            if (is_string($key) && !is_int($key)) {
                $parts[] = $key . ': ' . $flat;
            } else {
                $parts[] = $flat;
            }
        }
        return implode(', ', $parts);
    }

    /**
     * Customer-facing message for non-JSON API bodies (e.g. Cloudflare HTML error pages).
     * Full raw body must remain in the log only — never in checkout notices.
     *
     * @throws Exception
     */
    protected function buildInvalidApiResponseException(string $response, int $responseCode = 0): Exception
    {
        $looksLikeHtml = (bool) preg_match('/^\s*</', $response)
            || stripos($response, '<html') !== false
            || stripos($response, 'cloudflare') !== false
            || stripos($response, '<!DOCTYPE') !== false;

        if ($looksLikeHtml) {
            $httpCode = $responseCode;
            if (!$httpCode && preg_match('/\bError code (5\d{2})\b/i', $response, $matches)) {
                $httpCode = (int) $matches[1];
            } elseif (!$httpCode && preg_match('/\b(5\d{2}):\s*Web server/i', $response, $matches)) {
                $httpCode = (int) $matches[1];
            }

            $message = __(
                'Não foi possível concluir o pagamento devido a uma falha temporária de comunicação. Tente novamente em alguns minutos.',
                'pagbank-connect'
            );
            if ($httpCode >= 500) {
                $message .= ' (HTTP ' . $httpCode . ')';
            }

            return new Exception($message);
        }

        $snippet = trim(wp_strip_all_tags($response));
        if ($snippet === '') {
            $snippet = __('"Resposta vazia"', 'pagbank-connect');
        } else {
            $snippet = function_exists('mb_substr') ? mb_substr($snippet, 0, 200) : substr($snippet, 0, 200);
        }

        return new Exception(
            __('Resposta inválida da API:', 'pagbank-connect') . ' ' . esc_attr($snippet)
        );
    }

    /**
     * @param string $endpoint (only suffix after EF_URL, e.g., 'boxing')
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function postEf(string $endpoint, array $params = [], int $cacheMin = 0): array
    {
        $isSandbox = $this->is_sandbox ? '?isSandbox=1' : '';
        $url = self::EF_URL . ltrim($endpoint, '/') . $isSandbox;

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->connect_key,
            'Platform' => 'WooCommerce',
            'Extra-Version' => WC()->version,
            'Platform-Version' => get_bloginfo('version'),
            'Module-Version' => WC_PAGSEGURO_CONNECT_VERSION,
            'Referer' => get_site_url(),
        ];

        $transientKey = 'cache_' . md5($url . serialize($params) . serialize($headers));
        if ($cacheMin > 0) {
            $cached = get_transient($transientKey);
            if ($cached !== false) {
                Functions::log('[EnvioFácil][Api] Response from ' . $endpoint . ' (cached): ' . wp_json_encode($cached, JSON_PRETTY_PRINT), 'debug');
                return $cached;
            }
        }

        Functions::log('[EnvioFácil][Api] POST ' . $endpoint . ' payload: ' . wp_json_encode($params, JSON_PRETTY_PRINT), 'debug');

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => wp_json_encode($params),
            'timeout' => 60,
            'user-agent' => 'WooCommerce / PagBank Integracoes',
        ]);

        if (is_wp_error($response)) {
            Functions::log('[EnvioFácil][Api] Erro na requisição EF: ' . $response->get_error_message(), 'error', [
                'endpoint' => $endpoint,
                'payload' => $params,
            ]);
            throw new Exception('Erro na requisição EF: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        if ($body === '' || !is_string($body)) {
            Functions::log('[EnvioFácil][Api] Resposta vazia EF', 'error', [
                'endpoint' => $endpoint,
                'http_code' => $code,
            ]);
            throw new Exception('Resposta vazia EF: ' . $code);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            Functions::log('[EnvioFácil][Api] JSON inválido EF', 'error', [
                'endpoint' => $endpoint,
                'body' => $body,
                'http_code' => $code,
            ]);
            throw $this->buildInvalidApiResponseException($body, (int) $code);
        }

        Functions::log('[EnvioFácil][Api] Resposta EF ' . $endpoint . ': ' . wp_json_encode($decoded, JSON_PRETTY_PRINT), 'debug');
        
        if ($cacheMin > 0) {
            set_transient($transientKey, $decoded, $cacheMin * 60);
        }
        
        return $decoded;
    }

    /**
     * @param bool $forceRefresh Skip Api::get cache (refresh_connect_info / badge refresh).
     */
    public function getConnectInfo(bool $forceRefresh = false): array
    {
        try {
            return $this->get('connectInfo', [], $forceRefresh ? 0 : 60);
        } catch (Exception $e) {
            return [
                'error' => true,
                'status' => 'ERROR',
            ];
        }
    }

    /**
     * Returns the 3D Secure session string to be used in the JS or empty string if not available/fails
     *
     * @param bool $useCache Whether to reuse a cached session. Pass false to force a fresh session from PagBank.
     * @return string
     */
    public function get3DSession(bool $useCache = true): string
    {
        if ($this->partner()->isVindi()) {
            return '';
        }
        try {
            $resp = $this->post('ws-sdk/checkout-sdk/sessions', [], $useCache ? 5 : 0);
            if (isset($resp['session'])) {
                return $resp['session'];
            }
        } catch (Exception $e) {
            // shhh
        }

        return '';
    }

    /**
     * Checks if the credit card payment method is enabled and healthy (3d session is available or not required)
     * @return bool
     * @throws Exception
     */
    public function isCcEnabledAndHealthy(): bool
    {
        $isCcEnabled = $this->getPaymentGateway()->get_option('cc_enabled') === 'yes';
        if (!$isCcEnabled) {
            return false;
        }

        // Vindi: no PagBank 3DS session; availability is Connect Key + optional credit_card_3ds capability.
        if ($this->partner()->isVindi()) {
            return true;
        }

        $is3dEnabled = $this->getPaymentGateway()->get_option('cc_3ds') === 'yes';
        $threeDSession = $this->get3DSession();
        $canContinueWithNo3d = $this->getPaymentGateway()->get_option('cc_3ds_allow_continue') === 'yes';

        // PagBank: credit card enabled and 3d disabled, or 3d enabled with session / allow continue.
        return !$is3dEnabled || ($is3dEnabled && ($threeDSession || $canContinueWithNo3d));
    }


    /**
     * @return bool
	 * @noinspection PhpUnused
	 */
    public function getIsSandbox(): bool
    {
        return $this->is_sandbox;
    }

    /**
     * @param bool $is_sandbox
     */
    public function setIsSandbox(bool $is_sandbox): void
    {
        $this->is_sandbox = $is_sandbox;
    }

    /**
     * @return string
	 * @noinspection PhpUnused
	 */
    public function getConnectKey(): string
    {
        return $this->connect_key;
    }

    /**
     * @param string $connect_key
     */
    public function setConnectKey(string $connect_key): void
    {
        $this->connect_key = $connect_key;
        $this->partnerDetector = PartnerDetector::fromKey($connect_key);
		$this->setIsSandbox($this->partnerDetector->isSandbox());
    }

    /**
     * @return false|mixed
     */
    public static function getPaymentGateway()
    {
//        $gateways = WC_Payment_Gateways::instance();
        return new Connect\Gateway() ?? false;
    }

    /**
     * @param WC_Order $order
     *
     * @return false|string
     */
    public static function getOrderHash(WC_Order $order){
        return substr(wp_hash($order->get_id()), 0, 5);
    }

    /**
     * Get order total wether from cart, or from order-pay page
     * @return float
     */
    public static function getOrderTotal(): float
    {
        if ( ! WC()->cart ) {
            return 0;
        }
        
        $total = floatval(WC()->cart->get_total('edit'));
        if ( is_wc_endpoint_url('order-pay') )
        {
            global $wp;
            $orderId = (int)$wp->query_vars['order-pay'];
            $order = wc_get_order($orderId);

            if ($order) {
                $total = $order->get_total('edit');
            }
        }
        return $total;
    }
    
    public static function refund($orderId, $amount = null)
    {
        $order = wc_get_order( $orderId );
        if (!$order) {
            return new WP_Error( 'error', __('Pedido não encontrado para reembolso.', 'pagbank_connect' ));
        }

        $charge = OrderMeta::get($order, 'charge_id');
        if (!$charge) {
            $charge = OrderMeta::get($order, 'order_id');
        }

        $amountObj = new Amount();
        $amountObj->setValue(Params::convertToCents($amount));
        $helper = new Api();
        try {
            $endpoint = 'ws/charges/'.$charge.'/cancel';
            $response = $helper->post($endpoint, ['amount' => $amountObj]);
        } catch (Exception $e) {
            return new WP_Error(
                'error',
                __('Erro ao solicitar reembolso via API. Tente novamente mais tarde.', 'pagbank-connect')
                . ' ' . $e->getMessage()
            );
        }

        $rejection = self::refundRejectionDetail(is_array($response) ? $response : []);
        if ($rejection !== null) {
            $order->add_order_note(
                sprintf(
                    __('PB Integrações: Reembolso recusado. %s', 'pagbank-connect'),
                    $rejection
                )
            );
            return new WP_Error(
                'error',
                __('Reembolso recusado pelo adquirente.', 'pagbank-connect') . ' ' . $rejection
            );
        }

        if(isset($response['amount']['summary']) || ($response['status'] ?? '') === 'CANCELED') {
            $order->add_order_note(
                sprintf(
                    __('PB Integrações: Reembolso de %s solicitado com sucesso.', 'pagbank-connect'),
                    wc_price($amount)
                )
            );
            return true;
        }

        return new WP_Error(
            'error',
            __('Reembolso via API pode ter falhado. Veja a transação no painel.', 'pagbank-connect')
        );
    }

    /**
     * Vindi answers charges/cancel with top-level status CANCELED even when the
     * refund transaction was rejected and the charge is still paid.
     *
     * @param array<string, mixed> $response
     */
    public static function refundRejectionDetail(array $response): ?string
    {
        $charge = $response['refund']['charge'] ?? null;
        if (!is_array($charge)) {
            return null;
        }
        $tx = $charge['last_transaction'] ?? null;
        if (!is_array($tx)) {
            return null;
        }
        $type = strtolower((string) ($tx['transaction_type'] ?? ''));
        if ($type !== '' && $type !== 'refund') {
            return null;
        }
        $status = strtolower((string) ($tx['status'] ?? ''));
        if (!in_array($status, ['rejected', 'failure', 'failed', 'timeout'], true)) {
            return null;
        }
        $gateway = trim((string) ($tx['gateway_message'] ?? ''));
        $code = trim((string) ($tx['gateway_response_code'] ?? ''));
        if ($gateway !== '' && $code !== '') {
            return $gateway . ' (' . $code . ')';
        }
        if ($gateway !== '') {
            return $gateway;
        }
        if ($code !== '') {
            return $code;
        }
        return 'rejected';
    }

    /**
     * @param $pagBankOrderId
     *
     * @return array
     * @throws Exception
     */
    public static function getOrderData($pagBankOrderId){
        $api = new Api();
        $isCheckoutPagBank = substr($pagBankOrderId, 0, 4) === 'CHEC';
        if ($isCheckoutPagBank) {
            $pagBankOrderId = self::getFirstOrderFromCheckout($pagBankOrderId);
            if (is_array($pagBankOrderId)) {
                return [];
            }
        }
        
        return $api->get('ws/orders/' . $pagBankOrderId, [], 5);
    }

    /**
     * @param $checkoutId
     *
     * @return array|mixed
     * @throws Exception
     */
    public static function getFirstOrderFromCheckout($checkoutId)
    {
        $api = new Api();
        $data = $api->get('ws/checkouts/' . $checkoutId, [], 5);

        if (empty($data['orders']) || isset($data['error_messages'])) {
            return [];
        }
        
        return $data['orders'][0]['id'] ?? [];
    }
}
