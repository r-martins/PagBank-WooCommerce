<?php

namespace RM_PagBank\Helpers;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use RM_PagBank\Connect;
use stdClass;
use WC_Cart;
use WC_Order;
use RM_PagBank\Helpers\Params;

class Recurring
{
    public static function getFriendlyStatus($status): string
    {
        switch ($status) {
            case 'ACTIVE':
                return __('Ativo', 'pagbank-connect');
            case 'PAUSED':
                return __('Pausado', 'pagbank-connect');
            case 'PENDING_CANCEL':
                return __('Cancelamento Pendente', 'pagbank-connect');
            case 'SUSPENDED':
                return __('Suspenso', 'pagbank-connect');
            case 'PENDING':
                return __('Pendente', 'pagbank-connect');
            case 'CANCELED':
                return __('Cancelado', 'pagbank-connect');
            case 'COMPLETED':
                return __('Finalizado', 'pagbank-connect');
            default:
                return __('Desconhecido', 'pagbank-connect');
        }
    }
    
    public static function getAllStatuses()
    {
        return [
            'ACTIVE' => __('Ativo', 'pagbank-connect'),
            'PAUSED' => __('Pausado', 'pagbank-connect'),
            'PENDING_CANCEL' => __('Cancelamento Pendente', 'pagbank-connect'),
            'SUSPENDED' => __('Suspenso', 'pagbank-connect'),
            'PENDING' => __('Pendente', 'pagbank-connect'),
            'CANCELED' => __('Cancelado', 'pagbank-connect'),];
    }

    public static function getFriendlyType($type): string
    {
        switch (strtoupper($type)) {
            case 'DAILY':
                return __('Diário', 'pagbank-connect');
            case 'WEEKLY':
                return __('Semanal', 'pagbank-connect');
            case 'MONTHLY':
                return __('Mensal', 'pagbank-connect');
            case 'YEARLY':
                return __('Anual', 'pagbank-connect');
            default:
                return __('Desconhecido', 'pagbank-connect');
        }
    }

    /**
     * Checks if the $cart or the current cart contains recurring products
     * @param WC_Cart|null $cart
     *
     * @return bool
     */
    public function isCartRecurring(WC_Cart $cart = null): bool
    {
        //checks if pagbank recurring is enabled
        $isRecurringEnabled = Params::getRecurringConfig('recurring_enabled', 'no') == 'yes';
        if (!$isRecurringEnabled) {
            return false;
        }
        
        //avoids warnings with plugins like Mercado Pago that calls things before WP is loaded
        if (!did_action('woocommerce_load_cart_from_session')) {
            return false;
        }
        
        if (!$cart) {
            $cart = WC()->cart;
        }
        
        if (!$cart) {
            return false;
        }
        
        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'];
            if ($product->get_meta('_recurring_enabled') == 'yes') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Checks if the $cart or the current cart contains trial recurring products and returns the trial length
     * @param WC_Cart|null $cart
     *
     * @return bool|int
     */
    public function getCartRecurringTrial(WC_Cart $cart = null)
    {
        //avoids warnings with plugins like Mercado Pago that calls things before WP is loaded
        if (!did_action('woocommerce_load_cart_from_session')) {
            return false;
        }

        if (!$cart) {
            $cart = WC()->cart;
        }

        if (!$cart) {
            return false;
        }

        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'];
            if ($product->get_meta('_recurring_trial_length') > 0 && $product->get_meta('_recurring_enabled') == 'yes') {
                return (int) $product->get_meta('_recurring_trial_length');
            }
        }

        return false;
    }

