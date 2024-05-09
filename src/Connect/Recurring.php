<?php
namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use stdClass;
use WC_Emails;
use WC_Order;
use wpdb;

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
        if (Params::getConfig('recurring_enabled') != 'yes') {
            return;
        }

        //region admin management
        add_action('woocommerce_product_data_panels', [$this, 'addRecurringTabContent']);
        add_action('woocommerce_process_product_meta', [$this, 'saveRecurringTabContent']);
        add_filter('woocommerce_product_data_tabs', [$this, 'addProductRecurringTab']);
        //endregion

        //region frontend initial-order flow
        add_action('woocommerce_checkout_update_order_meta', [$this, 'addProductMetaToOrder'], 20, 1);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'avoidOtherThanRecurringInCart'], 1, 2);
        add_filter('woocommerce_checkout_registration_required', [$this, 'disableGuestCheckoutForRecurringOrder'], 1, 1);
        //endregion
        
        //emails
        add_filter('woocommerce_email_classes', [$this, 'addEmails']);
        WC_Emails::instance();
        
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
        
        //region frontend subscription management
        add_filter('woocommerce_account_menu_items', [$this, 'addSubscriptionManagementMenuItem'], 10, 1);
        add_action('woocommerce_account_rm-pagbank-subscriptions_endpoint', [$this, 'addManageSubscriptionContent']);
        add_action('woocommerce_account_rm-pagbank-subscriptions-view_endpoint', [$this, 'addManageSubscriptionViewContent']);
        add_action('woocommerce_api_rm-pagbank-subscription-edit', [$this, 'addManageSubscriptionEditAction']);
        add_action('rm_pagbank_view_subscription', [$this, 'subscriptionDetailsTable'], 10, 1);
        add_action('rm_pagbank_recurring_details_subscription_table_payment_info', [$this, 'getPaymentInfoRows'], 10, 1);
        add_action('rm_pagbank_view_subscription_actions', [$this, 'getSubscriptionActionButtons'], 10, 1);
        add_action('rm_pagbank_view_subscription_order_list', [$this, 'getSubscriptionOrderList'], 10, 1);
        add_filter('the_title', [$this, 'recurring_endpoint_title'], 10, 2 );
        add_filter('rm_pagbank_account_recurring_actions', [$this, 'filterAllowedActions'], 10, 2);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'filterRecurringOrderActions'], 10, 2);
        //endregion
        
        add_action('woocommerce_cart_calculate_fees', [$this, 'addInitialFeeToCart'], 10, 1);
    }
    
    public function addInitialFeeToCart($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Defina a taxa extra
        $extra_fee = 0;

        // Percorra cada produto no carrinho
        foreach($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];

            // Verifique a propriedade do produto
            if($product->get_meta('_initial_fee') && $product->get_meta('_recurring_enabled') == 'yes') {
                // Adicione a taxa extra se a propriedade do produto for verdadeira
                $extra_fee += floatval($product->get_meta('_initial_fee'));
            }
        }

        // Adicione a taxa extra ao carrinho se for maior que zero
        if($extra_fee > 0) {
            $cart->add_fee(__('Taxa Inicial', 'pagbank-connect'), $extra_fee);
        }
    }
    
    
    public function addProductRecurringTab($productTabs)
    {
        $productTabs['recurring_pagbank'] = [
            'label' => __('Assinatura PagBank', 'pagbank-connect'),
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
                'label'         => __( 'Habilitar recorrência', 'pagbank-connect' ),
                'description'   => __( 'Habilitar', 'pagbank-connect'),
                'default'  		=> '0',
                'desc_tip'    	=> false,
            ) );
            woocommerce_wp_select([
                'id' => '_frequency',
                'label' => __('Frequência', 'pagbank-connect'),
                'options' => [
                    'daily'     => __('Diário', 'pagbank-connect'),
                    'weekly'    => __('Semanal', 'pagbank-connect'),
                    'monthly'    => __('Mensal', 'pagbank-connect'),
                    'yearly'    => __('Anual', 'pagbank-connect'),
                ],
                'desc_tip' => true,
                'value' => get_post_meta($post->ID, '_frequency', true),
            ]);
            woocommerce_wp_text_input([
                'id' => '_frequency_cycle',
                'label' => __('Ciclo', 'pagbank-connect'),
                'description' => __('Ex: Se Frequência fosse Diário e ciclo fosse 2, cobraria a cada 2 dias.', 'pagbank-connect'),
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
                'label' => __('Taxa inicial', 'pagbank-connect'),
                'description' => __('Use . como separador decimal.', 'pagbank-connect'),
                'desc_tip' => true,
                'value' => get_post_meta($post->ID, '_initial_fee', true),
            ]);
            ?>
            <p><?php echo esc_html( 
                    __('Alterações realizadas aqui só afetarão futuras assinaturas.', 'pagbank-connect') 
                );?></p>
        </div>
        <?php
    }
    
    public function saveRecurringTabContent($postId)
    {
        $recurringEnabled = isset($_POST['_recurring_enabled']) ? 'yes' : 'no';
        update_post_meta($postId, '_recurring_enabled', $recurringEnabled);
        
        $frequency = isset($_POST['_frequency']) ? sanitize_text_field($_POST['_frequency']) : 'monthly'; //phpcs:ignore WordPress.Security.NonceVerification
        update_post_meta($postId, '_frequency', $frequency);
        
        if ($recurringEnabled == 'yes') {
            $cycle = isset($_POST['_frequency_cycle']) ? sanitize_text_field($_POST['_frequency_cycle']) : 1;
            $cycle = max($cycle, 1);
            
            $initial = sanitize_text_field($_POST['_initial_fee'] ?? 0);
            $initial = floatval(str_replace(',', '.', $initial));
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
            wc_add_notice(__('Produtos recorrentes ou assinaturas devem ser comprados separadamente. Remova os itens recorrentes do carrinho antes de prosseguir.', 'pagbank-connect'), 'error');
            $canBeAdded = false;
        }
        
        return $canBeAdded;
        
    }

    /**
     * Disables guest checkout if the cart contains recurring products regardless of the settings
     *
     * @param bool $mustBeRegistered
     *
     * @return bool
     */
    public function disableGuestCheckoutForRecurringOrder(bool $mustBeRegistered): bool
    {
        $recHelper = new RecurringHelper();
        if ($recHelper->isCartRecurring()) {
            $mustBeRegistered = true;
        }
        return $mustBeRegistered;
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
        $initialFee = (float)$order->get_meta('_recurring_initial_fee');

        $paymentInfo = $this->getPaymentInfo($order);
        $statusFromOrder = $recHelper->getStatusFromOrder($order);
        $success = $wpdb->insert($wpdb->prefix.'pagbank_recurring', [
            'initial_order_id' => $order->get_id(),
            'recurring_amount' => $order->get_total() - $initialFee,
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
            $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE id = %d";
//            $wpdb->query($wpdb->prepare($sql, $subId));
            $subscription = $wpdb->get_row($wpdb->prepare($sql, $subId));
            //send welcome e-mail
            do_action('pagbank_recurring_subscription_created_notification', $subscription, $order);
        }
        
        return $success !== false;
    }
    
    public function processRecurringPayments(\stdClass $subscription = null)
    {
        global $wpdb;
        //Get all recurring orders that are due or past due and active
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE ";
        $sql .= $subscription == null 
            ? "status = 'ACTIVE' AND next_bill_at <= '%s'" 
            : "id = 0%d";
        $nowOrId = $subscription == null ? $now : $subscription->id;
        $sql = $wpdb->prepare($sql, $nowOrId);
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
            return true;
        
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
            $initialChargeInfo = $order->get_meta('pagbank_order_charges');
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
    public static function addEmails(array $emails):array
    {
        $emails['RM_PagBank_Canceled_Subscription'] = include __DIR__ . '/Recurring/Emails/CanceledSubscription.php';
        $emails['RM_PagBank_New_Subscription'] = include __DIR__ . '/Recurring/Emails/NewSubscription.php';
        $emails['RM_PagBank_Paused_Subscription'] = include __DIR__ . '/Recurring/Emails/PausedSubscription.php';
        $emails['RM_PagBank_Suspended_Subscription'] = include __DIR__ . '/Recurring/Emails/SuspendedSubscription.php';
        
        return $emails;
    }

    
    
    public function addSubscriptionManagementMenuItem($items)
    {
        $items['rm-pagbank-subscriptions'] = __('Assinaturas', 'pagbank-connect');
        return $items;
    }
    
    public static function addManageSubscriptionEndpoints()
    {
        add_rewrite_endpoint('rm-pagbank-subscriptions', EP_PAGES);
        add_rewrite_endpoint('rm-pagbank-subscriptions-view', EP_PAGES);
        add_rewrite_endpoint('rm-pagbank-subscriptions-edit', EP_ROOT | EP_PAGES);
    }
    
    public function addManageSubscriptionContent()
    {
        $recDash = new Connect\Recurring\RecurringDashboard();
        $mySubs = $recDash->getMySubscriptions();
        //get 
        wc_get_template('recurring/my-account/dashboard.php', [
                'subscriptions' => $mySubs,
                'dashboard'  => $recDash
        ], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);
    }
    
    public function addManageSubscriptionViewContent($subscriptionId)
    {
        $subscriptionId = intval($subscriptionId);
        $subscription = $this->getSubscription($subscriptionId);
        if (is_null($subscription)) {
            wc_get_template('recurring/my-account/subscription-not-found.php', [], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);
            return;
        }

        if (isset($_GET['action'])) {
            do_action('rm_pagbank_manage_subscription_action', wp_slash($_GET['action']));
            $subscription = $this->getSubscription($subscriptionId);
        }
        
        $order = wc_get_order($subscription->initial_order_id);
        if ($order->get_customer_id('edit') !== get_current_user_id()) {
            wc_get_template(
                'recurring/my-account/subscription-not-found.php',
                [],
                Connect::DOMAIN,
                WC_PAGSEGURO_CONNECT_TEMPLATES_DIR
            );
            return;
        }
        
        $dash = new Connect\Recurring\RecurringDashboard();
        
        wc_get_template('recurring/my-account/view-subscription.php', [
                'subscription' => $subscription,
                'initialOrder' => $order,
                'dashboard' => $dash
        ], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);
    }    
    
    public function addSubscriptionManagementTitle($title, $postid)
    {
        if ($title == 'My account'){
            return 'Assinaturas';
        }
        
        return $title;
    }

    /**
     * Replace a page title with the endpoint title.
     *
     * @param  string $title Post title.
     * @return string
     */
    public function recurring_endpoint_title( $title ) {
        global $wp_query;

        if ( ! is_null( $wp_query ) && ! is_admin() && is_main_query() && in_the_loop() && is_page() && $this->isRecurringEndpoint() ) {
            $action         = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
            $endpoint_title = $this->getEndpointTitle( $action );
            $title          = $endpoint_title ? $endpoint_title : $title;

            remove_filter( 'the_title', 'recurring_endpoint_title' );
        }

        return $title;
    }
    
    public static function isRecurringEndpoint()
    {
        global $wp;
        $pbEndpoints = [
            'my-account/rm-pagbank-subscriptions',
            'my-account/rm-pagbank-subscriptions-view',
            'my-account/rm-pagbank-subscriptions-edit',
        ];
        foreach ($pbEndpoints as $endpoint) {
            if (stripos($wp->request, $endpoint) !== false)
                return true;
        }
        return false;
    }
    
    public function getEndpointTitle(  $action )
    {
        global $wp;
        
        $title = '';
        $endpoint = $wp->request;
        switch ($endpoint) {
            case stripos($endpoint, 'my-account/rm-pagbank-subscriptions-view') !== false:
                $id = esc_html($wp->query_vars['rm-pagbank-subscriptions-view']);
                $title = sprintf(__('Assinatura #%d', 'pagbank-connect'), $id);
                break;
            case stripos($endpoint, 'my-account/rm-pagbank-subscriptions') !== false:
                $title = __('Minhas Assinaturas', 'pagbank-connect');
                break;
        }
        
        return $title;
    }
    
    public function addManageSubscriptionViewEndpoint($actions, $order)
    {
        $actions['view-subscription'] = [
                'url' => wc_get_endpoint_url('rm-pagbank-subscriptions', $order->get_id()),
                'name' => __('Ver assinatura', 'pagbank-connect'),
        ];
        return $actions;
    }

    /**
     * Returns the subscription with the given id
     * @param int $id
     *
     * @return stdClass|null
     */
    public function getSubscription(int $id): ?\stdClass
    {
        global $wpdb;
        $query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE id = %d", $id );
        return $wpdb->get_row( $query );
    }
    
    public function subscriptionDetailsTable($subscription)
    {
        wc_get_template('recurring/subscription-details.php', [
            'subscription' => $subscription,
        ], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);;
    }
    
    public function getPaymentInfoRows($subscription)
    {
        wc_get_template('recurring/my-account/subscription-payment-info-rows.php', [
            'subscription' => $subscription,
        ], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);
    }
    
    public function getSubscriptionActionButtons($subscription)
    {
        wc_get_template('recurring/my-account/subscription-action-buttons.php', [
            'subscription' => $subscription,
        ], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);
    }
    
    public function getSubscriptionOrderList($subscription)
    {
        global $wpdb;
        $orders = wc_get_orders([
                'parent' => $subscription->initial_order_id,
        ]);
        $customer = new stdClass();
        $customer->orders = $orders;
        $customer->max_num_pages = 0;
        
        wc_get_template('templates/myaccount/orders.php', [
            'has_orders' => count($orders) > 0,
            'customer_orders' => $customer,
            'wp_button_class' => wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '',
        ], 'woocommerce', plugin_dir_path( WC_PLUGIN_FILE ) );
    }
    
    public function filterAllowedActions($actions, $subscription)
    {
        switch ($subscription->status){
            case 'PAUSED':
                unset($actions['pause']);
                unset($actions['activate']);
                unset($actions['uncancel']);
                break;
            case 'ACTIVE':
                unset($actions['unpause']);
                unset($actions['uncancel']);
                unset($actions['activate']);
                break;
            case 'CANCELED':
                unset($actions['cancel']);
                unset($actions['uncancel']);
                unset($actions['pause']);
                unset($actions['unpause']);
                unset($actions['activate']);
                break;
            case 'SUSPENDED':
                unset($actions['cancel']);
                unset($actions['uncancel']);
                unset($actions['pause']);
                unset($actions['unpause']);
                break;
            case 'PENDING':
                unset($actions['uncancel']);
                unset($actions['unpause']);
                break;
            case 'PENDING_CANCEL':
                unset($actions['cancel']);
                unset($actions['pause']);
                unset($actions['unpause']);
                unset($actions['activate']);
                unset($actions['update']);
                break;
        }
        
        return $actions;
    }

    /**
     * Removes the pay action from recurring orders, specially when the order is a recurring order details page
     * @param $actions
     * @param $order
     *
     * @return array
     */
    public function filterRecurringOrderActions($actions, $order): array
    {
        if ($order->get_meta('_pagbank_is_recurring') && isset($actions['pay'])) {
            unset($actions['pay']);
        }
        
        return $actions;
    }

    // region frontend subscription management -edit api endpoint (/wc-api/rm-pagbank-subscription-edit)

    /**
     * Entry point for the subscription management edit api endpoint
     * @return void
     */
    public function addManageSubscriptionEditAction(): void
    {
        $subscriptionId = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
        $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
        $referrer = wp_get_referer() ? wp_get_referer() : home_url();
        if (empty($subscriptionId) || empty($action)) {
            wc_add_notice(__('Ação inválida. Verifique se o identificador da assinatura é válido.', 'pagbank-connect'), 'error');
            wp_safe_redirect($referrer);
            return;
        }

        $subscription = $this->getSubscription($subscriptionId);
        if ( ! $subscription->id ) {
            wc_add_notice(__('Assinatura não encontrada.', 'pagbank-connect'), 'error');
            wp_safe_redirect($referrer);
            return;
        }
        $order = wc_get_order($subscription->initial_order_id);
        if ( ! is_admin()) {
            if ( ! is_user_logged_in() ) {
                wp_die(
                    esc_html( __('Você precisa estar logado para acessar esta página.', 'pagbank-connect') ),
                    esc_html( __('Acesso Negado', 'pagbank-connect', ['response' => 403]) )
                );
            }
        }
        
        // if not an admin, check if the user is the owner of the subscription
        if ( ! current_user_can('manage_options') && $order->get_customer_id() != get_current_user_id()) {
            wp_die(
                esc_html( __('Você não tem permissão para acessar esta página.', 'pagbank-connect') ),
                esc_html( __('Acesso Negado', 'pagbank-connect', ['response' => 403]) )
            );
        }

        if ( ! method_exists($this, $action . 'SubscriptionAction')) {
            wc_add_notice(__('Ação não implementada.', 'pagbank-connect'), 'error');
            wp_safe_redirect($referrer);
            return;
        }

        $this->{$action . 'SubscriptionAction'}($subscription);
        wp_safe_redirect($referrer);

    }

    /**
     * @param stdClass $subscription
     *
     * @return void
     */
    public function cancelSubscriptionAction(\stdClass $subscription): void
    {
        $fromAdmin = isset($_GET['fromAdmin']); //phpcs:ignore WordPress.Security.NonceVerification
        if ($fromAdmin) {
            $this->cancelSubscription($subscription, __('Cancelado pelo administrador', 'pagbank-connect'), 'ADMIN');
            return;
        }
        $this->cancelSubscription($subscription, __('Cancelado pelo cliente', 'pagbank-connect'), 'CUSTOMER');
    }
    
    /**
     * Cancels the specified subscription
     * @param stdClass $subscription The subscription to be canceled (row from pagbank_recurring table)
     * @param string   $reason     The reason for cancellation (will be visible to the customer)
     * @param string   $reasonType The reason type (CUSTOMER, ADMIN or FAILURE)
     *
     * @return bool
     */
    public function cancelSubscription(\stdClass $subscription, string $reason, string $reasonType): void
    {
        global $wpdb;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        $nextBill = wp_date('U', strtotime($subscription->next_bill_at));
        $newStatus = ($nextBill < time()) ? 'CANCELED' : 'PENDING_CANCEL';
        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring',
            ['canceled_at' => gmdate('Y/m/d H:i:s'), 'status' => $newStatus, 'canceled_reason' => $reason],
            ['id' => $subscription->id],
            ['%s', '%s', '%s'],
            ['%d']
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
                case 'ADMIN':
                    do_action(
                        'pagbank_recurring_subscription_canceled_by_admin_notification',
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
        
        if ($update > 0) {
            wc_add_notice(__('Assinatura cancelada com sucesso.', 'pagbank-connect'));
            return;            
        }
        
        wc_add_notice(__('Não foi possível cancelar a assinatura.', 'pagbank-connect'), 'error');
    }

    /** @noinspection PhpUnused */
    public function uncancelSubscriptionAction(\stdClass $subscription): void
    {
        global $wpdb;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        if ($subscription->status != 'PENDING_CANCEL') {
            wc_add_notice(__('O status atual da assinatura não permite esta alteração.', 'pagbank-connect'), 'error');
            return;
        }
        
        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring',
            ['canceled_at' => null, 'status' => 'ACTIVE', 'canceled_reason' => null],
            ['id' => $subscription->id],
            ['%s', '%s'],
            ['%d']
        );

        if ($update)
        {
            do_action(
                'pagbank_recurring_subscription_canceled_suspension_notification',
                $subscription,
                $initialOrder
            );
        }
        
        if ($update > 0) {
            wc_add_notice(__('Cancelamento suspenso com sucesso. Sua assinatura foi resumida.', 'pagbank-connect'));
            return;
        }
        
        wc_add_notice(__('Não foi possível suspender o cancelamento da assinatura.', 'pagbank-connect'), 'error');
    }

    /**
     * Suspends the specified subscription
     *
     * @param stdClass $subscription
     *
     * @return bool
     * @noinspection PhpUnused*/
    public function pauseSubscriptionAction(\stdClass $subscription): void
    {
        global $wpdb;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        if ($subscription->status != 'ACTIVE' && $subscription->status != 'PENDING') {
            wc_add_notice(__('O status atual da assinatura não permite esta alteração.', 'pagbank-connect'), 'error');
            return;
        }
        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring',
            ['paused_at' => gmdate('Y/m/d H:i:s'), 'status' => 'PAUSED'],
            ['id' => $subscription->id],
            ['%s', '%s'],
            ['%d']
        );

        if ($update)
        {
            $notifAction = current_user_can('manage_options') ? 'pagbank_recurring_subscription_paused_by_admin'
                : 'pagbank_recurring_subscription_paused_by_customer';
            do_action($notifAction, $subscription, $initialOrder);
            
        }
        if ($update > 0){
            wc_add_notice(__('Assinatura pausada com sucesso.', 'pagbank-connect'));
            return;
        }
        wc_add_notice(__('Não foi possível pausar a assinatura.', 'pagbank-connect'), 'error');
    }

    /** @noinspection PhpUnused */
    public function unpauseSubscriptionAction(\stdClass $subscription): void
    {
        global $wpdb;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        $status = 'ACTIVE';

        if ($subscription->status != 'PAUSED') {
            wc_add_notice(__('O status atual da assinatura não permite esta alteração.', 'pagbank-connect'), 'error');
            return;
        }
        //if next_bill_at < current date, update the next_bill_at with current time
        $nextBill = $subscription->next_bill_at;
        $isDueNow = gmdate('U', strtotime($nextBill)) <= gmdate('U');
        if ($isDueNow) {
            $nextBill = gmdate('Y-m-d H:i:s');
            $status = 'PENDING';
        }

        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring',
            ['paused_at' => null, 'status' => $status, 'next_bill_at' => $nextBill],
            ['id' => $subscription->id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($isDueNow) {
            $this->processRecurringPayments($subscription);
        }

        if ($update > 0)
        {
            wc_add_notice(__('Assinatura resumida com sucesso.', 'pagbank-connect'));
            do_action(
                'pagbank_recurring_subscription_unpaused_notification',
                $subscription,
                $initialOrder
            );
            return;
        }
        
        wc_add_notice(__('Não foi possível resumir a assinatura.', 'pagbank-connect'), 'error');
    }
    // endregion

    /**
     * Updates $subscription with the new $data
     * @param stdClass $subscription
     * @param array    $data
     *
     * @return bool
     */
    public function updateSubscription(\stdClass $subscription, array $data): bool
    {
        global $wpdb;
        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring',
            $data,
            ['id' => $subscription->id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($update > 0)
        {
            do_action(
                'pagbank_recurring_subscription_updated_notification',
                $subscription,
                $data
            );
            return true;
        }
        return false;        
    }

    /**
     * Returns the subscription with the given initial order id or WC_Order object
     * @param mixed $order WC_Order | int
     *
     * @return array|object|stdClass|null
     */
    public function getSubscriptionFromOrder($order)
    {
        global $wpdb;
        
        if ($order instanceof WC_Order) {
            $order = $order->get_id();
        }
        $order = intval($order);
        $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE initial_order_id = %d";
        return $wpdb->get_row($wpdb->prepare($sql, $order));
    }
    
    public function getThankyouInstructions($order)
    {
        require_once dirname(__FILE__) . '/../templates/recurring-instructions.php';
    }
    
}