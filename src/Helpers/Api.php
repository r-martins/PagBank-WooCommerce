<?php

namespace RM_PagBank\Helpers;

use Exception;
use RM_PagBank\Connect;
use WC_Order;
use WC_Payment_Gateways;

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
    const WS_URL = 'https://ws.ricardomartins.net.br/pspro/v7/connect/';

    public string $connect_key;

    protected bool $is_sandbox;

    public function __construct()
    {
        $gateway = self::getPaymentGateway();
        $connect_key = $gateway->get_option('connect_key') ?? null;

        $this->setConnectKey($connect_key);
    }

    /**
     * @throws Exception
     */
    public function get(string $endpoint, array $params = [], int $cacheMin = 0): array
    {
        $params['isSandbox'] = $this->is_sandbox ? '1': '0';
        $url = self::WS_URL . $endpoint .'?' .http_build_query($params);

        $header = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->connect_key,
			'Referer' => get_site_url(),
        ];

        $transientKey = 'cache_' . md5($url . serialize($header));
        $cached = get_transient($transientKey);
        if ($cached !== false){
            return $cached;
        }
        
        $resp = wp_remote_get($url, [ 'headers' => $header, 'timeout' => 60 ]);

		if (is_wp_error($resp)) {
			throw new Exception('Erro na requisição: ' . $resp->get_error_message());
		}

		$response = wp_remote_retrieve_body($resp);
		if (empty($response)) {
			throw new Exception('Resposta inválida da API: ' . $response);
		};

        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Resposta inválida da API: ' . $response);
        }

        if ($cacheMin > 0){
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
		$isSandbox = $this->is_sandbox ? '?isSandbox=1' : '';
        $url = self::WS_URL . $endpoint . $isSandbox;
        
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

		$response = wp_remote_post($url, [
			'headers' => $headers,
			'body' => wp_json_encode($params),
            'timeout' => 60,
		]);


		if (is_wp_error($response)){
            Functions::log(
                'Erro na requisição: ' . $response->get_error_message(),
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw new Exception('Erro na requisição: ' . $response->get_error_message());
        }

		$response = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            Functions::log(
                'Resposta inválida da API: '.$response,
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw new Exception('Resposta inválida da API: ' . $response);
        }

        Functions::log('Response from '.$endpoint.': ' . wp_json_encode($decoded_response, JSON_PRETTY_PRINT), 'debug');
        if ($cacheMin > 0){
            set_transient($transientKey, $decoded_response, $cacheMin * 60);
        }
        return $decoded_response;
    }

    /**
     * @throws Exception
     */
    public function get3DSession(): string
    {
        $resp = $this->post('ws-sdk/checkout-sdk/sessions', [], 20);
        
        if (isset($resp['session']))
        {
            return $resp['session'];
        }
        
        throw new Exception(__('Erro ao obter a sessão 3D Secure', 'pagbank-connect'));
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
		$this->setIsSandbox(strpos($connect_key, 'CONSANDBOX') !== false);
    }

    /**
     * @return false|mixed
     */
    public static function getPaymentGateway()
    {
        $gateways = WC_Payment_Gateways::instance();
        return $gateways->payment_gateways()[Connect::DOMAIN] ?? false;
    }

    /**
     * @param WC_Order $order
     *
     * @return false|string
     */
    public static function getOrderHash(WC_Order $order){
        return substr(wp_hash($order->get_id()), 0, 5);
    }
}