    /**
     * Calculates the DateTime for the next billing date
     *
     * @param string $frequency Accepted values are: 'daily', 'weekly', 'monthly', 'yearly'
     * @param int $cycle
     * @param null $trialLenght
     * @return DateTime The next billing date GMT timezone
     * @throws Exception
     */
    public function calculateNextBillingDate(string $frequency, int $cycle, $trialLenght = null): DateTime
    {
        $date = new DateTime('now', new DateTimeZone('GMT'));

        if ($trialLenght){
            $interval = new DateInterval('P' . $trialLenght . 'D');
            return $date->add($interval);
        }

        switch ($frequency){
            case 'daily':
                $frequency = 'D';
                break;
            case 'weekly':
                $frequency = 'W';
                break;
            case 'monthly':
                $frequency = 'M';
                break;
            case 'yearly':
                $frequency = 'Y';
                break;
        }
        $interval = new DateInterval('P' . $cycle . $frequency);
        return $date->add($interval);
    }

    public function getStatusFromOrder(WC_Order $order): string
    {
        switch ($order->get_status()){
            case 'processing':
            case 'completed':
                return 'ACTIVE';
            case 'cancelled':
                return 'CANCELED';
            case 'on-hold':
            default:
                return 'PENDING';
        }
    }
    
    public function translateFrequency($frequency)
    {
        $available = [
            'daily' => __('Diário', 'pagbank-connect'),
            'weekly' => __('Semanal', 'pagbank-connect'),
            'monthly' => __('Mensal', 'pagbank-connect'),
            'yearly' => __('Anual', 'pagbank-connect'),
            'default' => __('Desconhecido', 'pagbank-connect')
        ];
        
        if (in_array($frequency, array_keys($available)))
            return $available[$frequency];
            
        return $available['default'];
    }

    public function translateFrequencyTermsPlural($frequency)
    {
        $available = [
            'daily' => __('dias', 'pagbank-connect'),
            'weekly' => __('semanas', 'pagbank-connect'),
            'monthly' => __('meses', 'pagbank-connect'),
            'yearly' => __('anos', 'pagbank-connect'),
            'default' => __('desconhecido', 'pagbank-connect')
        ];

        if (in_array($frequency, array_keys($available)))
            return $available[$frequency];

        return $available['default'];
    }

    /**
     * In case of a subscription for digital content you can check if the user are still eligible to access the content
     * It will be based on the status of the subscription. In cases where the subscription is pending or paused
     * this method will see if the next billing date is in the future.
     *
     * @param stdClass $subscription
     *
     * @return bool
     */
    public function areBenefitsActive(\stdClass $subscription): bool
    {
        switch ($subscription->status) {
            case 'ACTIVE':
                return true;
            case 'PAUSED':
            case 'PENDING_CANCEL':
                $nextBilling = $subscription->next_bill_at;
                try {
                    $now = new DateTime('now', new DateTimeZone('GMT'));
                } catch (Exception $e) {
                    return false;
                }
                return $nextBilling > $now->format('Y-m-d H:i:s');
            case 'PENDING':
            case 'SUSPENDED':
            case 'CANCELED':
                default:
                    return false;
                
        }
    }
    
    public function getRecurringTermsFromCart($paymentMethod, WC_Cart $cart = null): string
    {
        if (!$cart) $cart = WC()->cart;
        if (!$cart) return '';
        $msgDefault = __('O valor de %s será cobrado %s.', 'pagbank-connect');
        $total = $cart->get_total('edit');
        $frequency = __('mensalmente', 'pagbank-connect');
        $initialFee = 0;
        //get cicle and frequency from the first recurring product
        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'];
            if ($product->get_meta('_recurring_enabled') == 'yes'){
                $cycle = $product->get_meta('_frequency_cycle');
                $frequency = $product->get_meta('_frequency');
                $initialFee = (float)$product->get_meta('_initial_fee');
                $total -= $initialFee * $cartItem['quantity'];
                if ($cycle == 1){
                    switch ($frequency){
                        case 'daily':
                            $frequency = __('diariamente', 'pagbank-connect');
                            break 2;
                        case 'weekly':
                            $frequency = __('semanalmente', 'pagbank-connect');
                            break 2;
                        case 'monthly':
                            $frequency = __('mensalmente', 'pagbank-connect');
                            break 2;
                        case 'yearly':
                            $frequency = __('anualmente', 'pagbank-connect');
                            break 2;
                    }
                }
                $frequency = sprintf(__('a cada %d %s', 'pagbank-connect'), $cycle, $this->translateFrequencyTermsPlural($frequency));
                break;       
            }
        }

