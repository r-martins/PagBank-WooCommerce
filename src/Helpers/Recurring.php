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
     * Calculates the DateTime for the next billing date
     *
     * @param string $frequency Accepted values are: 'daily', 'weekly', 'monthly', 'yearly'
     * @param int $cycle 
     *
     * @return DateTime The next billing date GMT timezone
     * @throws Exception
     */
    public function calculateNextBillingDate(string $frequency, int $cycle): DateTime
    {
        $date = new DateTime('now', new DateTimeZone('GMT'));
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
     * @throws Exception
     */
    public function areBenefitsActive(\stdClass $subscription): bool
    {
        switch ($subscription->status) {
            case 'ACTIVE':
                return true;
            case 'PAUSED':
            case 'PENDING_CANCEL':
                $nextBilling = $subscription->next_billing_at;
                $now = new DateTime('now', new DateTimeZone('GMT'));
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
        $msg = __('O valor de R$ %s será cobrado %s.', 'pagbank-connect');
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
        $msg = sprintf($msg, wc_price($total), $frequency);
        $initialFee = $product->get_meta('_initial_fee');
        if ($initialFee > 0){
            $msg .= '<p> ' . sprintf(__('Uma taxa de %s foi adicionada à primeira cobrança.', 'pagbank-connect'), wc_price($initialFee)) . '</p>';;
        }
        
        $recurringNoticeDays = (int)Params::getConfig('recurring_notice_days', 0);
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
}