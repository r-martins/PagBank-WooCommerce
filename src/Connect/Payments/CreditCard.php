<?php

namespace RM_PagBank\Connect\Payments;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Object\Amount;
use RM_PagBank\Object\AuthenticationMethod;
use RM_PagBank\Object\Buyer;
use RM_PagBank\Object\Card;
use RM_PagBank\Object\Charge;
use RM_PagBank\Object\Fees;
use RM_PagBank\Object\Holder;
use RM_PagBank\Object\Interest;
use RM_PagBank\Object\PaymentMethod;
use RM_PagBank\Object\Recurring;
use WC_Order;
use WC_Payment_Tokens;
use RM_PagBank\Helpers\OrderMeta;

/**
 * Class CreditCard
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Payments
 */
class CreditCard extends Common
{
    public string $code = 'credit_card';

    /**
	 * @param WC_Order $order
	 */
    public function __construct(WC_Order $order)
    {
        parent::__construct($order);
    }

    /**
     * Create the array with the data to be sent to the API on CreditCard payments
     *
     * @return array
     */
    public function prepare():array
    {
        $return = $this->getDefaultParameters();
        $charge = new Charge();
        $amount = new Amount();

        $orderTotal = $this->order->get_total();

        $amount->setValue(Params::convertToCents($orderTotal));
        $charge->setAmount($amount);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setType('CREDIT_CARD');
        $paymentMethod->setCapture(true);
        $paymentMethod->setInstallments(intval(OrderMeta::get($this->order, 'card_installments')));
        $api = new Api();
        // Soft descriptor is PagBank-only; Vindi configures statement descriptor in their admin.
        if (!$api->partner()->isVindi()) {
            $paymentMethod->setSoftDescriptor(Params::getCcConfig('cc_soft_descriptor'));
        }
        $card = $this->getCardDetails();
        $paymentMethod->setCard($card);

        if ($api->partner()->isVindi()) {
            $profileId = (int) OrderMeta::get($this->order, '_payment_profile_id');
            if ($profileId <= 0) {
                try {
                    $fromCard = $card->getPaymentProfileId();
                    if ($fromCard > 0) {
                        $profileId = $fromCard;
                    }
                } catch (\Throwable $e) {
                    // Card.payment_profile_id may be uninitialized
                }
            }
            $wantSave = OrderMeta::get($this->order, '_save_card') === 'yes'
                || OrderMeta::get($this->order, '_recurring_initial');
            if ($profileId > 0) {
                $paymentMethod->setPaymentProfileId($profileId);
                $card->setPaymentProfileId($profileId);
                OrderMeta::set($this->order, '_payment_profile_id', $profileId, true);
            }
            // Never discard when charging a known profile or when the shopper asked to save the card.
            $paymentMethod->setDiscardCreditCard(!($profileId > 0 || $wantSave));
            $vindiCustomerId = (int) OrderMeta::get($this->order, '_vindi_customer_id');
            if ($vindiCustomerId > 0) {
                $paymentMethod->setCustomerId($vindiCustomerId);
            }
            $threeDsRaw = OrderMeta::get($this->order, '_card_3ds_payload');
            if (is_string($threeDsRaw) && $threeDsRaw !== '') {
                $decoded = json_decode($threeDsRaw, true);
                if (is_array($decoded) && $decoded !== []) {
                    // Explicit PB type — API maps 1:1 to Vindi payment_method_code credit_card_3ds.
                    $paymentMethod->setType('CREDIT_CARD_3DS');
                    $paymentMethod->setThreeDs($decoded);
                }
            }
        }

        // Check if split with liable will be applied
        $hasSplitWithLiable = false;
        if (\RM_PagBank\Integrations\Dokan\DokanSplitManager::shouldApplySplit($this->order)) {
            $hasSplitWithLiable = true;
        }

        //3ds - Cannot be used with split transactions that have liable configuration
        // Vindi 3DS uses payment_method.three_ds + payment_profile_id; PagBank uses authentication_method id
        if ($hasSplitWithLiable && OrderMeta::get($this->order, '_card_3ds_id')) {
            // Log that 3DS is being skipped due to split
            Functions::log('3DS authentication skipped for order ' . $this->order->get_id() . ' due to split transaction with liable configuration', 'info');
        } elseif (!$hasSplitWithLiable
            && !$api->partner()->isVindi()
            && OrderMeta::get($this->order, '_card_3ds_id')
            && (wc_string_to_bool(Params::getCcConfig('cc_3ds')) || wc_string_to_bool(Params::getCcConfig('cc_3ds_retry')))) {
            $authMethod = new AuthenticationMethod();
            $authMethod->setType('THREEDS');
            $authMethod->setId(OrderMeta::get($this->order, '_card_3ds_id'));
            $paymentMethod->setAuthenticationMethod($authMethod);
        }
        
        $charge->setPaymentMethod($paymentMethod);

        if ($paymentMethod->getInstallments() > 1) {
            $selectedInstallments = $paymentMethod->getInstallments();
            $installments = Params::getInstallments(
                $this->order->get_total(),
                OrderMeta::get($this->order, '_card_first_digits')
            );
            $installment = Params::extractInstallment($installments, $selectedInstallments);
            if ($installment['fees']) {
                $interest = new Interest();
                $interest->setInstallments($installment['fees']['buyer']['interest']['installments']);
                $interest->setTotal($installment['fees']['buyer']['interest']['total']);
                $buyer = new Buyer();
                $buyer->setInterest($interest);
                $fees = new Fees();
                $fees->setBuyer($buyer);
                $amount->setFees($fees);
                $amount->setValue($installment['total_amount_raw']);
            }
        }

        //region Recurring initial or subsequent order
        $recurring = new Recurring();
        if (OrderMeta::get($this->order, '_recurring_initial')) {
            $recurring->setType('INITIAL');
            $charge->setRecurring($recurring);
            $card->setStore(true);
            $paymentMethod->setCard($card);
//            if (floatval($this->order->get_meta('_recurring_initial_fee')) > 0) {
//                $currentAmount = $charge->getAmount()->getValue();
//                $initialFee = $this->order->get_meta('_recurring_initial_fee');
//                $newAmount = new Amount();
//                $newAmount->setValue($currentAmount + Params::convertToCents($initialFee));
//                $charge->setAmount($newAmount);
//            }
        }
        
        if (OrderMeta::get($this->order, '_is_recurring') === true) {
            $recurring->setType('SUBSEQUENT');
            $charge->setRecurring($recurring);
        }
        //endregion

        //region Split Integration
        // Check Dokan Split first (if Dokan is active)
        if (\RM_PagBank\Integrations\Dokan\DokanSplitManager::shouldApplySplit($this->order)) {
            $splitManager = new \RM_PagBank\Integrations\Dokan\DokanSplitManager();
            $splitData = $splitManager->buildSplitData($this->order, 'CREDIT_CARD');
            $charge->setSplits($splitData->jsonSerialize());
        } elseif (($splitData = apply_filters('pagbank_connect_split_for_order', null, $this->order, 'CREDIT_CARD')) && $splitData instanceof \RM_PagBank\Object\Split) {
            $charge->setSplits($splitData->jsonSerialize());
        } elseif (\RM_PagBank\Integrations\GeneralSplitManager::shouldApplySplit($this->order)) {
            $splitData = \RM_PagBank\Integrations\GeneralSplitManager::buildSplitData($this->order, 'CREDIT_CARD');
            $charge->setSplits($splitData->jsonSerialize());
        }
        //endregion

        // PagBank API does not allow liable configuration on recurring charges with split.
        if ($this->isRecurringCharge()) {
            $splitPayload = $charge->getSplits();
            if (!empty($splitPayload['receivers']) && is_array($splitPayload['receivers'])) {
                foreach ($splitPayload['receivers'] as $receiver) {
                    if ($receiver instanceof \RM_PagBank\Object\Receiver) {
                        $receiver->unsetLiable();
                    }
                }
            }
        }

        $return['charges'] = [$charge];
        return $return;
    }

