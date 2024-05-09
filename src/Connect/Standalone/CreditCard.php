<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;

/** Standalone Pix */
class CreditCard extends Gateway
{
    public function __construct()
    {
        parent::__construct();
        $this->icon = apply_filters(
            'wc_pagseguro_connect_icon',
            plugins_url('public/images/payment-icon.php?method=cc', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );
        $this->method_title = $this->get_option(
            'cc_title',
            __('Cartão de Crédito via PagBank', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Cartão de Crédito via PagBank (por Ricardo Martins)',
            'pagbank-connect'
        );
        $this->title = $this->get_option('cc_title', __('Cartão de Crédito via PagBank', 'pagbank-connect'));
    }

    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['cc_enabled']) && 'yes' === $this->settings['cc_enabled'] ? 'yes'
            : 'no';
        $this->enabled = ($this->enabled === 'yes'
            && !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled']) ? 'yes' : 'no';
    }
}