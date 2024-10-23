<?php

namespace RM_PagBank\Traits;

use RM_PagBank\Helpers\Params;

trait PaymentMethodIcon
{
    /**
     * Get gateway icon
     *
     * @param $icon
     * @param $id
     * @return mixed|string
     */
    public function getGatewayIcon($icon, $id) {
        $isDynamicIcoAccessible = Params::getIsDynamicIcoAccessible();
        if ($isDynamicIcoAccessible && $id === $this->id) {
            $this->icon = apply_filters(
                'wc_pagseguro_connect_icon',
                plugins_url('public/images/payment-icon.php?method=' . $this->code, WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
            return '<img src="' . esc_url( \WC_HTTPS::force_https_url( $this->icon ) ) . '" alt="' . esc_attr( $this->get_title() ) . '" />';
        }
        return $icon;
    }
}