    /**
     * Whether this order is a recurring charge (INITIAL or SUBSEQUENT).
     * Used to strip liable from split payload when recurring (API limitation).
     */
    protected function isRecurringCharge(): bool
    {
        return (bool) OrderMeta::get($this->order, '_recurring_initial')
            || wc_string_to_bool((string) OrderMeta::get($this->order, '_is_recurring'));
    }

    /**
     * Persist installment display/calculation meta from PagBank order/charge API response
     * (same payload as POST /orders or webhooks). Avoids a duplicate fees/calculate call.
     *
     * @param array $charge Single element from response charges[].
     */
    public static function applyChargeInstallmentMeta(WC_Order $order, array $charge): void
    {
        $chargeType = (string) ($charge['payment_method']['type'] ?? '');
        if (!in_array($chargeType, ['CREDIT_CARD', 'CREDIT_CARD_3DS'], true)) {
            return;
        }

        $rawValue = $charge['amount']['value'] ?? null;
        if (! is_numeric($rawValue) || (float) $rawValue <= 0) {
            return;
        }

        $decimals = wc_get_price_decimals();
        $totalReais = max(0, (float) $rawValue / 100);

        OrderMeta::set($order, 'cc_total_charged', wc_format_decimal($totalReais, $decimals));

        $inst = (int) ($charge['payment_method']['installments'] ?? 1);
        if ($inst < 1) {
            $inst = 1;
        }

        OrderMeta::set($order, 'cc_installment_amount', wc_format_decimal($totalReais / $inst, $decimals)
        );

        $interestRaw = $charge['amount']['fees']['buyer']['interest']['total'] ?? null;
        $interestReais = (is_numeric($interestRaw) && (float) $interestRaw > 0)
            ? max(0, (float) $interestRaw / 100)
            : 0.0;

        // PagBank may return amount.fees.buyer.interest. Vindi does not charge buyer
        // interest as fees — it only inflates the charge amount. Infer from charged vs cart.
        if ($interestReais <= 0) {
            $orderTotal = (float) $order->get_total('edit');
            $diff = round($totalReais - $orderTotal, $decimals);
            if ($diff > 0) {
                $interestReais = $diff;
            }
        }

        if ($interestReais > 0) {
            OrderMeta::set($order, '_cc_buyer_interest_total', wc_format_decimal($interestReais, $decimals));
            OrderMeta::set($order, 'cc_interest_free', 'no');
        } else {
            OrderMeta::delete($order, '_cc_buyer_interest_total');
            OrderMeta::set($order, 'cc_interest_free', 'yes');
        }
    }

