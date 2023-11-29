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

    public static function getFriendlyType($type): string
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

    /**
     * Checks if the $cart or the current cart contains recurring products
     * @param WC_Cart|null $cart
     *
     * @return bool
     */
    public function isCartRecurring(WC_Cart $cart = null): bool
    {
        if (!$cart) $cart = WC()->cart;
        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'];
            if ($product->get_meta('_recurring_enabled') == 'yes') 
                return true;
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
            'daily' => __('Diário', Connect::DOMAIN),
            'weekly' => __('Semanal', Connect::DOMAIN),
            'monthly' => __('Mensal', Connect::DOMAIN),
            'yearly' => __('Anual', Connect::DOMAIN),
            'default' => __('Desconhecido', Connect::DOMAIN)
        ];
        
        if (in_array($frequency, array_keys($available)))
            return $available[$frequency];
            
        return $available['default'];
    }

    public function translateFrequencyTermsPlural($frequency)
    {
        $available = [
            'daily' => __('dias', Connect::DOMAIN),
            'weekly' => __('semanas', Connect::DOMAIN),
            'monthly' => __('meses', Connect::DOMAIN),
            'yearly' => __('anos', Connect::DOMAIN),
            'default' => __('desconhecido', Connect::DOMAIN)
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
        $msg = __('O valor de R$ %s será cobrado %s.', Connect::DOMAIN);
        $total = $cart->get_total();
        $frequency = __('mensalmente', Connect::DOMAIN);
        //get cicle and frequency from the first recurring product
        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'];
            if ($product->get_meta('_recurring_enabled') == 'yes'){
                $cycle = $product->get_meta('_frequency_cycle');
                $frequency = $product->get_meta('_frequency');
                if ($cycle == 1){
                    switch ($frequency){
                        case 'daily':
                            $frequency = __('diariamente', Connect::DOMAIN);
                            break 2;
                        case 'weekly':
                            $frequency = __('semanalmente', Connect::DOMAIN);
                            break 2;
                        case 'monthly':
                            $frequency = __('mensalmente', Connect::DOMAIN);
                            break 2;
                        case 'yearly':
                            $frequency = __('anualmente', Connect::DOMAIN);
                            break 2;
                    }
                }
                $frequency = sprintf(__('a cada %d %s', Connect::DOMAIN), $cycle, $this->translateFrequencyTermsPlural($frequency));
                break;       
            }
        }
        $msg = sprintf($msg, $total, $frequency);
        $initialFee = $product->get_meta('_initial_fee');
        if ($initialFee > 0){
            $msg .= ' ' . sprintf(__('Uma taxa de %s será adicionada à primeira cobrança.', Connect::DOMAIN), wc_price($initialFee));
        }
        
        $recurringNoticeDays = (int)Params::getConfig('recurring_notice_days', 0);
        if ($paymentMethod != 'creditcard' && $recurringNoticeDays > 0){
            switch ($paymentMethod){
                case 'pix':
                    $msg .= '<br/>' . sprintf(__('Um código PIX será enviado para seu e-mail %d dias antes de cada vencimento.', Connect::DOMAIN), $recurringNoticeDays);
                    break;
                case 'boleto':
                    $msg .= '<br/>' . sprintf(__('Um novo boleto será enviado para seu e-mail %d dias antes de cada vencimento.', Connect::DOMAIN), $recurringNoticeDays);
                    break;
            }
            $msg .= ' ' . __('O não pagamento dentro do prazo causará a suspensão da assinatura.', Connect::DOMAIN);
        }
        
        return $msg;
    }
    
}