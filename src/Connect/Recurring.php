<?php
namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Connect\Payments\CreditCardToken;
use RM_PagBank\Helpers\Api;
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
        if (Params::getRecurringConfig('recurring_enabled') != 'yes') {
            return;
        }

        //region admin management
        add_action('woocommerce_product_data_panels', [$this, 'addRecurringTabContent']);
        add_action('woocommerce_process_product_meta', [$this, 'saveRecurringTabContent']);
        add_filter('woocommerce_product_data_tabs', [$this, 'addProductRecurringTab']);
        //endregion

        //region frontend initial-order flow
        add_action('woocommerce_checkout_update_order_meta', [$this, 'addProductMetaToOrder'], 20, 1);
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'addProductMetaToOrder'], 20, 1);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'avoidOtherThanRecurringInCart'], 1, 2);
        add_filter('woocommerce_checkout_registration_required', [$this, 'disableGuestCheckoutForRecurringOrder'], 1, 1);
        add_filter('woocommerce_order_needs_payment', [$this, 'requestPaymentForRecurringTrialOrder'], 1, 3);
        //endregion
        
        //emails
        add_filter('woocommerce_email_classes', [$this, 'addEmails']);
        WC_Emails::instance();
        
        //region cron jobs
        add_action('rm_pagbank_cron_process_recurring_payments', [$this, 'processRecurringPayments']);
        if ( ! wp_next_scheduled('rm_pagbank_cron_process_recurring_payments') ) {
            wp_schedule_event(
                time(),
                Params::getRecurringConfig('recurring_process_frequency', 'hourly'),
                'rm_pagbank_cron_process_recurring_payments'
            );
        }

        add_action('rm_pagbank_cron_process_recurring_cancellations', [$this, 'processRecurringCancellations']);
        if ( ! wp_next_scheduled('rm_pagbank_cron_process_recurring_cancellations') ) {
            wp_schedule_event(
                time(),
                'daily',
                'rm_pagbank_cron_process_recurring_cancellations'
            );
        }

        add_action('rm_pagbank_cron_process_expired_paused', [$this, 'processRecurringExpiredPaused']);
        if ( ! wp_next_scheduled('rm_pagbank_cron_process_expired_paused') ) {
            wp_schedule_event(
                time(),
                'daily',
                'rm_pagbank_cron_process_expired_paused'
            );
        }
        //endregion
        
        //region frontend subscription management
        add_filter('woocommerce_account_menu_items', [$this, 'addSubscriptionManagementMenuItem'], 10, 1);
        add_action('woocommerce_account_rm-pagbank-subscriptions_endpoint', [$this, 'addManageSubscriptionContent']);
        add_action('woocommerce_account_rm-pagbank-subscriptions-view_endpoint', [$this, 'addManageSubscriptionViewContent']);
        add_action('woocommerce_account_rm-pagbank-subscriptions-update_endpoint', [$this, 'addManageSubscriptionUpdateContent']);
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
        add_action('woocommerce_before_calculate_totals', [$this, 'handleRecurringProductPrice'], 10, 1);
        add_filter('woocommerce_cart_needs_payment', [$this, 'enablePaymentInTrialOrder'], 10, 2);
        add_action('template_redirect', [$this, 'handleRestrictedAccess']);
        add_action('pagbank_recurring_cancellation_processed', [$this, 'updateUserRestrictedAccessForSubscription'], 10, 1);
        add_action('pagbank_recurring_subscription_created_notification', [$this, 'updateUserRestrictedAccessForSubscription'], 10, 1);
        add_action('pagbank_recurring_subscription_status_changed', [$this, 'updateUserRestrictedAccessForSubscription'], 10, 2);
        add_action('pagbank_recurring_subscription_update_payment_method', [$this, 'subscriptionUpdatePayment'], 10, 1);
        add_action('pagbank_recurring_subscription_payment_method_changed', [$this, 'subscriptionMaybeChargeAndUpdate'], 10, 1);
        add_action('admin_notices', [$this,'showMessegesTransient'], 10, 2);
    }

    public static function showMessegesTransient()
    {
        if ($mensagem = get_transient('pagbank_recurring_message')) {
            wp_admin_notice(
                esc_html($mensagem),
                array(
                    'id'                 => 'message',
                    'additional_classes' => array('updated'),
                    'dismissible'        => true,
                )
            );
            delete_transient('pagbank_recurring_message');
        }
    }
    public static function recurringSettingsFields($settings, $current_section)
    {
        if ( 'rm-pagbank-recurring-settings' !== $current_section ) {
            return $settings;
        }

        return include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/recurring-fields.php';
    }

    public static function recurringHeaderSettingsSection()
    {
        global $current_section;
        if ( 'rm-pagbank-recurring-settings' !== $current_section ) {
            return;
        }

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-recurring-settings-page.php';
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

    function handleRecurringProductPrice($cart)
    {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }

        $recurringHelper = new RecurringHelper();
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];

            if ($product->get_meta('_recurring_enabled') != 'yes') {
                continue;
            }

            if ($product->get_meta('_recurring_trial_length')) {
                $trial_price = 0;
                $product->set_price( $trial_price );
                continue;
            }

            if ($recurringHelper->hasDiscount($product)) {
                $discount = $product->get_meta('_recurring_discount_amount');
                $price = $product->get_price();
                $product->set_price( $price - $discount );
            }
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
            <div class="options_group">
            <?php
            woocommerce_wp_checkbox( array(
                'id'            => '_recurring_enabled',
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
            woocommerce_wp_text_input([
                'id' => '_recurring_max_cycles',
                'label' => __('Não cobrar mais que X ciclos', 'pagbank-connect'),
                'description' => __('Número máximo de ciclos de cobrança. Nesse caso, o cliente não poderá cancelar a assinatura antes de finalizar.', 'pagbank-connect'),
                'desc_tip' => true,
                'value' => get_post_meta($post->ID, '_recurring_max_cycles', true),
                'type' => 'number',
                'custom_attributes' => [
                    'min' => 1,
                    'step' => 1,
                ],
            ]);
            ?>
            </div>
            <div class="options_group">
                <?php
                woocommerce_wp_text_input([
                    'id' => '_recurring_trial_length',
                    'label' => __('Período de testes (dias)', 'pagbank-connect'),
                    'description' => __('Definir um período para o cliente testar a assinatura. Valor em dias.', 'pagbank-connect'),
                    'desc_tip' => true,
                    'value' => get_post_meta($post->ID, '_recurring_trial_length', true),
                ]);
                ?>
            </div>
            <div class="options_group">
                <?php
                woocommerce_wp_text_input([
                    'id' => '_recurring_discount_amount',
                    'label' => __('Desconto (R$)', 'pagbank-connect'),
                    'description' => __('Valor de desconto a ser aplicado nos pedidos inicial e recorrentes durante o número de ciclos determinado.', 'pagbank-connect'),
                    'desc_tip' => true,
                    'value' => get_post_meta($post->ID, '_recurring_discount_amount', true),
                ]);
                woocommerce_wp_text_input([
                    'id' => '_recurring_discount_cycles',
                    'label' => __('Número de ciclos de pagamento com desconto', 'pagbank-connect'),
                    'description' => __('Ex: Se Desconto fosse 5 e ciclo fosse 2, aplicaria o desconto no pedido inicial e na primeira cobrança.', 'pagbank-connect'),
                    'desc_tip' => true,
                    'type' => 'number',
                    'custom_attributes' => [
                        'min' => 1,
                        'step' => 1,
                    ],
                    'value' => get_post_meta($post->ID, '_recurring_discount_cycles', true),
                ]);
                ?>
                </div>
                <h2><?php echo __('Restringir conteúdo', 'pagbank-connect');?><span class="woocommerce-help-tip" tabindex="0" aria-label="<?php echo __('Restrinja o acesso à páginas e categorias somente para assinantes deste produto', 'pagbank-connect')?>" data-tip="<?php echo __('Restrinja o acesso à páginas e categorias somente para assinantes deste produto', 'pagbank-connect')?>"></span></h2>
                
                <div class="options_group">
                <?php
                woocommerce_wp_select([
                    'id' => '_recurring_restricted_pages',
                    'name' => '_recurring_restricted_pages[]',
                    'label' => __('Páginas restritas', 'pagbank-connect'),
                    'description' => __('Selecione as páginas que só podem ser acessadas por assinantes. Use a tecla Crtl ou Command (Mac) para selecionar mais de uma.', 'pagbank-connect'),
                    'options' => $this->getPagesOptions(),
                    'desc_tip' => true,
                    'value' => get_post_meta($post->ID, '_recurring_restricted_pages', true),
                    'custom_attributes' => ['multiple' => 'multiple'],
                    'class' => 'wc-enhanced-select short'
                ]);
                woocommerce_wp_select([
                    'id' => '_recurring_restricted_categories',
                    'name' => '_recurring_restricted_categories[]',
                    'label' => __('Categorias restritas', 'pagbank-connect'),
                    'description' => __('Selecione as categorias que só podem ser acessadas por assinantes.', 'pagbank-connect'),
                    'options' => $this->getCategoriesOptions(),
                    'desc_tip' => true,
                    'value' => get_post_meta($post->ID, '_recurring_restricted_categories', true),
                    'custom_attributes' => ['multiple' => 'multiple'],
                    'class' => 'wc-enhanced-select short'
                ]);
                woocommerce_wp_select([
                    'id' => '_recurring_restricted_unauthorized_page',
                    'label' => __('Redirecionar para', 'pagbank-connect'),
                    'description' => __('Selecione a página que o cliente verá quando não tiver acesso.', 'pagbank-connect'),
                    'options' => [''=> __('Selecione', 'pagbank-connect')] + $this->getPagesOptions(),
                    'desc_tip' => true,
                    'value' => get_post_meta($post->ID, '_recurring_restricted_unauthorized_page', true),
                ]);
                
                ?>
            </div>
            <div class="options_group">
                <p><?php echo esc_html(
                        __('Alterações de valor, ciclos, taxas, periodos de testes, etc só afetarão futuras assinaturas.', 'pagbank-connect')
                    );?></p>
                <p><?php echo esc_html(
                        __('Alterações na restrição de conteúdo terão efeito imediato.', 'pagbank-connect')
                    );?></p>
            </div>
        </div>
        <?php
    }

    private function getPagesOptions() {
        $pages = get_pages();
        $options = [];
        foreach ($pages as $page) {
            $options[$page->ID] = $page->post_title . ' (ID: ' . $page->ID . ')';
        }
        return $options;
    }

    private function getCategoriesOptions() {
        $categories = get_categories();
        $options = [];
        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name . ' (ID: ' . $category->term_id . ')';
        }
        return $options;
    }

    /**
     * Save the recurring tab content (from product edit page)
     * @param $postId
     *
     * @return void
     */
    public function saveRecurringTabContent($postId)
    {
        $oldRecurringEnabled = get_post_meta($postId, '_recurring_enabled', true);
        $recurringEnabled = isset($_POST['_recurring_enabled']) ? 'yes' : 'no';
        update_post_meta($postId, '_recurring_enabled', $recurringEnabled);
        if ($oldRecurringEnabled != $recurringEnabled) {
            delete_transient('recurring_restricted_products');
            $this->updateAllUsersRestrictedAccess();
        }
        
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

            $trialLength = isset($_POST['_recurring_trial_length']) ? sanitize_text_field($_POST['_recurring_trial_length']) : 0;
            update_post_meta($postId, '_recurring_trial_length', $trialLength);

            $discountAmount = sanitize_text_field($_POST['_recurring_discount_amount'] ?? 0);
            $discountAmount = floatval(str_replace(',', '.', $discountAmount));
            $discountAmount = floatval(number_format(max(0, $discountAmount), 2, '.', ''));
            update_post_meta($postId, '_recurring_discount_amount', $discountAmount);

            $cycle = isset($_POST['_recurring_discount_cycles']) ? sanitize_text_field($_POST['_recurring_discount_cycles']) : 0;
            update_post_meta($postId, '_recurring_discount_cycles', $cycle);

            $maxCycles = isset($_POST['_recurring_max_cycles']) ? sanitize_text_field($_POST['_recurring_max_cycles']) : 0;
            update_post_meta($postId, '_recurring_max_cycles', $maxCycles);

            //region Restricted Access (pages and categories - coming soon)
            // if restricted pages info changed, clear transient recurring_restricted_products
            $oldRestrictedPages = get_post_meta($postId, '_recurring_restricted_pages', true);
            $oldRestrictedCategories = get_post_meta($postId, '_recurring_restricted_categories', true);

            $restrictedPages = isset($_POST['_recurring_restricted_pages']) ? array_map('sanitize_text_field', $_POST['_recurring_restricted_pages']) : [];
            update_post_meta($postId, '_recurring_restricted_pages', $restrictedPages);

            $restrictedCategories = isset($_POST['_recurring_restricted_categories']) ? array_map('sanitize_text_field', $_POST['_recurring_restricted_categories']) : [];
            update_post_meta($postId, '_recurring_restricted_categories', $restrictedCategories);

            if ($oldRestrictedPages != $restrictedPages || $oldRestrictedCategories != $restrictedCategories) {
                delete_transient('recurring_restricted_products');
                $this->updateAllUsersRestrictedAccess();
            }
            $unauthoridedPageId = isset($_POST['_recurring_restricted_unauthorized_page']) ? intval(sanitize_text_field($_POST['_recurring_restricted_unauthorized_page'])) : get_option('page_on_front');
            update_post_meta($postId, '_recurring_restricted_unauthorized_page', $unauthoridedPageId);
            
            
            //endregion
        }
        
        update_post_meta($postId, '_recurring_restriction_active', (!empty($restrictedCategories) || !empty($restrictedPages)));
    }
    
    public function avoidOtherThanRecurringInCart($canBeAdded, $productId)
    {
        $cart = WC()->cart;
        $cartItems = $cart->get_cart();
        
        $product = wc_get_product($productId);
        $productIsRecurring = $product->get_meta('_recurring_enabled') == 'yes';
        $recurringHelper = new RecurringHelper();

        $canClearCart = wc_string_to_bool(Params::getRecurringConfig('recurring_clear_cart'));
        $recurringCart = $productIsRecurring || $recurringHelper->isCartRecurring();

        if (empty($cartItems) || !$recurringCart) {
            return $canBeAdded;
        }
        
        if (!$canClearCart) {
            \wc_add_notice(__('Produtos recorrentes ou assinaturas devem ser comprados separadamente. Remova os itens recorrentes do carrinho antes de prosseguir.', 'pagbank-connect'), 'error');
            $canBeAdded = false;
        }

        if ($canClearCart) {
            $cart->empty_cart();
            $canBeAdded = true;
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
     * Will check if the order is a recurring trial order and if so,
     * will force the payment to be required for getting the card token and create the subscription
     *
     * @param $needsPayment
     * @param $order
     * @return mixed|true
     */
    public function requestPaymentForRecurringTrialOrder($needsPayment, $order)
    {
        $recHelper = new RecurringHelper();
        if ($recHelper->isCartRecurring() && $order->get_total() == 0) {
            $needsPayment = true;
        }
        return $needsPayment;
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
        $initialFee = (float)$order->get_meta('_recurring_initial_fee');
        $discount = (float)$order->get_meta('_recurring_discount_amount');
        $discountCycles = (int)$order->get_meta('_recurring_discount_cycles');
        $maxCycles = (int)$order->get_meta('_recurring_max_cycles');

        $nextBill = $recHelper->calculateNextBillingDate($frequency, $cycle);

        $trialLength = (int) $order->get_meta('_pagbank_recurring_trial_length');
        if ($trialLength) {
            $nextBill = $recHelper->calculateNextBillingDate($frequency, $cycle, $trialLength);
        }

        $recurringAmount = $order->get_total() - $initialFee;
        if ($trialLength || $discountCycles) {
            $recurringAmount = $recHelper->getRecurringAmountFromOrderItems($order);
        }

        $paymentInfo = $this->getPaymentInfo($order);
        $statusFromOrder = $recHelper->getStatusFromOrder($order);

        $success = $this->insertOrUpdateSubscription([
            'initial_order_id'          => $order->get_id(),
            'recurring_amount'          => $recurringAmount,
            'recurring_initial_fee'     => $initialFee,
            'recurring_trial_period'    => $trialLength,
            'recurring_discount_amount' => $discount,
            'recurring_discount_cycles' => $discountCycles,
            'recurring_max_cycles'      => $maxCycles,
            'status'                    => $statusFromOrder,
            'recurring_type'            => $frequency,
            'recurring_cycle'           => $cycle,
            'created_at'                => gmdate('Y-m-d H:i:s'),
            'updated_at'                => gmdate('Y-m-d H:i:s'),
            'next_bill_at'              => $nextBill->format('Y-m-d H:i:s'),
            'payment_info'              => json_encode($paymentInfo),
        ]);
        
        if ($success !== false && $statusFromOrder == 'ACTIVE') {
            $subOrderId = $order->get_id();
            $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE initial_order_id = %d";
            $subscription = $wpdb->get_row($wpdb->prepare($sql, $subOrderId));

            //send welcome e-mail
            do_action('pagbank_recurring_subscription_created_notification', $subscription, $order);
        }
        
        return $success !== false;
    }

    private function insertOrUpdateSubscription(array $data): bool
    {
        global $wpdb;
        $format = ['%d', '%f', '%f', '%d', '%f', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s'];
        $table = $wpdb->prefix . 'pagbank_recurring';

        $existingSubscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE initial_order_id = %d",
            $data['initial_order_id']
        ));

        if ($existingSubscription && $existingSubscription->status === 'PENDING') {
            $update = $wpdb->update($table, $data, ['id' => $existingSubscription->id]) !== false;
            if ($update && isset($data['status']) && strcmp($data['status'], $existingSubscription->status) != 0) {
                do_action('pagbank_recurring_subscription_status_changed', $existingSubscription, $data['status']);
            }
            return $update; 
        }

        return $wpdb->insert($table, $data, $format) !== false;
    }

    /**
     * Will get subscriptions that are due (or the given subscription) and process the recurring payment
     * @param stdClass|null $subscription
     *
     * @return void
     */
    public function processRecurringPayments(\stdClass $subscription = null)
    {
        global $wpdb;
        $recHelper = new \RM_PagBank\Helpers\Recurring();

        //Get all recurring orders that are due or past due and active
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE ";
        $sql .= $subscription == null 
            ? "status IN ('ACTIVE', 'SUSPENDED') AND next_bill_at <= '%s'"
            : "id = 0%d";
        $nowOrId = $subscription == null ? $now : $subscription->id;
        $sql = $wpdb->prepare($sql, $nowOrId);
        $subscriptions = $wpdb->get_results($sql);
        foreach ($subscriptions as $subscription) {
            $recurringOrder = new Connect\Recurring\RecurringOrder($subscription);
            $recurringOrder->createRecurringOrderFromSub();
        }
    }

    /**
     * Cron job to process recurring cancellations
     * @return void
     */
    public function processRecurringCancellations()
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring 
         WHERE status = 'PENDING_CANCEL' AND next_bill_at <= canceled_at";
        $subscriptions = $wpdb->get_results($sql);
        foreach ($subscriptions as $subscription) {
            $this->updateSubscription($subscription, ['status' => 'CANCELED']);
            do_action('pagbank_recurring_cancellation_processed', $subscription);
        }
    }
    
    /**
     * Process subscriptions that are due but paused and trigger further actions
     * @return void
     */
    public function processRecurringExpiredPaused()
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT * FROM {$wpdb->prefix}pagbank_recurring 
         WHERE status = 'PAUSED' AND next_bill_at <= '%s'";
        $subscriptions = $wpdb->get_results($wpdb->prepare($sql, $now));
        foreach ($subscriptions as $subscription) {
            //remove access to restricted content if any
            $this->updateUserRestrictedAccessForSubscription($subscription);
            do_action('pagbank_recurring_expired_paused_processed', $subscription);
        }
    }

    public function subscriptionUpdatePayment($subscription)
    {
        wc_get_template('recurring/my-account/form-change-credit-card.php', [
            'subscription' => $subscription,
        ], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);;
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
                $order->update_meta_data('_recurring_discount_amount', $originalItem->get_meta('_recurring_discount_amount'));
                $order->update_meta_data('_recurring_discount_cycles', $originalItem->get_meta('_recurring_discount_cycles'));
                $order->update_meta_data('_recurring_max_cycles', $originalItem->get_meta('_recurring_max_cycles'));
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
                'id' => $chargeInfo['card']['id'] ?? null,
            ];
        }

        if ($paymentMethod == 'credit_card_token') {
            $paymentInfo['method'] = 'credit_card';
            $cardInfo = $order->get_meta('pagbank_order_recurring_card');
            if ( ! isset($cardInfo['id'])){
                Functions::log('Não foi possível carregar as informações do pagamento inicial para gerar os '
                    .'detalhes da recorrência.', 'critical', ['order id' => $order->get_id()] );
                return [];
            }

            $paymentInfo['card'] = [
                'holder_name' => $cardInfo['holder']['name'],
                'number' => $cardInfo['first_digits'] . '******' . $cardInfo['last_digits'],
                'expiration_date' => $cardInfo['exp_month'] . '/' .
                    $cardInfo['exp_year'],
                'brand' => $cardInfo['brand'],
                'id' => $cardInfo['id']
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
        add_rewrite_endpoint('rm-pagbank-subscriptions-update', EP_PAGES);
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

    public function addManageSubscriptionUpdateContent($subscriptionId)
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

        wc_get_template('recurring/my-account/update-subscription.php', [
            'subscription' => $subscription,
            'initialOrder' => $order,
            'dashboard' => $dash
        ], Connect::DOMAIN, WC_PAGSEGURO_CONNECT_TEMPLATES_DIR);
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
            'my-account/rm-pagbank-subscriptions-update'
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
            case stripos($endpoint, 'my-account/rm-pagbank-subscriptions-update') !== false:
                $id = esc_html($wp->query_vars['rm-pagbank-subscriptions-update']);
                $title = sprintf(__('Atualizar Assinatura #%d', 'pagbank-connect'), $id);
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
        if ($subscription->recurring_max_cycles > 0 && !is_admin()) {
            unset($actions['cancel']);
            unset($actions['uncancel']);
            unset($actions['pause']);
            unset($actions['unpause']);
            unset($actions['activate']);
            unset($actions['edit']);

            return $actions;
        }

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
                unset($actions['edit']);
                unset($actions['update']);
                break;
            case 'SUSPENDED':
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

        if (!is_admin() && Params::getRecurringConfig('recurring_customer_can_cancel') === 'no') {
            unset($actions['cancel']);
        }

        if (!is_admin() && Params::getRecurringConfig('recurring_customer_can_pause') === 'no') {
            unset($actions['pause']);
        }
        
        if (!is_admin()) {
            unset($actions['edit']);
        }

        if (is_admin()) {
            unset($actions['update']);
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
        $action = htmlspecialchars($_GET['action'], ENT_QUOTES, 'UTF-8');
        
        $referrer = wp_get_referer() ? wp_get_referer() : home_url();
        if (empty($subscriptionId) || empty($action)) {
            \wc_add_notice(__('Ação inválida. Verifique se o identificador da assinatura é válido.', 'pagbank-connect'), 'error');
            wp_safe_redirect($referrer);
            return;
        }

        $subscription = $this->getSubscription($subscriptionId);
        if ( ! $subscription->id ) {
            \wc_add_notice(__('Assinatura não encontrada.', 'pagbank-connect'), 'error');
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
            \wc_add_notice(__('Ação não implementada.', 'pagbank-connect'), 'error');
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
     * Suspends the specified subscription
     *
     * @param stdClass $subscription The subscription to be canceled (row from pagbank_recurring table)
     * @param string $reason The reason for cancellation (will be visible to the customer)
     * @param int $retryAttempts
     * @return void
     * @noinspection PhpUnused
     */
    public function suspendSubscription(\stdClass $subscription, string $reason, int $retryAttempts): void
    {
        global $wpdb;
        $recHelper = new RecurringHelper();

        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring',
            [
                'suspended_at' => gmdate('Y/m/d H:i:s'),
                'status' => 'SUSPENDED',
                'suspended_reason' => $reason,
                'retry_attempts_remaining' => $retryAttempts,
                'next_bill_at' => $recHelper->calculateNextBillingDate(
                    'D',
                    1
                )->format('Y-m-d H:i:s')
            ],
            ['id' => $subscription->id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($update) {
            do_action('pagbank_recurring_subscription_status_changed', $subscription, 'SUSPENDED');
        }

        if ($update > 0) {
            do_action('pagbank_recurring_subscription_suspended_by_payment_failure', $subscription);
            return;
        }
    }

    /**
     * @param stdClass $subscription
     * @return void
     * @throws Exception
     */
    public function updateSuspendedSubscription(\stdClass $subscription)
    {
        $recHelper = new RecurringHelper();
        $cycle = $subscription->retry_attempts_remaining > 1 ? 1 : 3; // aumenta o intervalo na última tentativa de cobrança para 3 dias
        $retryAttemptsRemaining = --$subscription->retry_attempts_remaining;

        $this->updateSubscription($subscription, [
            'status' => 'SUSPENDED',
            'next_bill_at' => $recHelper->calculateNextBillingDate(
                'D',
                $cycle
            )->format('Y-m-d H:i:s'),
            'retry_attempts_remaining' => $retryAttemptsRemaining
        ]);

        do_action('pagbank_recurring_subscription_suspended_by_payment_failure', $subscription);
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
            
            //if canceled, remove the user content authorizations
            if ($newStatus == 'CANCELED') {
                $order = wc_get_order($subscription->initial_order_id);
                $userId = $order->get_customer_id();
                $this->updateUserRestrictions($userId);
            }
        }
        
        if ($update > 0) {
            if (strcmp($newStatus, $subscription->status) != 0) {
                do_action('pagbank_recurring_subscription_status_changed', $subscription, $newStatus);
            }
            
            if(defined('DOING_CRON') && DOING_CRON){
                Functions::log('Assinatura cancelada com sucesso.', 'info', ['subscription id' => $subscription->id]);
                return;
            }
            $this->addNotice(__('Assinatura cancelada com sucesso.', 'pagbank-connect'), $reasonType, 'success');
            return;            
        }
        
        if(defined('DOING_CRON') && DOING_CRON){
            Functions::log('Não foi possível cancelar a assinatura.', 'error', ['subscription id' => $subscription->id]);
            return;
        }

        $this->addNotice(__('Não foi possível cancelar a assinatura.', 'pagbank-connect'), $reasonType, 'error');
    }

    /** @noinspection PhpUnused */
    public function uncancelSubscriptionAction(\stdClass $subscription): void
    {
        global $wpdb;
        $fromAdmin = isset($_GET['fromAdmin']) ? 'ADMIN' : ''; //phpcs:ignore WordPress.Security.NonceVerification
        $initialOrder = wc_get_order($subscription->initial_order_id);
        if ($subscription->status != 'PENDING_CANCEL') {
            \wc_add_notice(__('O status atual da assinatura não permite esta alteração.', 'pagbank-connect'), 'error');
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
            do_action('pagbank_recurring_subscription_status_changed', $subscription, 'ACTIVE');
        }
        
        if ($update > 0) {
            $this->addNotice(__('Cancelamento suspenso com sucesso. Sua assinatura foi resumida.', 'pagbank-connect'), $fromAdmin, 'success');
            return;
        }
        $this->addNotice(__('Não foi possível suspender o cancelamento da assinatura.', 'pagbank-connect'), $fromAdmin, 'error');
    }

    /**
     * Pauses the specified subscription
     *
     * @param stdClass $subscription
     *
     * @return void
     * @noinspection PhpUnused
     */
    public function pauseSubscriptionAction(\stdClass $subscription): void
    {
        global $wpdb;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        $fromAdmin = isset($_GET['fromAdmin']) ? 'ADMIN' : ''; //phpcs:ignore WordPress.Security.NonceVerification
        if ($subscription->status != 'ACTIVE' && $subscription->status != 'PENDING') {
            \wc_add_notice(__('O status atual da assinatura não permite esta alteração.', 'pagbank-connect'), 'error');
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
            do_action('pagbank_recurring_subscription_status_changed', $subscription, 'PAUSED');
        }
        if ($update > 0){
            $this->addNotice(__('Assinatura pausada com sucesso.', 'pagbank-connect'), $fromAdmin, 'success');
            return;
        }
        $this->addNotice(__('Não foi possível pausar a assinatura.', 'pagbank-connect'), $fromAdmin, 'error');
    }

    /** @noinspection PhpUnused */
    public function unpauseSubscriptionAction(\stdClass $subscription): void
    {
        global $wpdb;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        $status = 'ACTIVE';
        $fromAdmin = isset($_GET['fromAdmin']) ? 'ADMIN' : ''; //phpcs:ignore WordPress.Security.NonceVerification
        if ($subscription->status != 'PAUSED') {
            $this->addNotice(__('O status atual da assinatura não permite esta alteração.', 'pagbank-connect'), $fromAdmin, 'error');
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

        if ($update > 0)
        {
            $this->addNotice(__('Assinatura resumida com sucesso.', 'pagbank-connect'), $fromAdmin, 'success');
            do_action(
                'pagbank_recurring_subscription_unpaused_notification',
                $subscription,
                $initialOrder
            );
            do_action('pagbank_recurring_subscription_status_changed', $subscription, $status);
            
            if ($isDueNow) {
                $this->processRecurringPayments($subscription);
                $this->addNotice(__('Um pagamento devido foi processado.', 'pagbank-connect'), $fromAdmin, 'success');
            }
            return;
        }

        $this->addNotice(__('Não foi possível resumir a assinatura.', 'pagbank-connect'), $fromAdmin, 'error');
    }

    /**
     * Change subscription status to COMPLETED if the subscription has finished the max cycles
     *
     * @param $subscription
     * @return void
     */
    public function completeSubscription($subscription)
    {
        global $wpdb;
        $subscription->status = 'COMPLETED';
        $update = $wpdb->update($wpdb->prefix . 'pagbank_recurring', ['status' => $subscription->status], ['id' => $subscription->id]);
        if ($update) {
            do_action('pagbank_recurring_subscription_status_changed', $subscription, 'COMPLETED');
        }
    }

    /**
     * Edit the specified subscription
     *
     * @param stdClass $subscription
     *
     * @return void
     * @noinspection PhpUnused*/
    public function editSubscriptionAction(\stdClass $subscription): void
    {
        $recurringAmount = sanitize_text_field($_POST['recurring_amount']);
        $recurringAmount = preg_replace('/[^0-9,.]/', '', $recurringAmount);
        $recurringAmount = str_replace(['.', ','], ['', '.'], $recurringAmount);
        $recurringAmount = floatval(number_format((float) $recurringAmount, 2, '.', ''));
        $update = $this->updateSubscription($subscription, [
            'recurring_amount' => $recurringAmount,
        ]);
        $fromAdmin = 'ADMIN'; //phpcs:ignore WordPress.Security.NonceVerification
        if ($update){
            $this->addNotice(__('Assinatura atualizada com sucesso.', 'pagbank-connect'), $fromAdmin, 'success');
            return;
        }
        $this->addNotice('Não foi possível atualizar a assinatura.', $fromAdmin, 'error');
    }

    public function changePaymentMethodSubscriptionAction(\stdClass $subscription): void
    {
        $fromAdmin = isset($_GET['fromAdmin']) ? 'ADMIN' : ''; //phpcs:ignore WordPress.Security.NonceVerification
        $order = wc_get_order($subscription->initial_order_id);
        $order->add_meta_data(
            '_pagbank_card_encrypted',
            htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8'),
            true
        );
        $method = new CreditCardToken($order);
        $params = $method->prepare();

        $api = new Api();
        try {
            $resp = $api->post('ws/tokens/cards', $params);
            if (isset($resp['error_messages'])) {
                throw new \RM_PagBank\Connect\Exception($resp['error_messages'], 40000);
            }
            $method->process_response($order, $resp);
        } catch (Exception $e) {
            $message = sprintf(__('Não foi possível salvar o cartão. Por favor, confira os dados do cartão e tente novamente. %s', 'pagbank-connect'), $e->getMessage());
            $this->addNotice($message, $fromAdmin, 'error');
            return;
        }
        $order->update_meta_data('pagbank_payment_method', $method->code);
        $paymentInfo = $this->getPaymentInfo($order);
        $update = $this->updateSubscription($subscription, [
            'payment_info' => json_encode($paymentInfo),
        ]);

        if ($update){
            $this->addNotice(__('Método de pagamento alterado com sucesso.', 'pagbank-connect'), $fromAdmin, 'success');
            do_action('pagbank_recurring_subscription_payment_method_changed', $subscription->id);
            return;
        }
        
        $this->addNotice(__('Não foi possível salvar o cartão. Por favor, confira os dados do cartão e tente novamente.', 'pagbank-connect'), $fromAdmin, 'error');
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
            
            if (isset($data['status']) && strcasecmp($data['status'], $subscription->status) != 0) {
                do_action('pagbank_recurring_subscription_status_changed', $subscription, $data['status']);
            }
            
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

    public function enablePaymentInTrialOrder($needs_payment, $cart)
    {
        // Check if the cart has a trial
        $recurringHelper = new RecurringHelper();
        $hasTrial = $recurringHelper->getCartRecurringTrial();
        if ($cart->total == 0 && $hasTrial) {
            $needs_payment = true;
        }

        return $needs_payment;
    }

    public function handleRestrictedAccess() {
        if ((!is_page() && !is_single()) || is_product_category() || is_product() || is_shop()) {
            return; //has access
        }
        
        $pageId = get_the_ID();
        $categoryId = $this->getPostCategories();
        $userId = get_current_user_id();
        $restrictedProducts = $this->getProductsWithRestriction();

        if (empty($restrictedProducts)) {
            return;
        }
        
        // Check if the current page or category is restricted
        $restrictedProductIds = [];
        $isPageRestricted = false;
        foreach ($restrictedProducts as $product) {
            $restrictedPages = get_post_meta($product, '_recurring_restricted_pages', true);
            $restrictedCategories = get_post_meta($product, '_recurring_restricted_categories', true);

            if (in_array($pageId, $restrictedPages) || array_intersect($categoryId, $restrictedCategories)) {
                $restrictedProductIds[] = $product;
                $isPageRestricted = true;
            }
        }

        if (empty($restrictedProductIds) || !$isPageRestricted) {
            return;
        }

        if (!is_user_logged_in()) {
            $this->redirectToUnauthorizedPage($product ?? 0);
        }

        $recHelper = new RecurringHelper();
        if ($recHelper->canAccessRestrictedContent($userId, $pageId, $categoryId)) {
            return;
        }

        $this->redirectToUnauthorizedPage($product ?? 0);
    }

    /**
     * Update user restricted access for a given subscription
     * @param $subscription
     *
     * @return void
     */
    public function updateUserRestrictedAccessForSubscription($subscription)
    {
        if (!isset($subscription->initial_order_id)) {
            return;
        }
        $order = wc_get_order($subscription->initial_order_id);
        if (!$order) {
            return;
        }
        $userId = $order->get_customer_id();
        $this->updateUserRestrictions($userId);
    }
    
    public function getPostCategories(): array
    {
        $categories = get_the_category();
        $category_ids = [];

        if ( ! empty( $categories ) ) {
            foreach ( $categories as $category ) {
                $category_ids[] = $category->term_id;
            }
        }
        return $category_ids;
    }
    
    public function updateUserRestrictionsAfterOrderIsPaid($order, $orderData)
    {
        $isRecurring = $order->get_meta('_pagbank_is_recurring');
        $userId = $order->get_customer_id();
        if ($isRecurring && $userId) {
            $this->updateUserRestrictions($userId);
        }
    }

    /**
     * @param $productId
     *
     * @return void
     */
    public function redirectToUnauthorizedPage($productId) {
        $unauthorizedPageId = get_post_meta($productId, '_recurring_restricted_unauthorized_page', true);
        wp_redirect(get_permalink($unauthorizedPageId));
        exit;
    }

    
    /**
     * Get all restricted products from cache or database
     * @return int[]|mixed|\WP_Post[]
     */
    protected function getProductsWithRestriction()
    {
        $restrictedProducts = get_transient('recurring_restricted_products');
        if ($restrictedProducts === false) {
            $restrictedProducts = get_posts([
                'post_type'   => 'product',
                'fields'      => 'ids',
                'meta_query'  => [
                    [
                        'key'   => '_recurring_restriction_active',
                        'value' => 1,
                    ],
                ],
                'numberposts' => -1,
            ]);
            set_transient('recurring_restricted_products', $restrictedProducts, HOUR_IN_SECONDS);
        }

        return $restrictedProducts;
    }

    /**
     * @param int|null $userId
     *
     * @return stdClass|WC_Order[]
     */
    protected function getUserRecurringOrders(int $userId = null)
    {
        $userId = $userId ?? get_current_user_id();
        // Check if HPOS is enabled
        if (wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
            return wc_get_orders([
                'customer_id' => $userId,
                'limit'       => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_pagbank_recurring_initial',
                        'value' => '1',
                    ]
                ]
            ]);
        }
        // else, HPOS is disabled
        $args = array(
            'post_type'      => 'shop_order',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_pagbank_recurring_initial',
                    'value'   => '1',
                    'compare' => '='
                ],
                [
                    'key'     => '_customer_user',
                    'value'   => $userId,
                    'compare' => '='
                ]
            ],
        );

        $query = new \WP_Query($args);

        $recurringOrders = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $order_id = get_the_ID();
                $order = wc_get_order($order_id);
                $recurringOrders[] = $order;
            }
            wp_reset_postdata();
        }

        return $recurringOrders;
    }

    /**
     * @param $userId
     *
     * @return void
     */
    public function updateUserRestrictions($userId)
    {
        $userRecurringOrders = $this->getUserRecurringOrders($userId);
        if (empty($userRecurringOrders)) {
            return;
        }
        
        $allowedPages = [];
        $allowedCategories = [];
        $recHelper = new RecurringHelper();
        foreach ($userRecurringOrders as $order) {
            $subscription = $this->getSubscriptionFromOrder($order);
            if (!$subscription || !$recHelper->areBenefitsActive($subscription)) {
                continue;
            }
            foreach($order->get_items() as $item) {
                $originalItem = wc_get_product($item->get_product_id());
                if ($originalItem->get_meta('_recurring_enabled') != 'yes') {
                    continue;
                }
                    
                $restrictedPages = get_post_meta($originalItem->get_id(), '_recurring_restricted_pages', true) ?? [];
                $restrictedCategories = get_post_meta($originalItem->get_id(), '_recurring_restricted_categories', true) ?? [];
                $allowedPages = array_merge($allowedPages, $restrictedPages);
                $allowedCategories = array_merge($allowedCategories, $restrictedCategories);
            }
        }
        
        $allowedPages = array_unique($allowedPages);
        $allowedCategories = array_unique($allowedCategories);
        $this->updateUserRestrictionsContent($userId, $allowedPages, $allowedCategories);
        
    }

    /**
     * @param $uscerId
     * @param $restrictedPages
     * @param $restrictedCategories
     *
     * @return void
     */
    public function updateUserRestrictionsContent($userId, $restrictedPages, $restrictedCategories)
    {
        // create new entry on pagbank_content_restriction if user is not listed there
        global $wpdb;
        $table = $wpdb->prefix . 'pagbank_content_restriction';
        $query = $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $userId);
        $user = $wpdb->get_row($query);
        $restrictedPages = $restrictedPages ? implode(',', $restrictedPages) : null;
        $restrictedCategories = $restrictedCategories ? implode(',', $restrictedCategories) : null;
        if (is_null($user)) {
            $wpdb->insert($table, ['user_id' => $userId, 'pages' => $restrictedPages, 'categories' => $restrictedCategories]);
            return;
        }
        //or update the existing entry
        $wpdb->update($table, ['pages' => $restrictedPages, 'categories' => $restrictedCategories], ['user_id' => $userId]);
    }
    
    public function updateAllUsersRestrictedAccess()
    {
        //all subscriptions
        global $wpdb;
        $table = $wpdb->prefix . 'pagbank_recurring';
        $query = "SELECT * FROM $table WHERE status IN ('ACTIVE', 'PAUSED', 'PENDING_CANCEL')";
        $subscriptions = $wpdb->get_results($query);
        if (empty($subscriptions)) {
            return;
        }
        $usersNeedUpdate = [];
        foreach ($subscriptions as $subscription) {
            $order = wc_get_order($subscription->initial_order_id);
            if (!$order) {
                Functions::log(
                    'Pedido inicial não encontrado para assinatura. Impossível atualizar restrição de conteúdo 
                    para esta assinatura.',
                    'error',
                    ['subscription' => $subscription->id]
                );
                continue;
            }
            $userId = $order->get_customer_id();
            if (!in_array($userId, $usersNeedUpdate)) {
                $usersNeedUpdate[] = $userId;
            }
        }
        foreach ($usersNeedUpdate as $userId) {
            $this->updateUserRestrictions($userId);
        }
    }

    /**
     * @param $subscriptionId
     * @return void
     */
    public function subscriptionMaybeChargeAndUpdate($subscriptionId)
    {
        $subscription = $this->getSubscription($subscriptionId);
        if ($subscription->status === 'SUSPENDED') {
            $recurringOrder = new Connect\Recurring\RecurringOrder($subscription);
            $recurringOrder->createRecurringOrderFromSub();
        }
    }

    public function addNotice($message, $reasonType, $class = null){

        if( $reasonType == 'ADMIN' ){
            set_transient('pagbank_recurring_message', $message, 30); // 30 seconds
            return;
        }

        \wc_add_notice($message, $class);
    }
    /**
     * @return bool Returns true on success, false on failure.
     */
    public static function subscriptionSandboxClear()
    {

        $orders = self::getOrderInitialSandbox();

        $exists = count($orders) > 0;     
        $force_clear = isset($_GET['clear_recurring']);
        // Force refresh if requested via URL
        if ($force_clear && $exists) {
            try {
                $removed = self::removeSubscriptionSandbox($orders);
                if ($removed) {
                    $message = sprintf(__('Assinaturas sandbox removidas com sucesso: %d', 'pagbank-connect'), $removed);
                    set_transient('pagbank_recurring_message', $message, 30); // 30 seconds
                }
            } catch (\Throwable $th) {
                $message = __('Nenhuma assinatura sandbox foi removida.', 'pagbank-connect');
                set_transient('pagbank_recurring_message', $message, 30); // 30 seconds
            }
            // Reload the page to show the admin notice
            echo '<script>window.location.reload();</script>';
            exit;
        }
        return $exists;
    }

    /**
     * Removes all sandbox subscriptions and marks them as removed.
     * @param mixed $orders
     * @return false|integer
     */
    public static function removeSubscriptionSandbox($orders)
    {
        global $wpdb;

        if(!$orders || count($orders) <= 0) return false;
        
        $count = 0;
        foreach ($orders as $order) {
            if($order->get_meta('pagbank_is_sandbox') !== "1"){
                continue;
            }

            // Add meta_data to mark as removed
            $order->add_meta_data('_pagbank_recurring_removed', 1, true);
            $order->save();

            // Delete subscription from pagbank_recurring table
            $deleted = $wpdb->delete(
            $wpdb->prefix . 'pagbank_recurring',
            ['initial_order_id' => $order->get_id()],
            ['%d']
            );
            if($deleted) $count++;
            
        }
        return $count;
    }

    public static function getOrderInitialSandbox()
    {
        if (wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()) {
            return wc_get_orders([
            'limit'        => -1,
            'relation' => 'AND',
            'meta_query'   => [
                [
                    'key'     => '_pagbank_recurring_initial',
                    'value'   => '1',
                ],
                [
                    'key'     => 'pagbank_is_sandbox',
                    'value'   => '1',
                ],
                [
                    'key'     => '_pagbank_recurring_removed',
                    'compare' => 'NOT EXISTS',
                ],
            ]
            ]);
        }


        // else, HPOS is disabled
        $args = array(
            'post_type'      => 'shop_order',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_pagbank_recurring_initial',
                    'value'   => '1',
                    'compare' => '='
                ],
                [
                    'key'     => 'pagbank_is_sandbox',
                    'value'   => '1',
                    'compare' => '=' // ou 'LIKE' se não funcionar
                ],
                [
                    'key'     => '_pagbank_recurring_removed',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        );

        $query = new \WP_Query($args);

        $recurringOrders = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $order_id = get_the_ID();
                $order = wc_get_order($order_id);
                $recurringOrders[] = $order;
            }
            wp_reset_postdata();
        }

        return $recurringOrders;
    }
}