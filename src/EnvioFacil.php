<?php
namespace RM_PagBank;

use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use WC_Admin_Settings;
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
		$this->method_title       = __( 'PagBank Envio Fácil', 'pagbank-connect' );  // Title shown in admin
		$this->method_description = __( 'Use taxas diferenciadas com Correios e transportadoras em pedidos feitos com PagBank', 'pagbank-connect' ); // Description shown in admin

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
    public function calculate_shipping($package = array()): array
    {
        $destinationPostcode = $package['destination']['postcode'];
        $destinationPostcode = preg_replace('/[^0-9]/', '', $destinationPostcode);

        $senderPostcode = $this->get_option('origin_postcode', get_option('woocommerce_store_postcode'));
        $senderPostcode = preg_replace('/[^0-9]/', '', $senderPostcode);

        $productValue = $package['contents_cost'];

        $dimensions = $this->getDimensionsAndWeight($package);

        $isValid = $this->validateDimensions($dimensions);

        if (!$isValid || !$dimensions) {
            return [];
        }

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
                'Authorization' => 'Bearer '.Params::getConfig('connect_key'),
            ],
            'timeout' => 10,
            'sslverify' => false,
            'httpversion' => '1.1'
        ]);


        if (is_wp_error($ret)) {
            return [];
        }
        $ret = wp_remote_retrieve_body($ret);
        $ret = json_decode($ret, true);

        if (isset($ret['error_messages'])) {
            Functions::log('Erro ao calcular o frete: '.print_r($ret['error_messages'], true), 'debug');

            return [];
        }

        foreach ($ret as $provider) {
            if (!isset($provider['provider']) || !isset($provider['providerMethod'])
                || !isset($provider['contractValue'])) {
                continue;
            }

            $addDays = $this->get_option('add_days', 0);
            $provider['estimateDays'] += $addDays;
            
            $adjustment = $this->get_option('adjustment_fee', 0);
            $provider['contractValue'] = Functions::applyPriceAdjustment($provider['contractValue'], $adjustment);
            $rate = array(
                'id'       => 'ef-'.$provider['provider'],
                'label'    => $provider['provider'].' - '.$provider['providerMethod'].sprintf(
                    __(' - %d dias úteis', 'pagbank-connect'),
                    $provider['estimateDays']
                ),
                'cost'     => $provider['contractValue'],
                'calc_tax' => 'per_order',
            );

            if (!$rate['cost']) {
                continue;
            }

            $this->add_rate($rate);
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
            $weight = Functions::convertToKg($weight);
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

		if ($dimensions['weight'] > 10 || $dimensions['weight'] < 0.3)
		{
            Functions::log(
                'Peso inválido: '.$dimensions['weight'].'. Deve ser menor que 10kg e maior que 0.3.',
                'debug'
            );
			return false;
		}

		return true;
	}

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled'         => [
                'title'   => __('Habilitar', 'pagbank-connect'),
                'type'    => 'checkbox',
                'label'   => __('Habilitar', 'pagbank-connect'),
                'default' => 'no',
            ],
            'origin_postcode' => [
                'title'       => __('CEP de Origem', 'pagbank-connect'),
                'type'        => 'text',
                'description' => __(
                    'CEP de onde suas mercadorias serão enviadas. '.'Se não informado, o CEP da loja será utilizado.',
                    'pagbank-connect'
                ),
                'desc_tip'    => true,
                'placeholder' => get_option('woocommerce_store_postcode', '00000-000'),
                'default'     => $this->getBasePostcode(),
            ],
            'adjustment_fee'    => [
                'title'       => __('Ajuste de preço', 'pagbank-connect'),
                'type'        => 'text',
                'description' => __(
                    'Acrescente ou remova um valor fixo ou percentual do frete. <br/>' .
                    'Use o sinal de menos para descontar. <br/>Adicione o símbolo % para um valor percentual.',
                    'pagbank-connect'
                ),
                'placeholder' => __('% ou fixo, positivo ou negativo', 'pagbank-connect'),
                'desc_tip'    => true,
            ],
            'add_days' => [
                'title'       => __('Adicionar', 'pagbank-connect'),
                'type'        => 'number',
                'description' => __('dias à estimativa do frete.', 'pagbank-connect'),
                'desc_tip'    => false,
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

	/**
	 * Output the shipping settings screen.
	 */
	public function admin_options()
	{
		if ( ! $this->instance_id ) {
			echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		}
		echo wp_kses_post( wpautop( $this->get_method_description() ) );
        echo wp_kses(
            __(
                'Para utilizar o PagBank Envio Fácil, você precisa autorizar nossa aplicação e obter suas '
                .'credenciais connect. <strong>Chaves Sandbox ou Minhas Taxas não são elegíveis.</strong>',
                'pagbank-connect'
            ),
            'strong'
        );
        echo '<p>'.esc_html(
                __(
                    '⚠️ Use com cautela. Este serviço usa uma API desencorajada pelo PagBank para o cálculo do'
                    .' frete. Faça suas simulações antes. ;)',
                    'pagbank-connect'
                )
            ).'</p>';
        echo '<p><a href="https://pagsegurotransparente.zendesk.com/hc/pt-br/articles/19944920673805-'
            .'Envio-F%C3%A1cil-com-WooCommerce" target="_blank">'
            .esc_html(__('Ver documentação ↗', 'pagbank-connect')).'</a>'.'</p>';
        echo $this->get_admin_options_html(); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
    }

	/**
	 * Validates if the method can be enabled with the configured connect key
	 *
	 * @param $value string
	 *
	 * @return string
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
    public function validate_enabled_field(string $value) : string
    {
		// We can't rely on the passed $value here, because WordPress always sends 'enabled' as value
        $value = htmlspecialchars($_POST['woocommerce_'] . $this->id . '_enabled', ENT_QUOTES, 'UTF-8');
        
		$value = $value == '1' ? 'yes' : 'no';

		$connectKey = Params::getConfig('connect_key');
		if (strpos($connectKey, 'CONPS14') === false && strpos($connectKey, 'CONPS30') === false && $value == 'yes') {
			WC_Admin_Settings::add_error(
				__(
					'Para utilizar o PagBank Envio Fácil, você precisa obter suas credenciais connect. '
					.'Chaves Sandbox ou Minhas Taxas não são elegíveis.',
					'pagbank-connect'
				)
			);
			$value = 'no';
		}

		return $value;
	}

    public function validate_adjustment_fee_field($key, $value) {
        return Functions::validateDiscountValue($value, true);
    }
    
    public function validate_add_days_field($key, $value) {
        if ($value === '') {
            return '';
        }
        return absint($value);
    }

	public function init_settings()
	{
		$this->init_form_fields();
		parent::init_settings();
	}
}
