<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect\Gateway;

/** Standalone Pix */
class Boleto extends Gateway
{
    public function __construct()
    {
        parent::__construct();
        $this->icon = apply_filters(
            'wc_pagseguro_connect_icon',
            plugins_url('public/images/payment-icon.php?method=boleto', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );
        $this->method_title = $this->get_option(
            'boleto_title',
            __('Boleto via PagBank', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Boleto via PagBank (por Ricardo Martins)',
            'pagbank-connect'
        );
        $this->title = $this->get_option('boleto_title', __('Boleto via PagBank', 'pagbank-connect'));
    }
    
    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['boleto_enabled']) && 'yes' === $this->settings['boleto_enabled']
            ? 'yes' : 'no';
        $this->enabled = ($this->enabled === 'yes'
            && !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled']) ? 'yes' : 'no';
    }
}