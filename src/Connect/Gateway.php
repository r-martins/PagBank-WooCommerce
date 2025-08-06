<?php

namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Traits\PaymentUnavailable;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\StaticResources;
use RM_PagBank\Traits\ThankyouInstructions;
use WC_Admin_Settings;
use WC_Payment_Gateway_CC;

/**
 * Class Gateway
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 */
class Gateway extends WC_Payment_Gateway_CC
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use ThankyouInstructions;

    public function __construct()
    {
        $this->id = Connect::DOMAIN;
		$this->icon = apply_filters(
			'wc_pagseguro_connect_icon',
			plugins_url('public/images/pagbank.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
		);
        $this->method_title = __('PagBank Connect por Ricardo Martins', 'pagbank-connect');
		$this->method_description = __(
			'Aceite PIX, Cartão e Boleto de forma transparente com PagBank (PagSeguro).',
			'pagbank-connect'
		);
		$this->title = $this->get_option('title', __('PagBank (PagSeguro UOL)', 'pagbank-connect'));
		$this->description = $this->get_option('description');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);

        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN, [$this, 'addThankyouInstructions']);
    }

    public function init_form_fields()
    {
        $fields = [];
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/general-fields.php';
        $this->form_fields = array_merge(...$fields);
    }

    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
    }

    /**
     * Validates the eligibility of the key used in the recurring feature
     * Note: attempting to modify this behavior will not make the plugin work in your favor
     *
     * @param $key
     * @param $recurring_enabled
     *
     * @return string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate_recurring_enabled_field($key, $recurring_enabled): string
    {
        $connect_key = $this->get_option('connect_key');
        if (substr($connect_key, 0, 9) == 'CONPSFLEX' && $recurring_enabled) {
            WC_Admin_Settings::add_message(__('A recorrência foi desativada pois'
                .' a Connect Key informada usa taxas personalizadas.', 'pagbank-connect'));
            return 'no';
        }
        
        return $recurring_enabled ? 'yes' : 'no';
    }
    
    
    
	/**
	 * Validates the inputed connect key and save additional information like public key and sandbox mode
	 *
	 * @param $key
	 * @param $connect_key
	 *
	 * @return mixed|string
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function validate_connect_key_field($key, $connect_key)
    {
        //check if it looks like a token (contains lower case and dashes)
        if (preg_match('/[a-z-]/', $connect_key)) {
            WC_Admin_Settings::add_error(__('Parece que você informou o Token PagBank no lugar da Connect Key. Clique em Obter Connect Key para obter a sua gratuitamente e ainda economizar nas taxas oficiais.', 'pagbank-connect'));
            return '';
        }

        $api = new Api();
        $api->setConnectKey($connect_key);
        
        try {
            $ret = $api->post('ws/public-keys', ['type' => 'card']);
            if (isset($ret['public_key'])) {
                $this->update_option('public_key', $ret['public_key']);
                $this->update_option('public_key_created_at', $ret['created_at']);
				$isSandbox = strpos($connect_key, 'CONSANDBOX') !== false;
				$this->update_option('is_sandbox', $isSandbox);
            }

            if (isset($ret['error_messages'])){
                //implode error_messages showing code and description
                $error_messages = array_map(function($error){
                    return $error['code'] . ' - ' . $error['description'];
                }, $ret['error_messages']);
                WC_Admin_Settings::add_error(implode('<br/>', $error_messages));
                $connect_key = '';
            }
        } catch (Exception $e) {
            WC_Admin_Settings::add_error('Validação da Connect Key Falhou. ' . $e->getMessage());
            $connect_key = '';
        }

        return $connect_key;

    }
    
    public function validate_icons_color_field($key, $icon_color)
    {
        //Validate if dynamic icon is accessible
        delete_transient('rm_pagbank_dynamic_ico_accessible');
        $isDynamicIcoAccessible = Params::getIsDynamicIcoAccessible();
        if (!$isDynamicIcoAccessible) {
            WC_Admin_Settings::add_error(__('A personalização da cor dos ícones foi desativada, pois alguma configuração de sua loja ou ambiente impede ele de ser utilizado/acessado.', 'pagbank-connect'));
            $icon_color = 'gray';
        }
        
        return $icon_color;
    }

    /**
     * Validate frontend fields
     *
     * @return bool
     */
    public function validate_fields():bool
    {
        return true; //@TODO validate_fields
    }

    public static function addStylesWoo($styles)
    {
        if ( Recurring::isRecurringEndpoint() )
        {
            $styles['rm-pagbank-recurring'] = [
                'src'     => plugins_url('public/css/recurring.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                'deps'    => [],
                'version' => WC_PAGSEGURO_CONNECT_VERSION,
                'media'   => 'all',
                'has_rtl' => false,
            ];
        }
        return $styles;
    }

    /**
     * Retrieves cached Connect info or fetches fresh data from the API.
     *
     * @param bool $force_refresh Whether to force refresh the cached data.
     * @return array|null The connect information or null if unavailable.
     */
    public function getCachedConnectInfo($transient_key, $force_refresh = false)
    {
        // Return cached data if not forcing refresh
        if (! $force_refresh ) {
            $cached = get_transient($transient_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch fresh data from the API
        $api = new Api();
        $info = $api->getConnectInfo();

        // Cache the result if it's valid
        if (! empty($info) && empty($info['error_messages'])) {
            set_transient($transient_key, $info, DAY_IN_SECONDS);
        }

        return $info;
    }

    /**
     * Generates the connection status HTML badge based on the current Connect key info.
     *
     * @return string|null HTML output of the status badge or null if not applicable.
     */
    public function connectKeyStatus()
    {
        // Retrieve plugin settings
        $settings = get_option('woocommerce_' . $this->id . '_settings');
        $connect_key = $settings['connect_key'] ?? '';
        $last_four = strlen($connect_key) == 40 ? substr($connect_key, -4) : null;

        if (empty($connect_key) || !$last_four) {
            return null;
        }

        $transient_key = sprintf('pagbank_connect_key_info_%s', $last_four);
   
        $force_refresh = isset($_GET['refresh_connect_info']);
        // Force refresh if requested via URL
        if ($force_refresh) {
            delete_transient($transient_key);
        }

        // Get cached or fresh Connect info
        $info = $this->getCachedConnectInfo($transient_key, $force_refresh);

        if (!$info) {
            return null;
        }

        // Extract and sanitize connect status info
        $dateFormat = get_option('date_format');
        $status   = strtoupper($info['status'] ?? 'UNKNOWN');
        $email    = esc_html($info['authorizerEmail'] ?? 'N/A');
        $expires  = esc_html(isset($info['expiresAt']) && $info['expiresAt'] ? date_i18n($dateFormat, strtotime($info['expiresAt'])) : '-');
        $isSandbox = !empty($info['isSandbox']);
        $sandbox  = $isSandbox ? 'Sim' : 'Não';
        $sandbox = !isset($info['isSandbox']) ? 'Desconhecido' : $sandbox;
        $message  = "Conta PagBank: $email <br>";
        $message .= !$isSandbox ? "Expira em: $expires <br>" : null;
        $message .= "Sandbox: $sandbox <br>";
        $message .= !$isSandbox ? "* renova automaticamente" : null;
        // Tooltip with detailed connect information
        $tooltip = esc_attr($message);

        // Generate status badge based on the current status
        switch ($status) {
            case "VALID":
                $btn = $this->buildStatusBadge('#4caf50', 'dashicons-yes', 'Conectado');
                break;
            case "INVALID":
                $btn = $this->buildStatusBadge('#f44336', 'dashicons-dismiss', 'Chave inválida');
                break;
            case "UNAUTHORIZED":
                $btn = $this->buildStatusBadge('#ff9800', 'dashicons-lock', 'Não autorizado');
                break;
            case "UNKNOWN":
                $btn = $this->buildStatusBadge('#9e9e9e', 'dashicons-info-outline', 'Desconhecido');
                break;
            default:
                $btn = $this->buildStatusBadge('#888', 'dashicons-info-outline', 'Erro ao obter informação');
                break;
        }

        $this->setStyleConnectKeyInfo();
        // Generate final HTML with badge, refresh button, and tooltip
        $html = '<div class="rm-pagbank-status-container">';
        $html .= $btn;
        $html .= '<a href="' . esc_url(add_query_arg('refresh_connect_info', 1)) . '" title="Atualizar" class="rm-pagbank-refresh-button">';
        $html .=  '<span class="dashicons dashicons-update-alt"></span>';
        $html .= '</a>';
        $html .= '<span class="dashicons dashicons-info" data-tip="' . $tooltip . '"></span>';
        $html .= '</div>';

       if ($isSandbox) {
            $html .= '<div class="rm-pagbank-connect-key-info">';
            $html .= '<span class="dashicons dashicons-warning"></span>';
            $html .= '<strong>' . __('Sandbox ativo', 'pagbank-connect') . ': </strong> '
                . __('você está testando o PagBank. Pedidos feitos neste ambiente não aparecerão no PagBank.', 'pagbank-connect')
                . '<br>';

            $html .= '<a href="' . esc_url('https://developer.pagbank.com.br/docs/simulador') . '" target="_blank" class="rm-pagbank-doc-link">';
            $html .= __('Documentação do Simulador', 'pagbank-connect');
            $html .= '</a><br>';

            $html .= '<a href="' . esc_url('https://ajuda.pbintegracoes.com/hc/pt-br/articles/22375426666253-Cart%C3%B5es-de-Cr%C3%A9dito-para-Testes-PagBank') . '" target="_blank" class="rm-pagbank-doc-link">';
            $html .= __('Cartões de Teste', 'pagbank-connect');
            $html .= '</a>';

            $html .= '</div>';
        }
       
        return $html;
    }
    /**
     * Adds custom styles for the Connect Key info badge in the admin area.
     * @return void
     */
    public function setStyleConnectKeyInfo()
    {
        wp_enqueue_style(
            'rm-pagbank-admin-connect-key-info', 
            plugins_url('public/css/admin/connect-key-info.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            false, 
            WC_PAGSEGURO_CONNECT_VERSION
        );
    }
    /**
     * Helper to build a styled status badge.
     *
     * @param string $color       Background color of the badge.
     * @param string $icon_class  Dashicon class to use.
     * @param string $label       Text label of the badge.
     * @return string             HTML of the badge.
     */
    private function buildStatusBadge($color, $icon_class, $label)
    {
        return sprintf(
            '<div class="rm-pagbank-status-badge learn-more">
                    <span class="circle" aria-hidden="true" style="background: %s;">
                        <span class="dashicons %s"></span>
                    </span>
                    <span class="button-text"> %s</span>
            </div>',
            esc_attr($color),
            esc_attr($icon_class),
            esc_html($label)
        );
    }
}