    /**
    * Outputs the installment options to populate the select field on checkout
    * @return void
    */
    public static function getAjaxInstallments()
    {
        $recurringHelper = new \RM_PagBank\Helpers\Recurring();
        $recurring = $recurringHelper->isCartRecurring();

        $isCheckoutBlocks = Functions::isCheckoutBlocks();
        if ($recurring && $isCheckoutBlocks) {
            return;
        }

        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'rm_pagbank_nonce')) {
            wp_send_json_error(
                [
                    'error' => __(
                        'Não foi possível obter as parcelas. Chave de formulário inválida. '
                        .'Recarregue a página e tente novamente.',
                        'pagbank-connect'
                    ),
                ],
                400
            );
        }

        $ccBin = isset($_REQUEST['cc_bin']) ? intval($_REQUEST['cc_bin']) : 0;
        $ccBin = Params::getConfig('is_sandbox', false) ? 555566 : $ccBin; // always use 555566 for sandbox

        //order id provided when  in order-pay page
        $orderId = !empty($_POST['order_id']) ? Functions::decrypt(
            sanitize_text_field($_POST['order_id'])
        ) : 0;
        
        $orderTotal = 0;
        
        if ($orderId) {
            $order = wc_get_order($orderId);
            if ($order) {
                $orderTotal = floatval($order->get_total('edit'));
            }
        }

        if (!$orderTotal) {
            global $woocommerce;
            $cart = $woocommerce->cart ?? null;
            $session = WC()->session;
            $previousChosen = $session ? $session->get('chosen_payment_method') : '';
            // Installments always reflect credit-card totals; session may still be PIX
            // from the previous selection when this AJAX runs before cart recalculation.
            if ($session) {
                $session->set('chosen_payment_method', Connect::DOMAIN . '-cc');
            }
            if ($cart) {
                $cart->calculate_totals();
            }
            $orderTotal = floatval($cart ? $cart->get_total('edit') : 0);
            if ($session) {
                $session->set('chosen_payment_method', $previousChosen);
            }
        }
        
        // Empty cart (e.g. Funnel Kit removes last item on checkout): return empty
        // plans with HTTP 200 so the storefront can hide the dropdown without an alert.
        if ($orderTotal <= 0) {
            wp_send_json([]);
        }

        $installments = Params::getInstallments($orderTotal, $ccBin);
        if (isset($installments['error'])) {
            $error = $installments['error'];
            wp_send_json(
                ['error' => sprintf(__('Não foi possível obter as parcelas. %s', 'pagbank-connect'), $error)],
                400
            );
        }
        wp_send_json($installments);
    }

    /**
     * Populates the Card object considering with data from order or subscription
     * @return Card
     */
    protected function getCardDetails(): Card
    {
        $card = new Card();
        //if subsequent recurring order...
        if (OrderMeta::get($this->order, '_is_recurring') === true)
        {
            //get card data from subscription
            global $wpdb;
            $initialSubOrderId = $this->order->get_parent_id('edit');
            $sql = "SELECT * from {$wpdb->prefix}pagbank_recurring WHERE initial_order_id = 0%d;";
            $recurring = $wpdb->get_row( $wpdb->prepare( $sql, $initialSubOrderId ) );
            $paymentInfo = json_decode($recurring->payment_info);
            $holder = new Holder();
            $holder->setName($paymentInfo->card->holder_name ?? '');
            $card->setHolder($holder);
            $card->setStore(true);

            $cardId = $paymentInfo->card->id ?? '';
            $api = new Api();
            $isVindiCard = $api->partner()->isVindi()
                || (($paymentInfo->partner ?? '') === 'vindi');
            if ($isVindiCard) {
                $profileId = (int) $cardId;
                if ($profileId > 0) {
                    $card->setPaymentProfileId($profileId);
                    OrderMeta::set($this->order, '_payment_profile_id', $profileId, true);
                }
                $vindiCustomerId = (int) ($paymentInfo->vindi_customer_id ?? 0);
                if ($vindiCustomerId > 0) {
                    OrderMeta::set($this->order, '_vindi_customer_id', $vindiCustomerId, true);
                }
                return $card;
            }

            $card->setId($cardId ?: '');
            return $card;
        }
        
        $holder = new Holder();
        $holder->setName(OrderMeta::get($this->order, '_card_holder_name'));
        $card->setHolder($holder);
        $token_id = OrderMeta::get($this->order, '_card_token_id');
        if($token_id && !empty($token_id) && 'new' !== $token_id){
            $tokenCc = self::getCcToken($token_id);
            OrderMeta::set($this->order, 'card_last4', $tokenCc->get_last4(), true);
            OrderMeta::set($this->order, '_card_first_digits', $tokenCc->get_meta( 'cc_bin' ), true);
            $api = new Api();
            if ($api->partner()->isVindi()) {
                $profileId = (int) ($tokenCc->get_meta('vindi_payment_profile_id') ?: $tokenCc->get_token());
                if ($profileId > 0) {
                    $card->setPaymentProfileId($profileId);
                    $holderName = (string) $tokenCc->get_meta('holder_name');
                    if ($holderName !== '') {
                        $holder->setName($holderName);
                        $card->setHolder($holder);
                    }
                    return $card;
                }
            }
            $card->setId($tokenCc->get_token() ?: '');
            return $card;
        }
        //non recurring...
        $api = new Api();
        if ($api->partner()->isVindi()) {
            $profileId = (int) OrderMeta::get($this->order, '_payment_profile_id');
            if ($profileId > 0) {
                $card->setPaymentProfileId($profileId);
                return $card;
            }
            $token = OrderMeta::get($this->order, '_card_gateway_token');
            if (!$token) {
                // fallback: reuse encrypted field if store posted gateway_token there during transition
                $token = OrderMeta::get($this->order, '_card_encrypted');
            }
            $card->setGatewayToken((string) $token);
            return $card;
        }
        $card->setEncrypted(OrderMeta::get($this->order, '_card_encrypted'));

        return $card;
    }

    /**
     * Outputs a fresh 3D Secure session (used via ajax immediately before authenticate3DS)
     * @return void
     */
    public static function getAjax3DSession()
    {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'rm_pagbank_nonce')) {
            wp_send_json_error(
                [
                    'error' => __(
                        'Não foi possível atualizar a sessão 3D Secure. Chave de formulário inválida. '
                        .'Recarregue a página e tente novamente.',
                        'pagbank-connect'
                    ),
                ],
                400
            );
        }

        $api = new Api();
        $session = $api->get3DSession(false);

        if ($session === '') {
            wp_send_json_error(
                [
                    'error' => __(
                        'Não foi possível atualizar a sessão 3D Secure. Recarregue a página e tente novamente.',
                        'pagbank-connect'
                    ),
                ],
                400
            );
        }

        wp_send_json(['session' => $session]);
    }

    /**
     * Outputs the cart total (used via ajax with nonce validation)
     * @return void
     */
    public static function getCartTotal()
    {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'rm_pagbank_nonce')) {
            wp_send_json_error([
                'error' => __(
                    'Não foi possível obter o total. Chave de formulário inválida. '
                    .'Recarregue a página e tente novamente.',
                    'pagbank-connect'
                ),
            ],
                400);
        }
        global $woocommerce;
        Params::getInstallments(floatval($woocommerce->cart->get_total('edit')), intval($_POST['ccBin']));
        echo esc_html( $woocommerce->cart->get_total('edit') );
        wp_die();
    }

    /**
     * Adds order details to /order-pay page in order to correctly process credit card payments (with 3Ds)
     * @param $template_name
     *
     * @return void
     */
    public static function orderPayScript($template_name)
    {
        if (strpos($template_name, 'checkout/form-pay.php') === false) {
            return;
        }

        $orderId = isset($GLOBALS['order-pay']) ? intval($GLOBALS['order-pay']) : false;
        $order = wc_get_order($orderId);
        
        if (!$order) {
            return;
        }

        //HPOS compatibility
        $billingNumber = !empty($order->get_meta('_billing_number')) ? $order->get_meta('_billing_number')
            : $order->get_billing_address_2();
        
        $billingNeighborhood = !empty($order->get_meta('_billing_neighborhood')) ? $order->get_meta(
            '_billing_neighborhood'
        ) : 'n/d';
        $phone = !empty($order->get_meta('billing_cellphone')) ? $order->get_meta('billing_cellphone') : $order->get_billing_phone();
        $orderDetails = [
            'data' => [
                'customer' => [
                    'name'           => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                    'email'          => strtolower($order->get_billing_email()),
                    'phones'         => [
                        [
                            'country' => '55',
                            'area'    => substr(Params::removeNonNumeric($phone), 0, 2),
                            'number'  => substr(Params::removeNonNumeric($phone), 2),
                            'type'    => 'MOBILE'
                        ]
                    ],
                ],
                'amount'         => [
                    'currency' => 'BRL',
                    'value'    => intval(round($order->get_total('edit') * 100)),
                ],
                'billingAddress' => [
                    'street'     => preg_replace('/\s+/', ' ', $order->get_billing_address_1()),
                    'number'     => $billingNumber,
                    'complement' => $billingNeighborhood,
                    'regionCode' => preg_replace('/\s+/', ' ', strtoupper($order->get_billing_state())),
                    'country'    => 'BRA',
                    'city'       => preg_replace('/\s+/', ' ', $order->get_billing_city()),
                    'postalCode' => Params::removeNonNumeric($order->get_billing_postcode()),
                ],
            ],
            'encryptedOrderId' => Functions::encrypt($orderId),
        ];

        echo '<script type="text/javascript">var pagBankOrderDetails = ' . wp_json_encode($orderDetails) . ';</script>';
    }

    /**
     * Function to update the transient when the product is updated
     * @param int $product_id The ID of the product being updated
     * @return void
     */
    public static function updateProductInstallmentsTransient($product, $updatedProps)
    {
        if (!array_intersect(['regular_price', 'sale_price', 'product_page'], $updatedProps)) {
            return;
        }
        
        if (!$product) {
            return;
        }

        $product_id   = $product->get_id();
        $price        = $product->get_price();
        $parent_id    = get_class($product) == 'WC_Product_Variation' ? $product->get_parent_id() : null;

        // If the product is a variation, we need to create a cache key for the parent product
        if ($parent_id) {
            $variation_cache_key = sprintf("rm_pagbank_product_installment_info_%d_variation_%d", $parent_id, $product_id);
            self::buildTransactionData($variation_cache_key, $price);
            return; // break
        }
        // Permanent cache key for the product
        $main_cache_key = sprintf("rm_pagbank_product_installment_info_%d", $parent_id ?: $product_id);
        self::buildTransactionData($main_cache_key, $price);
    }

    /**
     * Function to update the transient when the product is updated or created
     * @param  $product       
     * @param  $updated_props 
     * @return void
     */
    public static function updateProductTransient($product, $updatedProps)
    {
        if($product->get_type() == "variable"){
            // configurable products do not have a price,
            // but the hook for updateProductVariationTransient will be triggered next
            return; 
        }

        self::updateProductInstallmentsTransient($product, $updatedProps);
    }
    /**
     * Function to update the transient when the product variation is updated or created
     * @param int $product_id
     * @param object $product
     * @return void
     */
    public static function updateProductVariationTransient($product_id, $product)
    {
        if (!$product_id || !$product) {
            return;
        }
        self::updateProductInstallmentsTransient($product, $product->get_changes());
    }

    public static function buildTransactionData($transientId, $price)
    {
        delete_transient($transientId);

        $ccInstallmentProductPage = Params::getCcConfig('cc_installment_product_page');
        $ccShortcodeInUse = Params::getCcConfig('cc_installment_shortcode_enabled');

        if ($ccInstallmentProductPage === 'yes' || $ccShortcodeInUse === 'yes') {
            $default_installments = Params::getInstallments($price, '555566');

            if ($default_installments && !isset($default_installments['error'])) {
                $installments = [];

                foreach ($default_installments as $installment) {
                    $amount = number_format($installment['installment_amount'], 2, ',', '.');
                    $total_amount = number_format($installment['total_amount'], 2, ',', '.');
                    $installments[] = [
                        'installments' => $installment['installments'],
                        'amount' => $amount,
                        'interest_free' => $installment['interest_free'],
                        'total_amount' => $total_amount
                    ];
                }

                $installmentsData = wp_json_encode($installments);
            }

            if (!empty($installmentsData)) {
                set_transient(
                    $transientId,
                    $installmentsData,
                    YEAR_IN_SECONDS
                );
            }
        }
    }

    /**
     * Outputs the installment table for the product
     * @return void
     */
    public static function getProductInstallments()
    {
        global $product;

        if (!$product || $product->get_meta('_recurring_enabled') == 'yes') {
            return;
        }

        $ccEnabledInstallments = Params::getCcConfig('cc_installment_product_page');

        $calledByDoShortcode = Functions::isCalledByDoShortcode();
        $ccShortcodeInUse = Params::getCcConfig('cc_installment_shortcode_enabled');
        
        if (($ccEnabledInstallments === 'yes' && !$calledByDoShortcode)
            || ($calledByDoShortcode && $ccShortcodeInUse === 'yes')) {
            $product_id = $product->get_id();

            $installment_info = get_transient('rm_pagbank_product_installment_info_' . $product_id);

            if (!$installment_info) {
                self::updateProductInstallmentsTransient($product, ['product_page']);
                $installment_info = get_transient('rm_pagbank_product_installment_info_' . $product_id);
            }

            if ($installment_info) {
                $type = Params::getCcConfig('cc_installment_product_page_type', 'table');
                $type = preg_replace("/[^a-z\-]/", "", $type); //safety is paramount
                $template_name = "product-installments-$type.php";
                $template_path = locate_template('pagbank-connect/' . $template_name);
                $args = json_decode($installment_info);
                
                if (!$template_path) {
                    $template_path = dirname(__FILE__) . '/../../templates/product/' . $template_name;
                    if (!file_exists($template_path)) {
                        return;
                    }
                }
                
                if (!$args) {
                    return;
                }

                if($product->get_type() == 'variable') {
                    self::addScriptProductVariableInstallments();
                }
                //checks if is being called by do_shortcode so don't output the template
                if ($calledByDoShortcode)
                    ob_start();
                
                load_template($template_path, false, $args);
                
                if ($calledByDoShortcode)
                    return ob_get_clean();
                
            }
        }
    }

    /**
     * Add the script to the product variable page
     * 
     * @param string $type
     * @return void
     */
    public static function addScriptProductVariableInstallments()
    {
        if(is_admin()){
            return;
        }

        // Define payment handle baseado na versão do WooCommerce (wc-jquery-payment desde 10.3.0)
        $payment_handle = wp_script_is('wc-jquery-payment', 'registered') ? 'wc-jquery-payment' : 'jquery-payment';
        
        wp_enqueue_script(
            'pagseguro-connect-product-variable',
            plugins_url('public/js/product-variable.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            ['jquery', $payment_handle],
            WC_PAGSEGURO_CONNECT_VERSION,
            ['strategy' => 'defer', 'in_footer' => true]
        );

        wp_localize_script(
            'pagseguro-connect-product-variable',
            'ajax_object',
            ['rest_installments' => get_rest_url(null, 'pagbank/installments/event/')]
        );
    }

    public static function restApiInstallments()
    {
        register_rest_route('pagbank/installments', '/event/', [
            'methods'  => 'GET',
            'callback' => [static::class, 'getProductVariableInstallmentsAjax'],
            'permission_callback' => '__return_true' // ou lógica de permissão
        ]);
    }

    /**
     * Return Table HTML
     * 
     * @return WP_REST_Response|WP_Error|WP_HTTP_Response|mixed
     */
    public static function getProductVariableInstallmentsAjax(){

        $_productId = (int) $_GET['_product_id'] ?? 0;
        $_variationId = (int) $_GET['_variation_id'] ?? 0;
        $_price = (float) $_GET['_price'] ?? 0;
        
        if(!$_productId || !$_variationId || !$_price) {
            return rest_ensure_response([
                'status' => 'error',
                'html' => __('Invalid product or variation ID', 'pagbank-connect'),
            ]);
        }
     
        $ccEnabledInstallments = Params::getCcConfig('cc_installment_product_page');
        $ccShortcodeInUse = Params::getCcConfig('cc_installment_shortcode_enabled');

        if ($ccEnabledInstallments === 'yes' || $ccShortcodeInUse === 'yes') {
            $transient_id = sprintf("rm_pagbank_product_installment_info_%d_variation_%d", $_productId, $_variationId);
            $installment_info = get_transient($transient_id);
            if (!$installment_info) {
                self::buildTransactionData($transient_id, $_price);
                $installment_info = get_transient($transient_id);
            }
            if ($installment_info) {
                $type = Params::getCcConfig('cc_installment_product_page_type', 'table');
                $type = preg_replace("/[^a-z\-]/", "", $type); //safety is paramount
                $template_name = "product-installments-$type.php";
                $template_path = locate_template('pagbank-connect/' . $template_name);
                $args = json_decode($installment_info);
                
                if (!$template_path) {
                    $template_path = dirname(__FILE__) . '/../../templates/product/' . $template_name;
                }
        
                ob_start();
                    load_template($template_path, false, $args); 
                $html = ob_get_clean();

            }  
        }

        return rest_ensure_response([
            'status' => 'ok',
            'html' => $html ?? '',
            'installments' => $installment_info ?? [],
        ]);
    }
    /**
     * Function to delete the installment transients if the configuration has changed
     * @return void
     */
    public static function deleteInstallmentsTransientIfConfigHasChanged($option_name, $old_value, $value)
    {
        if (strpos($option_name, 'rm-pagbank-cc_settings') === false) {
            return;
        }
        
        $isDisablingInstallmentsOnPdpNow = (isset($old_value['cc_installment_product_page']) && $old_value['cc_installment_product_page'] === 'yes')
            && (isset($value['cc_installment_product_page']) && $value['cc_installment_product_page'] === 'no');

        $optionsToCheck = [
            'cc_installment_options',
            'cc_installment_options_fixed',
            'cc_installments_options_min_total',
            'cc_installments_options_limit_installments',
            'cc_installments_options_max_installments'
        ];
        $optionsChanged = false;
        foreach ($optionsToCheck as $option) {
            if (isset($old_value[$option]) && isset($value[$option]) && $old_value[$option] !== $value[$option]) {
                $optionsChanged = true;
                break;
            }
        }
        if ($isDisablingInstallmentsOnPdpNow || $optionsChanged) {
            
            global $wpdb;

            // Delete transients in bulk
            $transient_prefix = '_transient_rm_pagbank_product_installment_info_';
            $transient_timeout_prefix = '_transient_timeout_rm_pagbank_product_installment_info_';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like($transient_prefix) . '%',
                    $wpdb->esc_like($transient_timeout_prefix) . '%',
                )
            );

        }

        $installment_options = array(
            'installments' => $value['cc_installment_options'] ?? '',
            'installments_fixed' => $value['cc_installment_options_fixed'] ?? '',
            'min_total' => $value['cc_installments_options_min_total'] ?? '',
            'limit_installments' => $value['cc_installments_options_limit_installments'] ?? '',
            'max_installments' => $value['cc_installments_options_max_installments'] ?? '',
        );
        
        set_transient('pagbank_product_installment_options', $installment_options, YEAR_IN_SECONDS);
    }

    /**
     * Get Token Cc Woo/PagBank
     *
     * @param mixed $token_id
     *
     * @return \WC_Payment_Token|null
     * @throws Exception
     */
    public static function getCcToken($token_id)
    {
        if ($token_id && $token_id !== 'new') {
            $token = WC_Payment_Tokens::get($token_id);
            if (!$token instanceof \WC_Payment_Token_CC) {
                throw new \Exception(__('Token do cartão salvo não encontrado.', 'pagbank-connect'));
            }
            
            if ($token->get_user_id() !== get_current_user_id()) {
                throw new \Exception(
                    __('Token do cartão salvo é inválido ou não pertence ao usuário atual.', 'pagbank-connect')
                );
            }

            return $token;
        }
        return null;
    }
}
