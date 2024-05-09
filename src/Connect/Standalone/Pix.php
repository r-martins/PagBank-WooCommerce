<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Connect\Gateway;

/** Standalone Pix */
class Pix extends Gateway
{
    public function __construct()
    {
        parent::__construct();
        $this->icon = apply_filters(
            'wc_pagseguro_connect_icon',
            plugins_url('public/images/payment-icon.php?method=pix', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
        );
        $this->has_fields = true;
        $this->method_title = $this->get_option('pix_title', __('PIX via PagBank Connect', 'pagbank-connect'));
        $this->method_description = __(
            'Receba pagamentos com PIX via PagBank (por Ricardo Martins)',
            'pagbank-connect'
        );
        $this->supports = [
            'products',
            'refunds',
            'default_credit_card_form',
            //            'tokenization' //TODO: implement tokenization
        ];


        $this->title = $this->get_option('pix_title', __('PIX', 'pagbank-connect'));
        $this->description = $this->get_option('description');
//        $this->init_settings();
    }

    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['pix_enabled']) && 'yes' === $this->settings['pix_enabled'] ? 'yes'
            : 'no';
        $this->enabled = ($this->enabled === 'yes'
            && !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled']) ? 'yes' : 'no';
    }
    
}