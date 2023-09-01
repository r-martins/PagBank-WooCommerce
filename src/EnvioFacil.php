<?php
namespace RM_PagBank;

use WC_Product;
use WC_Shipping_Method;

class EnvioFacil extends WC_Shipping_Method
{
	public $countries = ['BR'];

	public function __construct() {
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
	}

	public function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function is_available($package)
	{
		if ( ! isset($package['destination']['postcode']))
		{
			return false;
		}

		$destinationPostcode = $package['destination']['postcode'];
		$destinationPostcode = preg_replace('/[^0-9]/', '', $destinationPostcode);

		if ( strlen( $destinationPostcode) != 8 )
			return false;

		return parent::is_available($package);
	}

	public function calculate_shipping( $package = array() ) {
		$destinationPostcode = $package['destination']['postcode'];
		$destinationPostcode = preg_replace('/[^0-9]/', '', $destinationPostcode);

		$senderPostcode = $this->get_option('origin_postcode', '');
		$senderPostcode = preg_replace('/[^0-9]/', '', $senderPostcode);

		$productValue = $package['contents_cost'];

		$dimensions = $this->getDimensionsAndWeight($package);

		$isValid = $this->validateDimensions($dimensions);

		if ( ! $isValid ) return false;

		$ch = curl_init();
		//body
		$body = json_encode([
			'postalSender' => $senderPostcode,
			'postalReceiver' => $destinationPostcode,
			'length' => $dimensions['length'],
			'height' => $dimensions['height'],
			'width' => $dimensions['width'],
			'weight' => $dimensions['weight'],
			'productValue' => $productValue
		]);
		curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://api.site.pagseguro.uol.com.br/ps-website-bff/v1/shipment/simulate',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			)
		));
		$ret = curl_exec($ch);
		curl_close($ch);

		$ret = json_decode($ret, true);

		if ( false === $ret) {
			return false;
		}

		foreach ($ret as $provider) {
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
		return false;
	}

	public static function addMethod()
	{
		$methods['rm_enviofacil'] = 'RM_PagBank\EnvioFacil';
		return $methods;
	}

	public function getDimensionsAndWeight($package)
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
			$weight = $product->get_weight();
			 $return['length'] += $dimensions['length'] * $content['quantity'];
			 $return['height'] += $dimensions['height'] * $content['quantity'];
			 $return['width'] += $dimensions['width'] * $content['quantity'];
			 $return['weight'] += $weight * $content['quantity'];
		}

		return $return;

	}

	public function validateDimensions($dimensions)
	{
		if (($dimensions['length'] < 15 || $dimensions['length'] > 100) ||
			($dimensions['height'] < 1 || $dimensions['height'] > 100) ||
			($dimensions['width'] < 10 || $dimensions['width'] > 100 ))
		{
			return false;
		}

		if ($dimensions['weight'] > 10)
		{
			return false;
		}

		return true;
	}

	public function init_form_fields()
	{
		$this->form_fields = [
			'enabled'            => array(
				'title'   => __( 'Habilitar', Connect::DOMAIN ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar', Connect::DOMAIN ),
				'default' => 'yes',
			),
			'origin_postcode'    => array(
				'title'       => __( 'CEP de Origem', Connect::DOMAIN ),
				'type'        => 'text',
				'description' => __( 'CEP de onde suas mercadorias serão enviadas', Connect::DOMAIN ),
				'desc_tip'    => true,
				'placeholder' => '00000-000',
				'default'     => $this->getBasePostcode(),
			),
		];

	}

	/**
	 * Get base postcode.
	 *
	 * @since  3.5.1
	 * @return string
	 */
	protected function getBasePostcode() {
		// WooCommerce 3.1.1+.
		if ( method_exists( WC()->countries, 'get_base_postcode' ) ) {
			return WC()->countries->get_base_postcode();
		}

		return '';
	}
}
