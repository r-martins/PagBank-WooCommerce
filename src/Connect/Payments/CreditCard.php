<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Connect;
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
        $amount->setValue(Params::convertToCents($this->order->get_total()));
        $charge->setAmount($amount);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setType('CREDIT_CARD');
        $paymentMethod->setCapture(true);
        $paymentMethod->setInstallments(intval($this->order->get_meta('pagbank_card_installments')));
        $paymentMethod->setSoftDescriptor(Params::getConfig('cc_soft_descriptor'));
        $card = $this->getCardDetails();
        $paymentMethod->setCard($card);

        //3ds
        if ($this->order->get_meta('_pagbank_card_3ds_id') && Params::getConfig('cc_3ds') === 'yes') {
            $authMethod = new AuthenticationMethod();
            $authMethod->setType('THREEDS');
            $authMethod->setId($this->order->get_meta('_pagbank_card_3ds_id'));
            $paymentMethod->setAuthenticationMethod($authMethod);
        }
        
        $charge->setPaymentMethod($paymentMethod);

        if ($paymentMethod->getInstallments() > 1) {
            $selectedInstallments = $paymentMethod->getInstallments();
            $installments = Params::getInstallments(
                $this->order->get_total(),
                $this->order->get_meta('_pagbank_card_first_digits')
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
        if ($this->order->get_meta('_pagbank_recurring_initial')) {
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
        
        if ($this->order->get_meta('_pagbank_is_recurring') === true) {
            $recurring->setType('SUBSEQUENT');
            $charge->setRecurring($recurring);
        }
        //endregion

        $return['charges'] = [$charge];
        return $return;
    }

    /**
    * Outputs the installment options to populate the select field on checkout
    * @return void
    */
    public static function getAjaxInstallments()
    {
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
            $orderTotal = floatval($woocommerce->cart->get_total('edit'));
        }
        
        if ($orderTotal <= 0) {
            wp_send_json(
                ['error' => __('Não foi possível obter as parcelas. Total do pedido inválido.', 'pagbank-connect')],
                400
            );
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
        if ($this->order->get_meta('_pagbank_is_recurring') === true)
        {
            //get card data from subscription
            global $wpdb;
            $initialSubOrderId = $this->order->get_parent_id('edit');
            $sql = "SELECT * from {$wpdb->prefix}pagbank_recurring WHERE initial_order_id = 0{$initialSubOrderId}";
            $recurring = $wpdb->get_row( $wpdb->prepare( $sql ) );
            $paymentInfo = json_decode($recurring->payment_info);
            $card->setId($paymentInfo->card->id);
            $holder = new Holder();
            $holder->setName($paymentInfo->card->holder_name);
            $card->setHolder($holder);
            $card->setStore(true);
            return $card;
        }
        
        //non recurring...
        $card->setEncrypted($this->order->get_meta('_pagbank_card_encrypted'));
        $holder = new Holder();
        $holder->setName($this->order->get_meta('_pagbank_card_holder_name'));
        $card->setHolder($holder);

        return $card;
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
        
        $orderDetails = [
            'data' => [
                'customer' => [
                    'name'           => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                    'email'          => $order->get_billing_email(),
                    'phones'         => [
                        [
                            'country' => '55',
                            'area'    => substr(Params::removeNonNumeric($order->get_billing_phone()), 0, 2),
                            'number'  => substr(Params::removeNonNumeric($order->get_billing_phone()), 2),
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
                    'regionCode' => preg_replace('/\s+/', ' ', $order->get_billing_state()),
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
        
        delete_transient('rm_pagbank_product_installment_info_' . $product->get_id());

        $ccInstallmentProductPage = Params::getConfig('cc_installment_product_page');

        if ($ccInstallmentProductPage === 'yes') {
            $default_installments = Params::getInstallments($product->get_price(), '555566');

            if ($default_installments) {
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
                    'rm_pagbank_product_installment_info_'.$product->get_id(),
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

        $ccEnabledInstallments = Params::getConfig('cc_installment_product_page');

        if ($ccEnabledInstallments === 'yes') {
            $product_id = $product->get_id();

            $installment_info = get_transient('rm_pagbank_product_installment_info_' . $product_id);

            if (!$installment_info) {
                self::updateProductInstallmentsTransient($product, ['product_page']);
                $installment_info = get_transient('rm_pagbank_product_installment_info_' . $product_id);
            }

            if ($installment_info) {
                $type = Params::getConfig('cc_installment_product_page_type', 'table');
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
                
                load_template($template_path, false, $args);
            }
        }
    }

    /**
     * Function to delete the installment transients if the configuration has changed
     * @return void
     */
    public static function deleteInstallmentsTransientIfConfigHasChanged()
    {
        $ccInstallmentProductPage = Params::getConfig('cc_installment_product_page');

        $cc_installment_options = Params::getConfig('cc_installment_options');
        $cc_installment_options_fixed = Params::getConfig('cc_installment_options_fixed');
        $cc_installments_options_min_total = Params::getConfig('cc_installments_options_min_total');
        $cc_installments_options_limit_installments = Params::getConfig('cc_installments_options_limit_installments');
        $cc_installments_options_max_installments = Params::getConfig('cc_installments_options_max_installments');

        $installment_options = array(
            'installments' => $cc_installment_options,
            'installments_fixed' => $cc_installment_options_fixed,
            'min_total' => $cc_installments_options_min_total,
            'limit_installments' => $cc_installments_options_limit_installments,
            'max_installments' => $cc_installments_options_max_installments
        );

        $installment_options = json_encode($installment_options);

        if ($ccInstallmentProductPage === 'no'
            || $installment_options !== get_transient(
                'pagbank_product_installment_options'
            )) {
            $product_ids = wc_get_products(array(
                'status' => 'publish',
                'limit' => -1,
                'return' => 'ids',
            ));

            foreach ($product_ids as $product_id) {
                delete_transient('rm_pagbank_product_installment_info_' . $product_id);
                delete_transient('wc_related_' . $product_id);
                delete_transient('timeout_wc_related_' . $product_id);
            }
        }

        set_transient('pagbank_product_installment_options', $installment_options, YEAR_IN_SECONDS);
    }
}
