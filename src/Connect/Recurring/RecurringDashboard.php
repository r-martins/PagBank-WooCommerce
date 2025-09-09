<?php


namespace RM_PagBank\Connect\Recurring;


use RM_PagBank\Connect;

class RecurringDashboard
{
    public function getMySubscriptions(): array
    {
        $return = [];
        
        //checks is user is logged in
        if (!is_user_logged_in()) {
            return $return;
        }
        
        //get user's orders
        /** @var array $orders */
        $orders = wc_get_orders([
            'customer' => get_current_user_id(),
            'limit' => -1
        ]);

        $ids = array_map(function ($order) {
            return $order->get_id();
        }, $orders);

        global $wpdb;
        
        // If no orders, return empty array
        if (empty($ids)) {
            return [];
        }
        
        // Create placeholders for each ID
        $placeholders = array_fill(0, count($ids), '%d');
        $format = implode(',', $placeholders);
        
        // Prepare and execute the query safely
        $table = $wpdb->prefix . 'pagbank_recurring';
        $query = $wpdb->prepare(
            "SELECT * FROM `$table` WHERE initial_order_id IN ($format) ORDER BY id DESC",
            $ids
        );
        
        $subscriptions = $wpdb->get_results($query);
        
        if ( ! empty($subscriptions))
        {
            return $subscriptions;
        }
        
        return $return;
    }
    
    public function getColumns()
    {
        return apply_filters(
            'rm_pagbank_recurring_dashboard_columns',
            [
                'recurring-id' => __('Identificador', 'pagbank-connect'),
                'status' => __('Status', 'pagbank-connect'),
                'created_at' => __('Data Inicial', 'pagbank-connect'),
                'recurring_type' => __('Tipo', 'pagbank-connect'),
                'recurring_amount' => __('Valor', 'pagbank-connect'),
                'subscription-actions' => __('Ações', 'pagbank-connect'),
            ]
        );
    }
    
    public function getViewSubscriptionUrl($subscription): string
    {
        return wc_get_endpoint_url('rm-pagbank-subscriptions-view', $subscription->id);
    }

    /**
     * Return the possible in-row actions for a subscription
     * Used in the user's account page > subscriptions
     * @param $subscription
     *
     * @return array
     */
    public function getSubscriptionInRowActions($subscription): array
    {
        $actions = [];
        
        switch ($subscription->status) {
            case 'PAUSED':
                $actions['unpause'] = [
                    'name' => __('Resumir', 'pagbank-connect'),
                    'url' => \RM_PagBank\Helpers\Recurring::subscriptionActionUrl('unpause', $subscription)
                ];
                break;
            case 'PENDING_CANCEL':
                $actions['cancel'] = [
                    'name' => __('Suspender Cancelamento', 'pagbank-connect'),
                    'url' =>  \RM_PagBank\Helpers\Recurring::subscriptionActionUrl('uncancel', $subscription)
                ];
                break;
            case 'SUSPENDED':
            case 'PENDING':
                // coming soon
//                $actions['pay'] = [
//                    'name' => __('Pagar', 'pagbank-connect'),
//                    'url' => \RM_PagBank\Helpers\Recurring::subscriptionActionUrl('pay', $subscription)
//                ];
                break;
            case 'CANCELED':
            default:
                break;
        }
        $actions['view'] = [
            'name' => __('Ver detalhes', 'pagbank-connect'),
            'url' => $this->getViewSubscriptionUrl($subscription)
        ];
        
        return $actions;
    }

}