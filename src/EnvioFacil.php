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

	const CODE = 'rm_enviofacil';
	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 *
	 * @noinspection PhpUnusedParameterInspection*/
	public function __construct( $instance_id = 0 ) {
		$this->id                 = self::CODE;
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


		// Build individual (non-aggregated) items for improved boxing calculation
		$items = [];
		$dimensionUnit = get_option('woocommerce_dimension_unit', 'cm');
		switch ($dimensionUnit) {
			case 'mm': $dimMultiplier = 1; break;
			case 'cm': $dimMultiplier = 10; break; // 1 cm = 10 mm
			case 'm': $dimMultiplier = 1000; break; // 1 m = 1000 mm
			case 'in': $dimMultiplier = 25.4; break; // inch to mm
			case 'yd': $dimMultiplier = 914.4; break; // yard to mm
			default: $dimMultiplier = 10; // fallback assume cm
		}
		$weightUnit = get_option('woocommerce_weight_unit', 'kg');
		switch ($weightUnit) {
			case 'g': $weightMultiplier = 1; break; // already grams
			case 'kg': $weightMultiplier = 1000; break; // kg to g
			case 'lbs': $weightMultiplier = 453.59237; break; // pounds to g
			case 'oz': $weightMultiplier = 28.34952; break; // ounces to g
			default: $weightMultiplier = 1000; // fallback assume kg
		}
		foreach ($package['contents'] as $content) {
			/** @var WC_Product $product */
			$product = $content['data'];
			$qty = (int) $content['quantity'];
			if ($qty < 1) { continue; }

			$prodDims = $product->get_dimensions(false); // array length|width|height
			$prodDims = array_map('floatval', $prodDims);
			$prodWeight = (float)$product->get_weight();

			$widthMm  = $prodDims['width'];
			$heightMm = $prodDims['height'] ?: 1;
			$lengthMm = $prodDims['length'] ?: 1;
			$weightG  = $prodWeight ?: 0.01;

			$priceUnit = (float) wc_get_price_excluding_tax($product); // valor unitário
			if ($priceUnit <= 0) {
				$priceUnit = $productValue / max(1, $qty); // fallback
			}
			
			$items[] = [
				'reference' => substr($product->get_name(), 0, 40),
				'width' => round($widthMm * $dimMultiplier),
				'length' => round($lengthMm * $dimMultiplier),
				'depth' => round($heightMm * $dimMultiplier),
				'weight' => round($weightG * $weightMultiplier),
				'qty' => $qty,
				'price' => (float) $priceUnit,
			];
		}

		if (empty($items)) {
			return [];
		}

		// Retrieve registered boxes (if the Box class exists)
		   $boxesPayload = [];
		   if (class_exists('\\RM_PagBank\\Connect\\EnvioFacil\\Box')) {
			   $boxManager = new \RM_PagBank\Connect\EnvioFacil\Box();
			   $availableBoxes = $boxManager->get_all_available();
			   foreach ($availableBoxes as $b) {
				   // Convert decimal columns from DB to int mm/g as required by API (no multiplication, just round)
				   $boxesPayload[] = [
					   'reference'   => $b->reference,
					   'outerWidth'  => (int) $b->outer_width,
					   'outerLength' => (int) $b->outer_length,
					   'outerDepth'  => (int) $b->outer_depth,
					   'emptyWeight' => (int) $b->empty_weight,
					   'innerWidth'  => (int) $b->inner_width,
					   'innerLength' => (int) $b->inner_length,
					   'innerDepth'  => (int) $b->inner_depth,
					   'maxWeight'   => (int) $b->max_weight,
				   ];
			   }
		   }

		   

		$params = [
			'sender' => $senderPostcode,
			'receiver' => $destinationPostcode,
			'boxes' => $boxesPayload,
			'items' => $items,
		];
        
        if (!$senderPostcode || strlen($senderPostcode) != 8) {
            Functions::log('EnvioFacil: CEP de origem não configurado ou incorreto', 'debug');
            return [];
        }

		$url = 'https://ws.pbintegracoes.com/pspro/v7/ef/boxing';

		$headers = [
			'Authorization' => 'Bearer ' . Params::getConfig('connect_key'),
			'Content-Type'  => 'application/json',
			'Platform' => 'WooCommerce',
			'Extra-Version' => WC()->version,
			'Platform-Version' => get_bloginfo('version'),
			'Module-Version' => WC_PAGSEGURO_CONNECT_VERSION,
			'Referer' => get_site_url(),
		];

		$ret = wp_remote_post($url, [
			'headers' => $headers,
			'body' => wp_json_encode($params),
            'timeout' => 60,
		]);
        
        if (is_wp_error($ret)) {
            return [];
        }

		$body = wp_remote_retrieve_body($ret);
		$decoded = json_decode($body, true);
		if (!is_array($decoded)) {
			Functions::log('EnvioFacil: resposta inválida boxing: '.$body, 'debug');
			return [];
		}

		if (isset($decoded['error_messages'])) {
			$errors = $decoded['error_messages'];
			$codes = array_map(static function($e){return $e['code'] ?? '';}, $errors);
			Functions::log('EnvioFacil: erros boxing', 'debug', ['errors' => $errors]);
			// Optional handling for specific error codes
			if (in_array('NO_BOXES_AVAILABLE', $codes, true)) {
				return [];
			}
			return []; // fallback genérico
		}

		// Expected structure: boxes[] each box contains shipping[]
		$aggregated = [];
		$boxCount = isset($decoded['boxes']) && is_array($decoded['boxes']) ? count($decoded['boxes']) : 0;
		$boxes = $decoded['boxes'] ?? [];
		$boxReferences = [];
		foreach ($boxes as $box) {
			if (empty($box['shipping']) || !is_array($box['shipping'])) { continue; }
			$boxReferences[] = $box['reference'];
			foreach ($box['shipping'] as $option) {
				if (!isset($option['provider'], $option['providerMethod'], $option['contractValue'])) { continue; }
				$key = $option['provider'].'|'.$option['providerMethod'];
				if (!isset($aggregated[$key])) {
					$aggregated[$key] = [
						'provider' => $option['provider'],
						'method' => $option['providerMethod'],
						'contractValue' => 0.0,
						'estimateDays' => (int) ($option['estimateDays'] ?? 0),
					];
				}
				$aggregated[$key]['contractValue'] += (float) $option['contractValue'];
				// total transit time = maximum transit among boxes (assuming consolidated shipment)
				$aggregated[$key]['estimateDays'] = max($aggregated[$key]['estimateDays'], (int) ($option['estimateDays'] ?? 0));
			}
		}

		if (empty($aggregated)) {
			return [];
		}

		$addDays = (int) $this->get_option('add_days', 0);
		$adjustment = $this->get_option('adjustment_fee', 0);
		foreach ($aggregated as $aggr) {
			$days = $aggr['estimateDays'] + $addDays;
			$cost = Functions::applyPriceAdjustment($aggr['contractValue'], $adjustment);
			if ($cost <= 0) { continue; }
			$label = sprintf('%s - %s - %d %s', $aggr['provider'], $aggr['method'], $days, _n('dia útil', 'dias úteis', $days, 'pagbank-connect'));

			$recommendedBoxes = '';
			if ( ! empty( $boxReferences ) ) {
				$boxCounts = array_count_values( $boxReferences );
				$boxStrings = [];
				foreach ( $boxCounts as $ref => $count ) {
					$boxStrings[] = $count . 'x ' . $ref;
				}
				$recommendedBoxes = implode( ', ', $boxStrings );
			}

			$this->add_rate([
				'id' => 'ef-'.$aggr['provider'].'-'.$aggr['method'],
				'label' => $label,
				'cost' => $cost,
				'calc_tax' => 'per_order',
				'meta_data' => [
					'pagbank_enviofacil_provider' => $aggr['provider'],
					'pagbank_enviofacil_method' => $aggr['method'],
					'pagbank_enviofacil_days' => $days,
					'pagbank_enviofacil_boxes' => $boxCount,
					'pagbank_enviofacil_recommended_boxes' => $recommendedBoxes,
				]
			]);
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
        $value = isset($_POST['woocommerce_'.$this->id.'_enabled']) ? htmlspecialchars(
            $_POST['woocommerce_'.$this->id.'_enabled'],
            ENT_QUOTES,
            'UTF-8'
        ) : '0';
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
