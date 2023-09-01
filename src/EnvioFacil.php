<?php
namespace RM_PagBank;

use WC_Shipping_Method;

class EnvioFacil extends WC_Shipping_Method
{
	public function __construct() {
		$this->id                 = 'rm_enviofacil';
		$this->method_title       = __( 'PagBank Envio Fácil' );  // Title shown in admin
		$this->method_description = __( 'Use taxas diferenciadas com Correios e transportadoras em pedidos feitos com PagBank' ); // Description shown in admin

		$this->enabled            = "yes"; // @TODO Add admin option
		$this->title              = "PagBank Envio Fácil";

		$this->init();
	}

	public function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function calculate_shipping( $package = array() ) {
		$rate = array(
			'id' => 'pac',
			'label' => $this->title,
			'cost' => '10.99',
			'calc_tax' => 'per_order'
		);

		$rate2 = array(
			'id' => 'sedex',
			'label' => $this->title . ' 2',
			'cost' => '15.99',
			'calc_tax' => 'per_order'
		);

		// Register the rate
		$this->add_rate( $rate );
		$this->add_rate( $rate2 );
	}

	public static function addMethod(){
		$methods['rm_enviofacil'] = 'RM_PagBank\EnvioFacil';
		return $methods;
	}
}
