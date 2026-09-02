<?php
namespace RM_PagBank\Connect\Recurring;

use stdClass;
use WC_Email;

class RecurringEmails extends WC_Email
{
    public function mergePlaceholders(stdClass $subscription)
    {
        foreach ($subscription as $key => $value)
        {
            $this->placeholders['{'.$key.'}'] = $value;
        }
    }

    /**
     * Dummy subscription for WooCommerce email preview/settings screens,
     * where trigger() has not run and $subscription is unset.
     */
    protected function get_dummy_subscription(): stdClass
    {
        $subscription = new stdClass();
        $subscription->id = 12345;
        $subscription->initial_order_id = $this->object instanceof \WC_Order ? $this->object->get_id() : 0;
        $subscription->next_bill_at = gmdate('Y-m-d H:i:s', strtotime('+7 days'));
        $subscription->paused_at = gmdate('Y-m-d H:i:s');
        $subscription->canceled_at = null;
        $subscription->suspended_reason = __('Falha no pagamento (prévia)', 'pagbank-connect');
        $subscription->canceled_reason = __('Cancelada pelo cliente (prévia)', 'pagbank-connect');

        return $subscription;
    }

    /**
     * Returns the real subscription or a dummy one for email preview.
     *
     * @param stdClass|null $subscription
     */
    protected function get_subscription_for_email($subscription): stdClass
    {
        return $subscription instanceof stdClass ? $subscription : $this->get_dummy_subscription();
    }
}