        if (!isset($product)) {
            return '';
        }

        $msg = sprintf($msgDefault, wc_price($total), $frequency);

        $hasTrial = $this->getCartRecurringTrial($cart);
        $hasDiscount = $this->hasDiscount($product);
        if ($hasTrial || $hasDiscount) {
            $total = $cart->get_shipping_total('edit') ?? 0;
            foreach ($cart->get_cart() as $cartItem) {
                $product = $cartItem['data'];
                $total += $product->get_data()['price'];
            }
            $msg = sprintf($msgDefault, wc_price($total), $frequency);
        }

        if ($hasTrial){
            $msgTrial = __('O valor de %s será cobrado %s após o período de testes de %d dias.', 'pagbank-connect');
            $msg = sprintf($msgTrial, wc_price($total), $frequency, $hasTrial);
        }

        if ($hasDiscount) {
            $total -= (float)$product->get_meta('_recurring_discount_amount');
        }

        if ($hasTrial && $hasDiscount){
            $msg .= ' ';
            $msgDiscount = sprintf(
                __('A próxima cobrança será de %s, aplicado o desconto.', 'pagbank-connect'),
                wc_price($total)
            );

            if ($product->get_meta('_recurring_discount_cycles') > 1) {
                $msgDiscount = sprintf(
                    __('Durante os %s ciclos com desconto, a cobrança será de %s.', 'pagbank-connect'),
                    $product->get_meta('_recurring_discount_cycles'),
                    wc_price($total)
                );
            }

            $msg .= $msgDiscount;
        }

        if (!$hasTrial && $hasDiscount) {
            $msg .= ' ';
            $msgDiscount = sprintf(
                __('A primeira cobrança será de %s, aplicado o desconto.', 'pagbank-connect'),
                wc_price($total)
            );

            if ($product->get_meta('_recurring_discount_cycles') > 1) {
                $msgDiscount = sprintf(
                    __('Durante os %s ciclos com desconto, a cobrança será de %s.', 'pagbank-connect'),
                    $product->get_meta('_recurring_discount_cycles'),
                    wc_price($total)
                );
            }

            $msg .= $msgDiscount;
        }

        $initialFee = $product->get_meta('_initial_fee');
        if ($initialFee > 0){
            $msg .= '<p> ' . sprintf(__('Uma taxa de %s foi adicionada à primeira cobrança.', 'pagbank-connect'), wc_price($initialFee)) . '</p>';;
        }
        
        $recurringNoticeDays = (int)Params::getRecurringConfig('recurring_notice_days', 0);
        if ($paymentMethod != 'creditcard' && $recurringNoticeDays > 0){
            switch ($paymentMethod){
                case 'pix':
                    $msg .= '<p>' . sprintf(__('Um código PIX será enviado para seu e-mail %d dias antes de cada vencimento.', 'pagbank-connect'), $recurringNoticeDays) . '</p>';
                    break;
                case 'boleto':
                    $msg .= '<p>' . sprintf(__('Um novo boleto será enviado para seu e-mail %d dias antes de cada vencimento.', 'pagbank-connect'), $recurringNoticeDays) . '</p>';
                    break;
            }
            $msg .= ' ' . __('O não pagamento dentro do prazo causará a suspensão da assinatura.', 'pagbank-connect');
        }

        $maxCycles = (int)$product->get_meta('_recurring_max_cycles');
        if ($maxCycles > 0){
            $msg .= '<p>' . sprintf(__(' Esta assinatura será cobrada %s por %d ciclos.', 'pagbank-connect'),$frequency, $maxCycles) . '</p>';
        }
        
