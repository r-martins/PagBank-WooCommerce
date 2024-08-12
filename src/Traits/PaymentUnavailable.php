<?php

namespace RM_PagBank\Traits;

use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;

trait PaymentUnavailable
{
    /**
     * Disables PagBank if order < R$1.00
     * @param $gateways
     *
     * @return mixed
     */
    public function disableIfOrderLessThanOneReal($gateways)
    {
        if (!is_checkout()) {
            return $gateways;
        }

        $hideIfUnavailable = Params::getConfig('hide_if_unavailable');
        if (!wc_string_to_bool($hideIfUnavailable) || is_admin()) {
            return $gateways;
        }

        if ($this->paymentUnavailable()) {
            foreach ($gateways as $key => $gateway) {
                if (strpos($key, Connect::DOMAIN) !== false) {
                    unset($gateways[$key]);
                }
            }
        }

        return $gateways;
    }

    /**
     * Payment is unavailable if the total is less than R$1.00
     * @return bool
     */
    public function paymentUnavailable(): bool
    {
        $total = Api::getOrderTotal();
        $total = Params::convertToCents($total);
        $isTotalLessThanOneReal = $total < 100;
        if (!$isTotalLessThanOneReal) {
            return false;
        }

        $recHelper = new RecurringHelper();
        if ($recHelper->isCartRecurring()) {
            return false;
        }

        return true;
    }
}
