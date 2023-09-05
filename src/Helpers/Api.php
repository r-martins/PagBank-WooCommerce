<?php

namespace RM_PagBank\Helpers;

use Exception;
use RM_PagBank\Connect;
use WC_Order;
use WC_Payment_Gateways;

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
    public function get(string $endpoint, array $params = []): array
    {
        $params['isSandbox'] = $this->is_sandbox ? '1': '0';
        $url = self::WS_URL . $endpoint .'?' .http_build_query($params);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->connect_key
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            curl_close($curl);
            throw new Exception('Resposta inválida da API: ' . $response);
        }

        curl_close($curl);
        return $decoded_response;
    }

    /**
     * @param string $endpoint
     * @param array  $params
     *
     * @return mixed
     * @throws Exception
     */
    public function post(string $endpoint, array $params = [])
    {
		$isSandbox = $this->is_sandbox ? '?isSandbox=1' : '';
        $url = self::WS_URL . $endpoint . $isSandbox;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->connect_key,
            'Platform: WooCommerce',
            'Extra-Version: ' . WC()->version,
            'Platform-Version: ' . get_bloginfo('version'),
            'Module-Version: ' . WC_PAGSEGURO_CONNECT_VERSION,
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        Functions::log('POST Request to '.$endpoint.' with params: '.json_encode($params, JSON_PRETTY_PRINT), 'debug');
        $response = curl_exec($curl);

        if (curl_errno($curl)){
            $error_message = curl_error($curl);
            curl_close($curl);
            Functions::log(
                'Erro na requisição: '.$error_message,
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw new Exception('Erro na requisição: ' . $error_message);
        }

        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            curl_close($curl);
            Functions::log(
                'Resposta inválida da API: '.$response,
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw new Exception('Resposta inválida da API: ' . $response);
        }

        curl_close($curl);
        Functions::log('Response from '.$endpoint.': '.json_encode($decoded_response, JSON_PRETTY_PRINT), 'debug');
        return $decoded_response;
    }


    /**
     * @return bool
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
        return substr(wp_hash($order->get_id(), 'auth'), 0, 5);
    }
}
