<?php
namespace RM_PagBank;

use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use WC_Product;
use WC_Shipping_Method;

/**
 * Class EnvioFacil
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank
 */
class EnvioFacil extends WC_Shipping_Method
{
	public $countries = ['BR'];

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 *
	 * @noinspection PhpUnusedParameterInspection*/
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'rm_enviofacil';
		$this->method_title       = __( 'PagBank Envio Fácil' );  // Title shown in admin
		$this->method_description = __( 'Use taxas diferenciadas com Correios e transportadoras em pedidos feitos com PagBank' ); // Description shown in admin

		$this->enabled            = $this->get_option('enabled');
		$this->title              = "PagBank Envio Fácil";
//		$this->supports           = [
//			'shipping-zones',
//			'instance-settings',
//		];

		$this->init();
		/** @noinspection PhpUnusedLocalVariableInspection */
		parent::__construct( $instance_id = 0 );
	}

	public function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Is this method available?
	 *
	 * @param array $package Package.
	 * @return bool
	 */
	public function is_available($package): bool
	{
		if ( ! isset($package['destination']['postcode']))
		{
			return false;
		}

		$connectKey = substr(Params::getConfig('connect_key'), 0, 7);
		if (!in_array($connectKey, ['CONPS14', 'CONPS30'])){
			return false;
		}

		return parent::is_available($package);
	}

	/**
	 * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
	 *
	 * @param array $package Package array.
	 */
	public function calculate_shipping($package = array()): array {
		$destinationPostcode = $package['destination']['postcode'];
		$destinationPostcode = preg_replace('/[^0-9]/', '', $destinationPostcode);

		$senderPostcode = $this->get_option('origin_postcode', get_option('woocommerce_store_postcode'));
		$senderPostcode = preg_replace('/[^0-9]/', '', $senderPostcode);

		$productValue = $package['contents_cost'];

		$dimensions = $this->getDimensionsAndWeight($package);

		$isValid = $this->validateDimensions($dimensions);

		if ( !$isValid || !$dimensions ) return [];

		//body
		$params = [
			'sender' => $senderPostcode,
			'receiver' => $destinationPostcode,
			'length' => $dimensions['length'],
			'height' => $dimensions['height'],
			'width' => $dimensions['width'],
			'weight' => $dimensions['weight'],
			'value' => max($productValue, 0.1)
		];
		$url = 'https://ws.ricardomartins.net.br/pspro/v7/ef/quote?' . http_build_query($params);
		$ret = wp_remote_get($url, [
			'headers' => [
				'Authorization' => 'Bearer ' . Params::getConfig('connect_key')
			],
			'timeout' => 10,
			'sslverify' => false,
			'httpversion' => '1.1'
		]);/*
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . Params::getConfig('connect_key')]
		]);*/

		if (is_wp_error($ret)) {
			return [];
		}
		$ret = wp_remote_retrieve_body($ret);
		$ret = json_decode($ret, true);

		if (isset($ret['error_messages'])) {
			Functions::log('Erro ao calcular o frete: ' . print_r($ret['error_messages'], true), 'debug');
			return [];
		}

		foreach ($ret as $provider) {
			if (!isset($provider['provider']) || !isset($provider['providerMethod'])
				|| !isset($provider['contractValue'])) {
				continue;
			}

			$rate = array(
				'id' => 'ef-' . $provider['provider'],
				'label' => $provider['provider'] . ' - ' . $provider['providerMethod'] . sprintf(__(' - %d dias úteis'), $provider['estimateDays']),
				'cost' => $provider['contractValue'],
				'calc_tax' => 'per_order'
			);

			if ( ! $rate['cost'] )
				continue;

			$this->add_rate( $rate );
		}
		return [];
	}

	/**
	 * Adds the method to the list of available payment methods
	 *
	 * @param $methods
	 *
	 * @return array
	 */
	public static function addMethod($methods): array
	{
		$methods['rm_enviofacil'] = 'RM_PagBank\EnvioFacil';
		return $methods;
	}

	/**
	 * Get a sum of the dimensions and weight of the products in the package
	 * @param $package
	 *
	 * @return int[]
	 */
	public function getDimensionsAndWeight($package): array
	{
		$return = [
			'length' => 0,
			'height' => 0,
			'width' => 0,
			'weight' => 0,
		];

		foreach ($package['contents'] as $content)
		{
			/** @var WC_Product $product */
			$product = $content['data'];

			$dimensions = $product->get_dimensions(false);
			//convert each dimension to float
			$dimensions = array_map('floatval', $dimensions);

			$weight = floatval($product->get_weight());
			 $return['length'] += $dimensions['length'] * $content['quantity'];
			 $return['height'] += $dimensions['height'] * $content['quantity'];
			 $return['width'] += $dimensions['width'] * $content['quantity'];
			 $return['weight'] += $weight * $content['quantity'];
		}

		return $return;

	}

	/**
	 * Validates the dimensions and weight of the package and logs errors if any
	 * @param $dimensions
	 *
	 * @return bool
	 */
	public function validateDimensions($dimensions): bool
	{
		if(($dimensions['length'] < 15 || $dimensions['length'] > 100)){
			Functions::log('Comprimento inválido: ' . $dimensions['length'] . '. Deve ser entre 15 e 100.', 'debug');
			return false;
		}
		if(($dimensions['height'] < 1 || $dimensions['height'] > 100)){
			Functions::log('Altura inválida: ' . $dimensions['height'] . '. Deve ser entre 1 e 100.', 'debug');
			return false;
		}
		if(($dimensions['width'] < 10 || $dimensions['width'] > 100)){
			Functions::log('Largura inválida: ' . $dimensions['width'] . '. Deve ser entre 10 e 100.', 'debug');
			return false;
		}

		if ($dimensions['weight'] > 10)
		{
			Functions::log('Peso inválido: ' . $dimensions['weight'] . '. Deve ser menor que 10.', 'debug');
			return false;
		}

		return true;
	}

	public function init_form_fields()
	{
		$this->form_fields = [
			'enabled'            => [
				'title'   => __( 'Habilitar', Connect::DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar', Connect::DOMAIN ),
				'default' => 'yes',
			],
			'origin_postcode'    => [
				'title'       => __( 'CEP de Origem', Connect::DOMAIN ),
				'type'        => 'text',
				'description' => __( 'CEP de onde suas mercadorias serão enviadas. '
					.'Se não informado, o CEP da loja será utilizado.', Connect::DOMAIN ),
				'desc_tip'    => true,
				'placeholder' => get_option('woocommerce_store_postcode', '00000-000'),
				'default'     => $this->getBasePostcode(),
			],
		];

	}

	/**
	 * Get base postcode.
	 *
	 * @since  3.5.1
	 * @return string
	 */
	protected function getBasePostcode(): string
	{
		// WooCommerce 3.1.1+.
		if ( method_exists( WC()->countries, 'get_base_postcode' ) ) {
			return WC()->countries->get_base_postcode();
		}

		return '';
	}
}
