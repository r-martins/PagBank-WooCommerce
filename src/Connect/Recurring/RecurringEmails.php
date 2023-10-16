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
}