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

        $ids = array_map(function($order) {
            return $order->get_id();
        }, $orders);

        $ids_string = implode(', ', $ids);
        
        global $wpdb;
        //select from pagbank_recurring where initial order is one of those
        $table = $wpdb->prefix . 'pagbank_recurring';
        $subscriptions = $wpdb->get_results("SELECT * FROM $table WHERE initial_order_id IN ( $ids_string) ORDER BY id DESC");
        
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
                'recurring-id' => __('Identificador', Connect::DOMAIN),
                'status' => __('Status', Connect::DOMAIN),
                'created_at' => __('Data Inicial', Connect::DOMAIN),
                'recurring_type' => __('Tipo', Connect::DOMAIN),
                'recurring_amount' => __('Valor', Connect::DOMAIN),
                'subscription-actions' => __('Ações', Connect::DOMAIN),
            ]
        );
    }
    
    public function getViewSubscriptionUrl($subscription): string
    {
        return wc_get_endpoint_url('rm-pagbank-subscriptions-view', $subscription->id);
    }
    
    public function getSubscriptionActions($subscription): array
    {
        $actions = [];
        
        switch ($subscription->status) {
            case 'ACTIVE':
                $actions['cancel'] = [
                    'name' => __('Cancelar', Connect::DOMAIN),
                    'url' => '#'
                ];
                break;
            case 'PAUSED':
                $actions['reactivate'] = [
                    'name' => __('Resumir', Connect::DOMAIN),
                    'url' => '#'
                ];
                break;
            case 'PENDING_CANCEL':
                $actions['cancel'] = [
                    'name' => __('Suspender Cancelamento', Connect::DOMAIN),
                    'url' => '#'
                ];
                break;
            case 'SUSPENDED':
            case 'PENDING':
                $actions['pay'] = [
                    'name' => __('Pagar', Connect::DOMAIN),
                    'url' => '#'
                ];
                break;
            case 'CANCELED':
            default:
                break;
        }
        $actions['view'] = [
            'name' => __('Ver detalhes', Connect::DOMAIN),
            'url' => $this->getViewSubscriptionUrl($subscription)
        ];
        
        return $actions;
    }
    
    public function getFriendlyStatus($status): string
    {
        switch ($status) {
            case 'ACTIVE':
                return __('Ativo', Connect::DOMAIN);
            case 'PAUSED':
                return __('Pausado', Connect::DOMAIN);
            case 'PENDING_CANCEL':
                return __('Cancelamento Pendente', Connect::DOMAIN);
            case 'SUSPENDED':
                return __('Suspenso', Connect::DOMAIN);
            case 'PENDING':
                return __('Pendente', Connect::DOMAIN);
            case 'CANCELED':
                return __('Cancelado', Connect::DOMAIN);
            default:
                return __('Desconhecido', Connect::DOMAIN);
        }
    }
    public function getFriendlyType($type): string
    {
        switch (strtoupper($type)) {
            case 'DAILY':
                return __('Diário', Connect::DOMAIN);
            case 'WEEKLY':
                return __('Semanal', Connect::DOMAIN);
            case 'MONTHLY':
                return __('Mensal', Connect::DOMAIN);
            case 'YEARLY':
                return __('Anual', Connect::DOMAIN);
            default:
                return __('Desconhecido', Connect::DOMAIN);
        }
    }
    
    public function getFriendlyPaymentMethodName(string $method): string
    {
        switch ($method) {
            case 'boleto':
                return __('Boleto', Connect::DOMAIN);
            case 'pix':
                return __('Pix', Connect::DOMAIN);
            case 'credit_card':
                return __('Cartão de Crédito', Connect::DOMAIN);
            default:
                return __('Desconhecido', Connect::DOMAIN);
        }
    }
}