<?php
namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Class Recurring
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect
 */
class Recurring
{
    public function init()
    {
        if (Params::getConfig('recurring_enabled') != 'yes') return;

        add_action('woocommerce_product_data_panels', [$this, 'addRecurringTabContent']);
        add_action('woocommerce_process_product_meta', [$this, 'saveRecurringTabContent']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'addProductMetaToOrder'], 20, 1);
        add_filter('woocommerce_product_data_tabs', [$this, 'addProductRecurringTab']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'avoidOtherThanRecurringInCart'], 1, 2);
        
        //emails
        add_filter('woocommerce_email_classes', [$this, 'addEmails']);
        //region cron jobs
        add_action('rm_pagbank_cron_process_recurring_payments', [$this, 'processRecurringPayments']);
        if ( ! wp_next_scheduled('rm_pagbank_cron_process_recurring_payments') ) {
            wp_schedule_event(
                time(),
                Params::getConfig('recurring_process_frequency', 'hourly'),
                'rm_pagbank_cron_process_recurring_payments'
            );
        }
        //endregion
    }
    
    public function addProductRecurringTab($productTabs)
    {
        $productTabs['recurring_pagbank'] = [
            'label' => __('Assinatura PagBank', Connect::DOMAIN),
            'target' => 'recurring_pagbank',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 90,
        ];
        
        return $productTabs;    
    }

    public function addRecurringTabContent() {
        global $post;
        ?>
        <!-- id below must match target registered in above add_my_custom_product_data_tab function -->
        <div id="recurring_pagbank" class="panel woocommerce_options_panel">
            <?php
            woocommerce_wp_checkbox( array(
                'id'            => '_recurring_enabled',
                'wrapper_class' => 'show_if_simple',
                'label'         => __( 'Habilitar recorrência', Connect::DOMAIN ),
                'description'   => __( 'Habilitar', Connect::DOMAIN),
                'default'  		=> '0',
                'desc_tip'    	=> false,
            ) );
            woocommerce_wp_select([
                'id' => '_frequency',
                'label' => __('Frequência', Connect::DOMAIN),
                'options' => [
                    'daily'     => __('Diário', Connect::DOMAIN),
                    'weekly'    => __('Semanal', Connect::DOMAIN),
                    'monthly'    => __('Mensal', Connect::DOMAIN),
                    'yearly'    => __('Anual', Connect::DOMAIN),
                ],
                'desc_tip' => true,
                'value' => get_post_meta($post->ID, '_frequency', true),
            ]);
            woocommerce_wp_text_input([
                'id' => '_frequency_cycle',
                'label' => __('Ciclo', Connect::DOMAIN),
                'description' => __('Ex: Se Frequência fosse Diário e ciclo fosse 2, cobraria a cada 2 dias.', Connect::DOMAIN),
                'desc_tip' => true,
                'type' => 'number',
                'custom_attributes' => [
                    'min' => 1,
                    'step' => 1,
                ],
                'value' => get_post_meta($post->ID, '_frequency_cycle', true),
            ]);
            woocommerce_wp_text_input([
                'id' => '_initial_fee',
                'label' => __('Taxa inicial', Connect::DOMAIN),
                'description' => __('Use . como separador decimal.', Connect::DOMAIN),
                'desc_tip' => true,
                'value' => get_post_meta($post->ID, '_initial_fee', true),
            ]);
            ?>
            <p><?php echo __('Alterações realizadas aqui só afetarão futuras assinaturas.', Connect::DOMAIN);?></p>
        </div>
        <?php
    }
    
    public function saveRecurringTabContent($postId)
    {
        $recurringEnabled = isset($_POST['_recurring_enabled']) ? 'yes' : 'no';
        update_post_meta($postId, '_recurring_enabled', $recurringEnabled);
        
        update_post_meta($postId, '_frequency', sanitize_text_field($_POST['_frequency']));
        
        if ($recurringEnabled == 'yes') {
            $cycle = sanitize_text_field($_POST['_frequency_cycle']);
            $cycle = max($cycle, 1);
            
            $initial = sanitize_text_field($_POST['_initial_fee']);
            $initial = str_replace(',', '.', $initial);
            $initial = floatval(number_format(max(0, $initial), 2, '.', ''));
            update_post_meta($postId, '_frequency_cycle', $cycle);
            update_post_meta($postId, '_initial_fee', $initial);
        }
    }
    
    public function avoidOtherThanRecurringInCart($canBeAdded, $productId)
    {
        $cart = WC()->cart;
        $cartItems = $cart->get_cart();
        
        $product = wc_get_product($productId);
        $productIsRecurring = $product->get_meta('_recurring_enabled') == 'yes';
        $recurringHelper = new RecurringHelper();
        
        if (!empty($cartItems) && ($productIsRecurring || $recurringHelper->isCartRecurring())) {
            wc_add_notice(__('Produtos recorrentes ou assinaturas devem ser comprados separadamente. Remova os itens recorrentes do carrinho antes de prosseguir.', Connect::DOMAIN), 'error');
            $canBeAdded = false;
        }
        
        return $canBeAdded;
        
    }

    /**
     * Process the initial response from the given order and add the recurring data to the database
     * @param $order
     *
     * @return bool
     * @throws Exception
     */
    public function processInitialResponse($order): bool
    {
        global $wpdb;
        
        $recHelper = new RecurringHelper();
        
        $frequency = $order->get_meta('_recurring_frequency');
        $cycle = (int)$order->get_meta('_recurring_cycle');
        $nextBill = $recHelper->calculateNextBillingDate($frequency, $cycle);

        $paymentInfo = $this->getPaymentInfo($order);
        $statusFromOrder = $recHelper->getStatusFromOrder($order);
        $success = $wpdb->insert($wpdb->prefix.'pagbank_recurring', [
            'initial_order_id' => $order->get_id(),
            'recurring_amount' => $order->get_total(),
            'status'           => $statusFromOrder,
            'recurring_type'   => $frequency,
            'recurring_cycle'  => $cycle,
            'created_at'       => gmdate('Y-m-d H:i:s'),
            'updated_at'       => gmdate('Y-m-d H:i:s'),
            'next_bill_at'     => $nextBill->format('Y-m-d H:i:s'),
            'payment_info'     => json_encode($paymentInfo),
        ], ['%d', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
        
        if ($success !== false && $statusFromOrder == 'ACTIVE') {
            $subId = $wpdb->insert_id;
            $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE id = 0{$subId}";
            $wpdb->query($sql);
            $subscription = $wpdb->get_row($sql);
            //send welcome e-mail
            do_action('pagbank_recurring_subscription_created_notification', $subscription, $order);
        }
        
        return $success !== false;
    }
    
    public function processRecurringPayments()
    {
        global $wpdb;
        //Get all recurring orders that are due or past due and active
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE status = 'ACTIVE' AND next_bill_at <= '$now'";
        $subscriptions = $wpdb->get_results($sql);
        foreach ($subscriptions as $subscription) {
            $recurringOrder = new Connect\Recurring\RecurringOrder($subscription);
            $recurringOrder->createRecurringOrderFromSub();
        }
    }
    
    public function addProductMetaToOrder($orderId)
    {
        $recHelper = new RecurringHelper();
        
        if (! $recHelper->isCartRecurring()) 
            return;
        
        $order = wc_get_order($orderId);
        foreach ($order->get_items() as $item){
            $originalItem = wc_get_product($item->get_product_id());
            if ($originalItem->get_meta('_recurring_enabled') == 'yes'){
                $order->update_meta_data('_recurring_frequency', $originalItem->get_meta('_frequency'));
                $order->update_meta_data('_recurring_cycle', $originalItem->get_meta('_frequency_cycle'));
                $order->update_meta_data('_recurring_initial_fee', $originalItem->get_meta('_initial_fee'));
                $order->save();
            }
        }
    }

    /**
     * Returns the payment info for the given order to be stored in the subscription and charge the customer
     * in the future
     *
     * @param WC_Order$order
     *
     * @return array The payment info to be stored
     */
    public function getPaymentInfo(WC_Order $order):array
    {
        $paymentMethod = $order->get_meta('pagbank_payment_method');
        $paymentInfo = [
            'method' => $paymentMethod,
        ];
        
        if ($paymentMethod == 'credit_card') {
            $initialChargeInfo = $order->get_meta('_pagbank_order_charges');
            if ( ! isset($initialChargeInfo[0])){
                Functions::log('Não foi possível carregar as informações do pagamento inicial para gerar os '
                    .'detalhes da recorrência.', 'critical', ['order id' => $order->get_id()] );
                return [];
            }
            $chargeInfo = $initialChargeInfo[0]['payment_method'];
            $paymentInfo['card'] = [
                'holder_name' => $chargeInfo['card']['holder']['name'],
                'number' => $chargeInfo['card']['first_digits'] . '******' . $chargeInfo['card']['last_digits'],
                'expiration_date' => $chargeInfo['card']['exp_month'] . '/' .
                    $chargeInfo['card']['exp_year'],
                'brand' => $chargeInfo['card']['brand'],
                'id' => $chargeInfo['card']['id']
            ];
        }
        
        return $paymentInfo;
    }

    /**
     * Adds transactional e-mail templates for recurring orders
     * @param array $emails
     *
     * @return array
     */
    public function addEmails(array $emails):array
    {
        $emails['RM_PagBank_Canceled_Subscription'] = include __DIR__ . '/Recurring/Emails/CanceledSubscription.php';
        $emails['RM_PagBank_New_Subscription'] = include __DIR__ . '/Recurring/Emails/NewSubscription.php';
        $emails['RM_PagBank_Paused_Subscription'] = include __DIR__ . '/Recurring/Emails/PausedSubscription.php';
        $emails['RM_PagBank_Suspended_Subscription'] = include __DIR__ . '/Recurring/Emails/SuspendedSubscription.php';
        
        return $emails;
    }

    /**
     * Cancels the specified subscription
     * @param stdClass $subscription The subscription to be canceled (row from pagbank_recurring table)
     * @param string   $reason     The reason for cancellation (will be visible to the customer)
     * @param string   $reasonType The reason type (CUSTOMER or FAILURE)
     *
     * @return bool
     */
    public function cancelSubscription(\stdClass $subscription, string $reason, string $reasonType): bool
    {
        global $wpdb;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        
        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring',
            ['canceled_at' => gmdate('Y/m/d H:i:s'), 'status' => 'CANCELED', 'canceled_reason' => $reason],
            ['id' => $subscription->id],
            ['%s', '%s', '%s'],
            ['id' => intval($subscription->id)]
        );
        
        if ($update)
        {
            $reasonType = strtoupper($reasonType);
            switch ($reasonType) {
                case 'CUSTOMER':
                    do_action(
                        'pagbank_recurring_subscription_canceled_by_customer_notification',
                        $subscription,
                        $initialOrder
                    );
                    break;
                case 'FAILURE':
                    do_action(
                        'pagbank_recurring_subscription_canceled_by_failure_notification',
                        $subscription,
                        $initialOrder
                    );
            }
        }
        return $update > 0;
    }
}