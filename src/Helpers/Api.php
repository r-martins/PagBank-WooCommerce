<?php

namespace RM_PagSeguro\Helpers;

use Exception;
use RM_PagSeguro\Connect;
use stdClass;
use WC_Order;
use WC_Payment_Gateways;

class Api
{
    // Saiba mais em https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/18183910009869
    const WS_URL = 'https://ws.ricardomartins.net.br/pspro/v7/connect/';

    /**
     * @var string
     */
    private $publicKey;

    public $connect_key;

    protected $is_sandbox;

    public function __construct()
    {
        $gateway = self::getPaymentGateway();
        $connect_key = $gateway->get_option('connect_key') ?? null;
        $is_sandbox = $gateway->get_option('is_sandbox', false) === 'yes';
        
        $this->set_connect_key($connect_key);
        $this->set_is_sandbox($is_sandbox);
    }

    public function get(string $endpoint, array $params = []): array
    {
        $params[]['isSandbox'] = $this->is_sandbox;
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
            // Aqui você pode tratar erros de decodificação JSON
            curl_close($curl);
            throw new \Exception('Resposta inválida da API: ' . $response);
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
        $params[]['isSandbox'] = $this->is_sandbox;
        $url = self::WS_URL . $endpoint;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->connect_key
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
            throw new \Exception('Erro na requisição: ' . $error_message);
        }
        
        $decoded_response = json_decode($response, true);
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE) {
            // Aqui você pode tratar erros de decodificação JSON
            curl_close($curl);
            Functions::log(
                'Resposta inválida da API: '.$response,
                'error',
                ['request' => $params, 'endpoint' => $endpoint]
            );
            throw new \Exception('Resposta inválida da API: ' . $response);
        }

        curl_close($curl);
        Functions::log('Response from '.$endpoint.': '.json_encode($decoded_response, JSON_PRETTY_PRINT), 'debug');
        return $decoded_response;
    }


    /**
     * @return bool
     */
    public function get_is_sandbox(): bool
    {
        return $this->is_sandbox;
    }

    /**
     * @param bool $is_sandbox
     */
    public function set_is_sandbox($is_sandbox): void
    {
        $this->is_sandbox = $is_sandbox;
    }

    /**
     * @return string
     */
    public function get_connect_key()
    {
        return $this->connect_key;
    }

    /**
     * @param string $connect_key
     */
    public function set_connect_key($connect_key): void
    {
        $this->connect_key = $connect_key;
    }
    
    public static function getPaymentGateway()
    {
        $gateways = WC_Payment_Gateways::instance();
        return $gateways->payment_gateways()[Connect::DOMAIN] ?? false;
    }
    
    public static function getOrderHash(WC_Order $order){
        return substr(wp_hash($order->get_id(), 'auth'), 0, 5);
    }
}