        return $msg;
    }

    public static function getAdminSubscriptionDetailsUrl($order)
    {
        global $wpdb;

        $parentId = $order->get_parent_id('edit');
        $orderId = $parentId > 0 ? $parentId : $order->get_id();
        
        $table = $wpdb->prefix . 'pagbank_recurring';
        $sql = "SELECT * FROM `$table` WHERE initial_order_id = 0%d";
        $subscription = $wpdb->get_row($wpdb->prepare($sql, $orderId));
        if ( ! $subscription) return '#';
        return admin_url('admin.php?page=rm-pagbank-subscriptions-view&action=view&id=' . $subscription->id);

    }

    public function getRecurringAmountFromOrderItems(WC_Order $order): float
    {
        $total = 0;
        $shipping_total = $order->get_shipping_total() ?? 0;
        foreach ($order->get_items() as $item){
            $product = $item->get_product();
            if ($product->get_meta('_recurring_enabled') == 'yes'){
                $total += $product->get_price();
            }
        }
        return $total + $shipping_total;
    }

    public function hasSubscriptionChargeRemaining($subscription): bool
    {
        $maxCycles = (int)$subscription->recurring_max_cycles;
        if (!$maxCycles) {
            return true;
        }

        $initialOrder = wc_get_order($subscription->initial_order_id);
        $orders = wc_get_orders([
            'parent' => $subscription->initial_order_id,
        ]);

        $ordersNumber = count($orders);

        // the first order is the initial order, so we need to discount it from the count of orders if it is not trial
        if ($initialOrder->get_meta('_pagbank_recurring_trial_length') < 1){
            $ordersNumber = $ordersNumber + 1;
        }

        if ($ordersNumber < $maxCycles){
            return true;
        }

        return false;
    }

    public function hasSubscriptionDiscountRemaining($subscription): bool
    {
        $discount = (float)$subscription->recurring_discount_amount;
        $discountCycles = (int)$subscription->recurring_discount_cycles;
        if (!$discount || !$discountCycles) {
            return false;
        }

        $initialOrder = wc_get_order($subscription->initial_order_id);
        $orders = wc_get_orders([
            'parent' => $subscription->initial_order_id,
        ]);

        $ordersNumber = count($orders);

        // the first order is the initial order, so we need to discount it from the count of orders if it is not trial
        if ($initialOrder->get_meta('_pagbank_recurring_trial_length') < 1){
            $ordersNumber = $ordersNumber + 1;
        }

        if ($ordersNumber < $discountCycles){
            return true;
        }

        return false;
    }

    public function hasDiscount($product): bool
    {
        return (float)$product->get_meta('_recurring_discount_amount') > 0
        && (int)$product->get_meta('_recurring_discount_cycles') > 0;
    }
    


    /**
     * Checks if the user has access to restricted content
     *
     * @param int   $userId
     * @param int   $pageId     
     * @param array $categoriesIds array of categories ids
     *
     * @return bool
     */
    public function canAccessRestrictedContent(int $userId, int $pageId, array $categoriesIds): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pagbank_content_restriction';
        $sql = "SELECT * FROM `$table` WHERE user_id = %d";
        $restrictions = $wpdb->get_row($wpdb->prepare($sql, $userId));
        if (!$restrictions) return false;
        
        // get pages and categories that the user has access
        $pages = explode(',', $restrictions->pages ?? '');
        $categories = explode(',', $restrictions->categories ?? '');
        
        //see if $pageId or $categoriesIds are in the user's access list
        if (in_array($pageId, $pages) || count(array_intersect($categoriesIds, $categories)) > 0){
            return true;
        }
    
        return false;
    }

    /**
     * @return bool
     */
    public function isSubscriptionUpdatePage(): bool
    {
        global $wp;
        $endpoint = $wp->request;
        return stripos($endpoint, 'rm-pagbank-subscriptions-update') !== false;
    }
}