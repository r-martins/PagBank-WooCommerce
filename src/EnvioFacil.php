<?php
namespace RM_PagBank;

use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Api;
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

	   
	   if (empty($boxesPayload)) {
		   $defaultBox = $this->getDefaultBoxForCalculation();
		   if ($defaultBox) {
			   $boxesPayload[] = $defaultBox;
			   Functions::log('[EnvioFácil] Usando caixa padrão das configurações para cálculo', 'info', [
				   'default_box' => $defaultBox,
				   'itens' => count($items),
			   ]);
		   }
	   }

		$params = [
			'sender' => $senderPostcode,
			'receiver' => $destinationPostcode,
			'boxes' => $boxesPayload,
			'items' => $items,
		];
        
        if (!$senderPostcode || strlen($senderPostcode) != 8) {
            Functions::log('[EnvioFácil] CEP de origem não configurado ou incorreto', 'error', [
                'sender_postcode' => $senderPostcode,
                'configured_postcode' => $this->get_option('origin_postcode'),
                'store_postcode' => get_option('woocommerce_store_postcode')
            ]);
            return [];
        }

		try {
			$api = new Api();
			$decoded = $api->postEf('boxing', $params);
		} catch (\Exception $e) {
			Functions::log('[EnvioFácil] Erro na requisição para API boxing', 'error', [
				'message' => $e->getMessage(),
				'request_data' => $params,
			]);
			return [];
		}

		if (isset($decoded['error_messages'])) {
			$errors = $decoded['error_messages'];
			$codes = array_map(static function($e){return $e['code'] ?? '';}, $errors);
			
			// Log detailed errors for debugging
			Functions::log('[EnvioFácil] Erro na API de boxing', 'error', [
				'errors' => $errors,
				'codes' => $codes,
				'request_data' => $params,
				'decoded_response' => $decoded,
			]);
			
			// Log user-friendly messages for store owners
			foreach ($errors as $error) {
				$errorMsg = $error['message'] ?? 'Erro desconhecido';
				$errorCode = $error['code'] ?? 'UNKNOWN';
				Functions::log("[EnvioFácil] [$errorCode] $errorMsg", 'error');
			}
			
			// Optional handling for specific error codes
			if (in_array('NO_BOXES_AVAILABLE', $codes, true)) {
				Functions::log('[EnvioFácil] Nenhuma caixa disponível para os produtos selecionados', 'error');
				return [];
			}
			if (in_array('INVALID_BOX_DIMENSIONS', $codes, true)) {
				Functions::log('[EnvioFácil] Dimensões de caixa inválidas ou não aceitas pela transportadora', 'error');
				return [];
			}
			if (in_array('INVALID_ITEM_DIMENSIONS', $codes, true)) {
				Functions::log('[EnvioFácil] Dimensões de produto inválidas ou incompatíveis com caixas disponíveis', 'error');
				return [];
			}
			if (in_array('INVALID_POSTCODE', $codes, true)) {
				Functions::log('[EnvioFácil] CEP de origem ou destino inválido', 'error');
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
			Functions::log('[EnvioFácil] Nenhuma opção de frete disponível após processamento dos dados da API', 'warning', [
				'boxes_count' => $boxCount,
				'boxes_references' => $boxReferences,
				'decoded_response' => $decoded
			]);
			return [];
		}

		// Log successful calculation
		Functions::log('[EnvioFácil] Cálculo de frete realizado com sucesso', 'info', [
			'shipping_options' => count($aggregated),
			'boxes_used' => $boxCount,
			'boxes_references' => $boxReferences
		]);

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
					__('Transportadora', 'pagbank-connect') => $aggr['provider'],
					__('Método de envio', 'pagbank-connect') => $aggr['method'],
					__('Entrega estimada (dias)', 'pagbank-connect') => $days,
					__('Quantidade de caixas', 'pagbank-connect') => $boxCount,
					__('Caixas recomendadas', 'pagbank-connect') => $recommendedBoxes,
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
        $defaultBoxData = $this->getDefaultBoxData();
        $this->form_fields = [
            'enabled'         => [
                'title'   => __('Habilitar', 'pagbank-connect'),
                'type'    => 'checkbox',
                'label'   => __('Habilitar', 'pagbank-connect'),
                'default' => 'no',
            ],
            'boxes_info' => [
                'title' => __('Embalagens', 'pagbank-connect'),
                'type' => 'title',
                'description' => sprintf(
                    __('📦 <a href="%s" target="_blank">Gerenciar embalagens do Envio Fácil</a> - Configure as caixas/embalagens disponíveis para cálculo de frete.', 'pagbank-connect'),
                    admin_url('admin.php?page=rm-pagbank-boxes')
                ),
                'desc_tip' => false,
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
            'default_box_section' => [
                'title' => __('Caixa Padrão', 'pagbank-connect'),
                'type' => 'title',
                'description' => __('Configure uma caixa padrão para cálculo de frete. Deixe em branco se já possui caixas cadastradas.', 'pagbank-connect'),
            ],
            'default_box_reference' => [
                'title' => __('Referência da Caixa', 'pagbank-connect'),
                'type' => 'text',
                'description' => __('Nome identificador da caixa (ex: CAIXA_PADRAO)', 'pagbank-connect'),
                'default' => $defaultBoxData['reference'],
                'desc_tip' => true,
            ],
            'default_box_width' => [
                'title' => __('Largura (cm)', 'pagbank-connect'),
                'type' => 'number',
                'description' => __('Largura externa da caixa em centímetros', 'pagbank-connect'),
                'default' => $defaultBoxData['width'],
                'custom_attributes' => [
                    'min' => '10',
                    'max' => '100',
                    'step' => '0.1'
                ],
                'desc_tip' => true,
            ],
            'default_box_height' => [
                'title' => __('Altura (cm)', 'pagbank-connect'),
                'type' => 'number',
                'description' => __('Altura externa da caixa em centímetros', 'pagbank-connect'),
                'default' => $defaultBoxData['height'],
                'custom_attributes' => [
                    'min' => '1',
                    'max' => '100',
                    'step' => '0.1'
                ],
                'desc_tip' => true,
            ],
            'default_box_length' => [
                'title' => __('Comprimento (cm)', 'pagbank-connect'),
                'type' => 'number',
                'description' => __('Comprimento externo da caixa em centímetros', 'pagbank-connect'),
                'default' => $defaultBoxData['length'],
                'custom_attributes' => [
                    'min' => '15',
                    'max' => '100',
                    'step' => '0.1'
                ],
                'desc_tip' => true,
            ],
            'default_box_thickness' => [
                'title' => __('Espessura (cm)', 'pagbank-connect'),
                'type' => 'number',
                'description' => __('Espessura da parede da caixa em centímetros', 'pagbank-connect'),
                'default' => $defaultBoxData['thickness'],
                'custom_attributes' => [
                    'min' => '0.1',
                    'step' => '0.1'
                ],
                'desc_tip' => true,
            ],
            'default_box_max_weight' => [
                'title' => __('Peso Máximo (g)', 'pagbank-connect'),
                'type' => 'number',
                'description' => __('Peso máximo suportado pela caixa em gramas', 'pagbank-connect'),
                'default' => $defaultBoxData['max_weight'],
                'custom_attributes' => [
                    'min' => '300',
                    'max' => '10000',
                    'step' => '1'
                ],
                'desc_tip' => true,
            ],
            'default_box_empty_weight' => [
                'title' => __('Peso Vazio (g)', 'pagbank-connect'),
                'type' => 'number',
                'description' => __('Peso da caixa vazia em gramas', 'pagbank-connect'),
                'default' => $defaultBoxData['empty_weight'],
                'custom_attributes' => [
                    'min' => '1',
                    'max' => '9999',
                    'step' => '1'
                ],
                'desc_tip' => true,
            ],
        ];

    }

	/**
	 * Get default box data for calculation if configured
	 *
	 * @return array|null
	 */
	private function getDefaultBoxForCalculation(): ?array
	{
		$reference = $this->get_option('default_box_reference');
		$width = $this->get_option('default_box_width');
		$height = $this->get_option('default_box_height');
		$length = $this->get_option('default_box_length');
		$thickness = $this->get_option('default_box_thickness');
		$maxWeight = $this->get_option('default_box_max_weight');
		$emptyWeight = $this->get_option('default_box_empty_weight');

		// Se algum campo obrigatório estiver vazio, retornar null
		if (empty($reference) || empty($width) || empty($height) || empty($length) || empty($thickness) || empty($maxWeight) || empty($emptyWeight)) {
			return null;
		}

		// Converter valores para mm/g conforme esperado pela API
		$widthMm = floatval($width) * 10;   // cm para mm
		$heightMm = floatval($height) * 10; // cm para mm
		$lengthMm = floatval($length) * 10; // cm para mm
		$thicknessMm = floatval($thickness) * 10; // cm para mm

		// Calcular dimensões internas subtraindo a espessura das paredes
		$innerWidthMm = max(1, $widthMm - (2 * $thicknessMm));
		$innerLengthMm = max(1, $lengthMm - (2 * $thicknessMm));
		$innerHeightMm = max(1, $heightMm - $thicknessMm); // apenas uma espessura para altura

		return [
			'reference'   => $reference,
			'outerWidth'  => (int) $widthMm,
			'outerLength' => (int) $lengthMm,
			'outerDepth'  => (int) $heightMm,
			'emptyWeight' => (int) $emptyWeight,
			'innerWidth'  => (int) $innerWidthMm,
			'innerLength' => (int) $innerLengthMm,
			'innerDepth'  => (int) $innerHeightMm,
			'maxWeight'   => (int) $maxWeight,
		];
	}

	/**
	 * Get default box data for form fields
	 *
	 * @return array
	 */
	private function getDefaultBoxData(): array
	{
		if (!class_exists('\\RM_PagBank\\Connect\\EnvioFacil\\Box')) {
			return [];
		}

		$boxManager = new \RM_PagBank\Connect\EnvioFacil\Box();
		$boxes = $boxManager->get_all(['is_available' => null, 'limit' => 100]);
		foreach ($boxes as $box) {
			if ($box->reference === 'CAIXA_PADRAO_EF' || strpos($box->reference, 'PADRAO') !== false) {
				return [
					'reference' => $box->reference,
					'width' => $box->outer_width / 10,    // converter mm para cm
					'height' => $box->outer_depth / 10,   // converter mm para cm
					'length' => $box->outer_length / 10,  // converter mm para cm
					'thickness' => $box->thickness / 10,  // converter mm para cm
					'max_weight' => $box->max_weight,
					'empty_weight' => $box->empty_weight,
				];
			}
		}

		// Valores padrão se não encontrar caixa existente
		return [
			'reference' => 'CAIXA_PADRAO_EF',
			'width' => '20',
			'height' => '15',
			'length' => '30',
			'thickness' => '0.5',
			'max_weight' => '1000',
			'empty_weight' => '100',
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
	}    public function validate_adjustment_fee_field($key, $value) {
        return Functions::validateDiscountValue($value, true);
    }
    
    public function validate_add_days_field($key, $value) {
        if ($value === '') {
            return '';
        }
        return absint($value);
    }

    /**
     * Process the default box settings and create/update the box
     */
    public function process_admin_options() {
        $result = parent::process_admin_options();
        
        // Processar caixa padrão se os campos estiverem preenchidos
        $this->processDefaultBox();
        
        return $result;
    }

    /**
     * Process default box creation/update
     */
    private function processDefaultBox(): void {
        if (!class_exists('\\RM_PagBank\\Connect\\EnvioFacil\\Box')) {
            return;
        }

        $reference = $this->get_option('default_box_reference');
        $width = $this->get_option('default_box_width');
        $height = $this->get_option('default_box_height');
        $length = $this->get_option('default_box_length');
        $thickness = $this->get_option('default_box_thickness');
        $maxWeight = $this->get_option('default_box_max_weight');
        $emptyWeight = $this->get_option('default_box_empty_weight');

        // Se algum campo obrigatório estiver vazio, não processar
        if (empty($reference) || empty($width) || empty($height) || empty($length) || empty($thickness) || empty($maxWeight) || empty($emptyWeight)) {
            return;
        }

        $boxManager = new \RM_PagBank\Connect\EnvioFacil\Box();
        
        // Verificar se já existe uma caixa com essa referência
        $existingBoxes = $boxManager->get_all(['is_available' => null, 'limit' => 100]);
        $existingBox = null;
        foreach ($existingBoxes as $box) {
            if ($box->reference === $reference) {
                $existingBox = $box;
                break;
            }
        }

        $boxData = [
            'reference' => $reference,
            'is_available' => 1,
            'outer_width' => floatval($width),
            'outer_depth' => floatval($height),
            'outer_length' => floatval($length),
            'thickness' => floatval($thickness),
            'max_weight' => intval($maxWeight),
            'empty_weight' => intval($emptyWeight),
        ];

        if ($existingBox) {
            $result = $boxManager->update($existingBox->box_id, $boxData);
            if (is_wp_error($result)) {
                WC_Admin_Settings::add_error(
                    sprintf(__('Erro ao atualizar caixa %s: %s', 'pagbank-connect'), $reference, $result->get_error_message())
                );
            } else {
                WC_Admin_Settings::add_message(
                    sprintf(__('Caixa %s atualizada com sucesso!', 'pagbank-connect'), $reference)
                );
            }
        } else {
            $result = $boxManager->create($boxData);
            if (is_wp_error($result)) {
                WC_Admin_Settings::add_error(
                    sprintf(__('Erro ao criar caixa %s: %s', 'pagbank-connect'), $reference, $result->get_error_message())
                );
            } else {
                WC_Admin_Settings::add_message(
                    sprintf(__('Caixa %s criada com sucesso! <a href="%s">Gerenciar todas as caixas</a>', 'pagbank-connect'), 
                        $reference, 
                        admin_url('admin.php?page=rm-pagbank-boxes')
                    )
                );
            }
        }
    }

	public function init_settings()
	{
		$this->init_form_fields();
		parent::init_settings();
	}
}
