<?php
namespace RM_PagBank\Connect\Standalone;

use RM_PagBank\Connect;
use RM_PagBank\Connect\Payments\CreditCardToken;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\TaxId;
use RM_PagBank\Traits\PaymentMethodIcon;
use RM_PagBank\Traits\PaymentUnavailable;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\StaticResources;
use RM_PagBank\Traits\ThankyouInstructions;
use WC_Payment_Gateway_CC;
use Exception;
use WC_Admin_Settings;
use WC_Data_Exception;
use WC_Order;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WP_Error;
use RM_PagBank\Helpers\OrderMeta;

/** Standalone Credit Card */
class CreditCard extends WC_Payment_Gateway_CC
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use PaymentMethodIcon;
    use ThankyouInstructions;

    public string $code = '';

    private static $injectedScripts = [];

    public function __construct()
    {
        $this->code = 'cc';
        $this->id = Connect::DOMAIN . '-' . $this->code;
        $this->icon = plugins_url('public/images/cc.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE);
        $this->method_title = $this->get_option(
            'title',
            __('Cartão de Crédito', 'pagbank-connect')
        );
        $this->method_description = __(
            'Receba pagamentos com Cartão de Crédito (por Ricardo Martins)',
            'pagbank-connect'
        );
        $this->title = $this->get_option('title', __('Cartão de Crédito', 'pagbank-connect'));
        $this->description = $this->get_option('description');


        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds',
            'default_credit_card_form',
            'tokenization',
            'add_payment_method',
        ];

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN . '-cc', [$this, 'addThankyouInstructions']);

        // Remove add_payment_method support if save card is disabled
        // Use the supports filter to override the support check, which is more reliable
        add_filter('woocommerce_payment_gateway_supports', [$this, 'maybe_remove_add_payment_method_support_filter'], 10, 3);
        // Also filter gateways on the add payment method page to hide gateways that don't support it
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_gateways_without_add_payment_method_support'], 30, 1);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);
    }
    /**
	 * Builds our payment fields area - including tokenization fields for logged
	 * in users, and the actual payment fields.
	 *
	 * @since 2.6.0
	 */
	public function payment_fields() {

        // Check if it's checkout blocks at runtime to avoid tokenization display
        $display_tokenization = $this->supports( 'tokenization' ) && is_checkout();
        
        if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->form();
			$this->save_payment_method_checkbox();
            echo $this->render_installments_field();
		}else{
            $this->form();
        }
	}

    /**
     * Outputs the save payment method checkbox.
     * Only shows if:
     * - It's a recurring order, OR
     * - The allow_save_card setting is enabled
     */
    public function save_payment_method_checkbox() {
        $recurringHelper = new \RM_PagBank\Helpers\Recurring();
        $isCartRecurring = $recurringHelper->isCartRecurring();
        $allowSaveCard = wc_string_to_bool($this->get_option('cc_allow_save_card', 'no'));

        // Only show checkbox if it's a recurring order OR if save card is enabled
        if (!$isCartRecurring && !$allowSaveCard) {
            return;
        }

        $html = sprintf(
            '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
            esc_attr($this->id),
            esc_html__('Salvar para uso futuro', 'pagbank-connect')
        );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo apply_filters('woocommerce_payment_gateway_save_new_payment_method_option_html', $html, $this);
    }

    public function init_form_fields()
    {
        $this->form_fields = include WC_PAGSEGURO_CONNECT_BASE_DIR . '/admin/views/settings/cc-fields.php';
        $this->adjustVindi3dsFormFields();
    }

    /**
     * Vindi: hide PagBank-only soft-decline retry; keep “allow continue” (card not enrolled in 3DS).
     * Show enable checkbox or “open ticket” notice when credit_card_3ds is unavailable.
     */
    private function adjustVindi3dsFormFields(): void
    {
        $detector = \RM_PagBank\Partner\PartnerDetector::fromSettings();
        if (!$detector->isVindi()) {
            return;
        }

        // PagBank soft-decline → retry with 3DS does not apply to Vindi MPI enroll.
        unset($this->form_fields['cc_3ds_retry']);

        if (isset($this->form_fields['cc_3ds_allow_continue'])) {
            $this->form_fields['cc_3ds_allow_continue']['description'] = __(
                'Alguns cartões não participam do 3D Secure (emissor/bandeira fora do programa). <br/>'
                . 'Ao marcar, o cliente pode concluir a compra sem 3DS nesses casos. <br/>'
                . 'Desmarcado (padrão): a compra é bloqueada e o cliente precisa usar outro cartão ou método.',
                'pagbank-connect'
            );
        }

        if (isset($this->form_fields['cc_soft_descriptor'])) {
            $this->form_fields['cc_soft_descriptor'] = [
                'title' => __('Identificador na Fatura', 'pagbank-connect'),
                'type' => 'title',
                'description' => __(
                    'Com Connect Key Vindi, o nome na fatura é configurado no painel da Vindi em '
                    . '<strong>Configurações → Métodos de Pagamento → Cartão de Crédito</strong>.',
                    'pagbank-connect'
                ),
            ];
        }

        $caps = new \RM_PagBank\Partner\Capabilities($detector);
        if ($caps->has3ds()) {
            return;
        }

        unset($this->form_fields['cc_3ds']);
        $notice = sprintf(
            /* translators: %s = HTML link to Vindi support */
            __('3D Secure não está ativo em sua conta Vindi. %s para solicitar.', 'pagbank-connect'),
            '<a href="' . esc_url('https://vindi.com.br/atendimento/abrir-chamado/') . '" target="_blank" rel="noopener noreferrer">'
            . esc_html__('Abra um chamado', 'pagbank-connect')
            . '</a>'
        );
        // Insert notice where cc_3ds was (before save card).
        $rebuilt = [];
        foreach ($this->form_fields as $key => $field) {
            if ($key === 'cc_allow_save_card') {
                $rebuilt['cc_3ds_unavailable'] = [
                    'title' => __('Autenticação 3D', 'pagbank-connect'),
                    'type' => 'title',
                    'description' => $notice,
                ];
            }
            $rebuilt[$key] = $field;
        }
        $this->form_fields = $rebuilt;
    }

    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
    }

    /**
     * Validates the inputed connect key and save additional information like public key and sandbox mode
     *
     * @param $key
     * @param $connect_key
     *
     * @return mixed|string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate_connect_key_field($key, $connect_key)
    {
        $gateway = new \RM_PagBank\Connect\Gateway();
        return $gateway->validate_connect_key_field($key, $connect_key);
    }

    /**
     * @param $key
     * @param $value
     *
     * @return string
     */
    public function validate_cc_installment_options_fixed_field($key, $value)
    {
        if ($value === "1"){
            WC_Admin_Settings::add_message(
                __(
                    'O número de parcelas sem juros foi alterado para 2. Se quiser oferecer juros por '
                    .'conta do comprador, selecione a opção "Juros por conta do comprador".',
                    'pagbank-connect'
                )
            );
            return "2";
        }
        
        return $value;
    }

    /**
     * Validate frontend fields
     *
     * @return bool
     */
    public function validate_fields():bool
    {
        return true; //@TODO validate_fields
    }

    /**
     * @inheritDoc
     */
    public function form() {
        
        if ($this->paymentUnavailable()) {
            include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/unavailable.php';
            return;
        }

        $this->addScripts(true);
        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payments/creditcard.php';
    }

    /**
     * Process Payment.
     *
     * @param int $order_id Order ID.
     *
     * @return array
     * @throws WC_Data_Exception
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;
        $order = wc_get_order( $order_id );

        //sanitize $_POST['ps_connect_method']
        $payment_method = htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8');

        $recurringHelper = new \RM_PagBank\Helpers\Recurring();
        $isCartRecurring = $recurringHelper->isCartRecurring();

        $payment_method = str_replace('rm-pagbank-', '', $payment_method);
        if ($isCartRecurring) {
            $payment_method = 'cc'; //@TODO change when supporting other methods for recurring orders
        }

        if ($isCartRecurring) {
            OrderMeta::set($order, '_recurring_initial', true);
        }

        // region Add note if customer changed payment method
        $this->handleCustomerChangeMethod($order, $payment_method);
        // endregion

        $recurringTrialPeriod = $recurringHelper->getCartRecurringTrial();
        if ($recurringTrialPeriod) {
            OrderMeta::set($order, '_recurring_trial_length', $recurringTrialPeriod, false);
        }

        $token_id = isset($_POST['wc-rm-pagbank-cc-payment-token']) ? wc_clean($_POST['wc-rm-pagbank-cc-payment-token']) : null;
        $is_saved_token = $token_id !== null && $token_id !== 'new';
        $is_trial_zero = $recurringTrialPeriod && $order->get_total() == 0;

        // Detect "save card" before prepare() so Vindi discard_credit_card is already false.
        $shouldSaveToken = false;
        if (isset($_POST['wc-rm-pagbank-cc-new-payment-method'])
            && wc_bool_to_string($_POST['wc-rm-pagbank-cc-new-payment-method']) === 'yes') {
            $shouldSaveToken = true;
        }
        if (isset($_POST['rm-pagbank-cc-new-payment-method-in-block'])
            && wc_bool_to_string($_POST['rm-pagbank-cc-new-payment-method-in-block']) === 'yes') {
            $shouldSaveToken = true;
        }
        if ($shouldSaveToken) {
            OrderMeta::set($order, '_save_card', 'yes', true);
        }

        if ($is_trial_zero) {
            $payment_method .= '_token';
        }

        switch ($payment_method) {
            case 'cc':
                //the first is used in non-block checkout
                $installments = filter_input(INPUT_POST, 'rm-pagbank-card-installments', FILTER_SANITIZE_NUMBER_INT)
                    ?: filter_var($_POST['rm-pagbank-card-installments'], FILTER_SANITIZE_NUMBER_INT); 
                $token_id = isset($_POST['wc-rm-pagbank-cc-payment-token']) ? wc_clean($_POST['wc-rm-pagbank-cc-payment-token']) : null;
                if($is_saved_token){
                    OrderMeta::set($order, '_card_token_id', $token_id, true);
                    $installments = filter_input(INPUT_POST, 'rm-pagbank-card-installments-token', FILTER_SANITIZE_NUMBER_INT)
                    ?: filter_var($_POST['rm-pagbank-card-installments-token'], FILTER_SANITIZE_NUMBER_INT);
                    // Vindi: copy payment_profile_id from Woo token onto the order for billing + email match.
                    try {
                        $tokenCc = \RM_PagBank\Connect\Payments\CreditCard::getCcToken($token_id);
                        if ($tokenCc && (new Api())->partner()->isVindi()) {
                            $profileId = (int) ($tokenCc->get_meta('vindi_payment_profile_id') ?: $tokenCc->get_token());
                            if ($profileId > 0) {
                                OrderMeta::set($order, '_payment_profile_id', $profileId, true);
                            }
                            $doc = (string) $tokenCc->get_meta('customer_document');
                            if ($doc !== '' && !$order->get_meta('_rm_pagbank_customer_document')) {
                                $order->add_meta_data('_rm_pagbank_customer_document', $doc, true);
                            }
                            $holder = (string) $tokenCc->get_meta('holder_name');
                            if ($holder !== '') {
                                OrderMeta::set($order, '_card_holder_name', $holder, true);
                            }
                        }
                    } catch (\Exception $e) {
                        // getCcToken validates ownership; fall through to prepare/charge error handling.
                    }
                }
                OrderMeta::set($order, 'card_installments', $installments, true);

                //the first is used in non-block checkout
                $ccNumber = filter_input(INPUT_POST, 'rm-pagbank-card-number', FILTER_SANITIZE_NUMBER_INT)
                    ?: filter_var($_POST['rm-pagbank-card-number'], FILTER_SANITIZE_NUMBER_INT);
                    
                OrderMeta::set($order, 'card_last4', substr($ccNumber, -4), true);
                OrderMeta::set($order, '_card_first_digits', substr($ccNumber, 0, 6), true);


                OrderMeta::set($order, '_card_encrypted', htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8'), true);
                OrderMeta::set($order, '_card_gateway_token', htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8'), true);
                $holderName = htmlspecialchars($_POST['rm-pagbank-card-holder-name'], ENT_QUOTES, 'UTF-8');
                $holderName = preg_replace('/\s+/', ' ', trim($holderName));
                $holderName = preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/', '', $holderName);
                OrderMeta::set($order, '_card_holder_name', $holderName, true);
                OrderMeta::set($order, '_card_3ds_id', isset($_POST['rm-pagbank-card-3d'])
                        ? htmlspecialchars($_POST['rm-pagbank-card-3d'], ENT_QUOTES, 'UTF-8')
                        : false);
                OrderMeta::set($order, '_card_retry_with_3ds', isset($_POST['rm-pagbank-card-retry-with-3ds'])
                        ? htmlspecialchars($_POST['rm-pagbank-card-retry-with-3ds'], ENT_QUOTES, 'UTF-8')
                        : false);

                if (!empty($_POST['rm-pagbank-payment-profile-id'])) {
                    OrderMeta::set($order, '_payment_profile_id', (int) wc_clean(wp_unslash($_POST['rm-pagbank-payment-profile-id'])), true);
                } else {
                    // BELOW_MINIMUM / retry clears the hidden field — do not reuse a discarded profile from a prior attempt.
                    OrderMeta::delete($order, '_payment_profile_id');
                }
                if (!empty($_POST['rm-pagbank-vindi-customer-id'])) {
                    OrderMeta::set($order, '_vindi_customer_id', (int) wc_clean(wp_unslash($_POST['rm-pagbank-vindi-customer-id'])), true);
                } else {
                    OrderMeta::delete($order, '_vindi_customer_id');
                }
                if (!empty($_POST['rm-pagbank-payment-company-code'])) {
                    OrderMeta::set($order, '_payment_company_code', sanitize_text_field(wp_unslash($_POST['rm-pagbank-payment-company-code'])), true);
                }
                if (!empty($_POST['rm-pagbank-card-3ds-payload'])) {
                    $threeDsPayload = wp_unslash($_POST['rm-pagbank-card-3ds-payload']);
                    $decodedThreeDs = json_decode($threeDsPayload, true);
                    if (is_array($decodedThreeDs)) {
                        OrderMeta::set($order, '_card_3ds_payload', wp_json_encode($decodedThreeDs), true);
                    }
                } else {
                    OrderMeta::delete($order, '_card_3ds_payload');
                }

                // Vindi: when 3DS is required and “Permitir concluir” is off, reject charges without a 3DS payload.
                $apiVindi3ds = new Api();
                if (
                    $apiVindi3ds->partner()->isVindi()
                    && wc_string_to_bool(Params::getCcConfig('cc_3ds', 'no'))
                    && !wc_string_to_bool(Params::getCcConfig('cc_3ds_allow_continue', 'no'))
                    && (float) $order->get_total() >= 1.0
                ) {
                    $capsVindi3ds = new \RM_PagBank\Partner\Capabilities($apiVindi3ds->partner());
                    $threeDsStored = OrderMeta::get($order, '_card_3ds_payload');
                    if ($capsVindi3ds->has3ds() && (!is_string($threeDsStored) || $threeDsStored === '')) {
                        wc_add_notice(
                            __(
                                '3D Secure é obrigatório para cartão nesta loja. Use um cartão com suporte a 3DS ou outro método de pagamento.',
                                'pagbank-connect'
                            ),
                            'error'
                        );
                        return [
                            'result' => 'fail',
                            'redirect' => '',
                        ];
                    }
                }

                $order->add_meta_data(
                    '_rm_pagbank_checkout_blocks',
                    wc_bool_to_string(isset($_POST['rm-pagbank-cc-new-payment-method-in-block']) || isset($_POST['wc-rm-pagbank-cc-new-payment-method'])),
                    true
                );

                if(isset($_POST['rm-pagbank-customer-document'])) {
                    $order->add_meta_data(
                        '_rm_pagbank_customer_document',
                        TaxId::sanitizeForApi(wp_unslash($_POST['rm-pagbank-customer-document'])),
                        true
                    );
                }

                $method = new \RM_PagBank\Connect\Payments\CreditCard($order);
                $params = $method->prepare();
                break;
            case 'cc_token':
                OrderMeta::set($order, '_card_encrypted', htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8'), true);
                OrderMeta::set($order, '_card_gateway_token', htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8'), true);
                if (!empty($_POST['rm-pagbank-card-holder-name'])) {
                    $holderName = htmlspecialchars($_POST['rm-pagbank-card-holder-name'], ENT_QUOTES, 'UTF-8');
                    $holderName = preg_replace('/\s+/', ' ', trim($holderName));
                    $holderName = preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/', '', $holderName);
                    OrderMeta::set($order, '_card_holder_name', $holderName, true);
                }
                if (!empty($_POST['rm-pagbank-payment-company-code'])) {
                    OrderMeta::set($order, '_payment_company_code', sanitize_text_field(wp_unslash($_POST['rm-pagbank-payment-company-code'])), true);
                }
                if (!empty($_POST['rm-pagbank-customer-document'])) {
                    $order->add_meta_data(
                        '_rm_pagbank_customer_document',
                        TaxId::sanitizeForApi(wp_unslash($_POST['rm-pagbank-customer-document'])),
                        true
                    );
                }
                if (!empty($_POST['rm-pagbank-payment-profile-id'])) {
                    OrderMeta::set($order, '_payment_profile_id', (int) wc_clean(wp_unslash($_POST['rm-pagbank-payment-profile-id'])), true);
                } else {
                    OrderMeta::delete($order, '_payment_profile_id');
                }
                if (!empty($_POST['rm-pagbank-vindi-customer-id'])) {
                    OrderMeta::set($order, '_vindi_customer_id', (int) wc_clean(wp_unslash($_POST['rm-pagbank-vindi-customer-id'])), true);
                } else {
                    OrderMeta::delete($order, '_vindi_customer_id');
                }
                $ccNumber = filter_input(INPUT_POST, 'rm-pagbank-card-number', FILTER_SANITIZE_NUMBER_INT)
                    ?: filter_var($_POST['rm-pagbank-card-number'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                if ($ccNumber) {
                    OrderMeta::set($order, 'card_last4', substr((string) $ccNumber, -4), true);
                    OrderMeta::set($order, '_card_first_digits', substr((string) $ccNumber, 0, 6), true);
                }

                $apiTrial = new Api();
                if ($apiTrial->partner()->isVindi()) {
                    // Trial R$0: persist Vindi payment_profile (never tokens/cards).
                    $method = new CreditCardToken($order);
                    if ($is_saved_token) {
                        $tokenCc = \RM_PagBank\Connect\Payments\CreditCard::getCcToken($token_id);
                        $profileId = (int) ($tokenCc->get_meta('vindi_payment_profile_id') ?: $tokenCc->get_token());
                        $holder_name = (string) $tokenCc->get_meta('holder_name');
                        $brand = (string) $tokenCc->get_card_type();
                        $resp = [
                            'id' => $profileId > 0 ? (string) $profileId : ($tokenCc->get_token() ?: ''),
                            'holder' => ['name' => $holder_name],
                            'first_digits' => $tokenCc->get_meta('cc_bin'),
                            'last_digits' => $tokenCc->get_last4(),
                            'exp_month' => $tokenCc->get_expiry_month(),
                            'exp_year' => $tokenCc->get_expiry_year(),
                            'brand' => $brand,
                        ];
                        if ($profileId > 0) {
                            OrderMeta::set($order, '_payment_profile_id', $profileId, true);
                        }
                        OrderMeta::set($order, 'payment_method', $method->code, true);
                        $order->set_payment_method(Connect::DOMAIN);
                        break;
                    }

                    $this->saveCcToken($order);
                    $profileId = (int) OrderMeta::get($order, '_payment_profile_id');
                    $expMonth = '';
                    $expYear = '';
                    if (!empty($_POST['rm-pagbank-card-expiry'])) {
                        $parts = explode('/', (string) wp_unslash($_POST['rm-pagbank-card-expiry']));
                        $expMonth = str_pad(preg_replace('/\D+/', '', $parts[0] ?? ''), 2, '0', STR_PAD_LEFT);
                        $yy = preg_replace('/\D+/', '', $parts[1] ?? '');
                        $expYear = '20' . substr($yy, -2);
                    }
                    $resp = [
                        'id' => (string) $profileId,
                        'holder' => [
                            'name' => (string) (OrderMeta::get($order, '_card_holder_name') ?: ''),
                        ],
                        'first_digits' => (string) (OrderMeta::get($order, '_card_first_digits') ?: ''),
                        'last_digits' => (string) (OrderMeta::get($order, 'card_last4') ?: ''),
                        'exp_month' => $expMonth,
                        'exp_year' => $expYear,
                        'brand' => (string) (OrderMeta::get($order, '_payment_company_code') ?: 'card'),
                    ];
                    OrderMeta::set($order, 'payment_method', $method->code, true);
                    $order->set_payment_method(Connect::DOMAIN);
                    // Profile already persisted — skip tokens/cards makeRequest below.
                    $is_saved_token = true;
                    break;
                }

                $method = new CreditCardToken($order);
                if ($is_saved_token) {
                    $tokenCc = \RM_PagBank\Connect\Payments\CreditCard::getCcToken($token_id);
                    $holder_name = (string)$tokenCc->get_meta('holder_name');
                    $brand = (string)$tokenCc->get_card_type();
                    $resp = [
                        'id'    => $tokenCc->get_token() ?: '',
                        'holder'   => [
                            'name' => $holder_name,
                        ],
                        'first_digits'   => $tokenCc->get_meta('cc_bin'),
                        'last_digits'   => $tokenCc->get_last4(),
                        'exp_month'   => $tokenCc->get_expiry_month(),
                        'exp_year'   => $tokenCc->get_expiry_year(),
                        'brand' => $brand,
                    ];
                   
                    OrderMeta::set($order, 'payment_method', $method->code, true);
                    $order->set_payment_method(Connect::DOMAIN);
                    break;
                }
                $params = $method->prepare();
                break;
            default:
                wc_add_wp_error_notices(
                    new WP_Error('invalid_payment_method', __('Método de pagamento inválido', 'pagbank-connect'))
                );
                return array(
                    'result' => 'fail',
                    'redirect' => '',
                );
        }
        // Persist card after order meta is set; rebuild Vindi charge so discard_credit_card stays false.
        if ($shouldSaveToken) {
            $this->saveCcToken($order);
            if ($payment_method === 'cc' && (new Api())->partner()->isVindi()) {
                $method = new \RM_PagBank\Connect\Payments\CreditCard($order);
                $params = $method->prepare();
            }
        }

        if (!$is_trial_zero || ($is_trial_zero && !$is_saved_token)) {
            $resp = $this->makeRequest($order, $params, $method);
        }

        $method->process_response($order, $resp);
        self::updateTransaction($order, $resp);

        $charge = $resp['charges'][0] ?? false;

        // region Immediately decline if payment method is credit card and charge was declined
        if ($payment_method == 'cc' && $charge !== false) {
            if ($charge['status'] == 'DECLINED'){
                $additional_error = '';
                if(isset($charge['payment_response']))
                    $additional_error .= $charge['payment_response']['message'] . ' ('
                        . $charge['payment_response']['code'] . '). ';

                $retryWith3ds = !(new Api())->partner()->isVindi()
                    && !wc_string_to_bool($this->get_option('cc_3ds'))
                    && wc_string_to_bool($this->get_option('cc_3ds_retry'))
                    && $this->codeCanRetryPayment((string) $charge['payment_response']['code']);
                if ($retryWith3ds) {
                    $additional_error .= '<br /> ' . '<strong>Vamos tentar com validação 3DS?</strong> Basta Finalizar a compra novamente.';
                }

                $message = 'Pagamento Recusado. ' . $additional_error;
                wc_add_wp_error_notices(new WP_Error('api_error', $message));
                return [
                    'result' => 'fail',
                    'redirect' => '',
                    'message' => $message
                ];
            }

            // region If payment method is credit card and charge was approved, check if it is a subscription
            if(isset($_POST['rm-pagbank-card-set-default']) && $_POST['rm-pagbank-card-set-default'] == '1') {
                $orderParent = $order->get_parent_id() ? wc_get_order($order->get_parent_id()) : $order;
                $recurring = new \RM_PagBank\Connect\Recurring();
                $subscription = $recurring->getSubscriptionFromOrder($orderParent);
                $recurring->changePaymentMethodSubscriptionAction($subscription);
            }
        }
        // endregion

        // some notes to customer (or keep them private if order is pending)
        $shouldNotify = $order->get_status('edit') !== 'pending';
        $order->add_order_note(\RM_PagBank\Partner\Branding::notePrefix($order) . ': Pedido criado com sucesso!', $shouldNotify);

        // sends the new order email
        if ($shouldNotify) {
            $newOrderEmail = WC()->mailer()->emails['WC_Email_New_Order'];
            $newOrderEmail->trigger($order->get_id());
        }

        $woocommerce->cart->empty_cart();
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Token ID PagBank|Woo — Vindi uses payment_profile id (no /tokens/cards).
     * @param WC_Order $order
     * @throws \RM_PagBank\Connect\Exception
     */
    public function saveCcToken($order)
    {
        $api = new Api();
        if ($api->partner()->isVindi()) {
            $this->saveVindiCcToken($order, $api);
            return $order;
        }

        $ccToken = new CreditCardToken($order);
        $params = $ccToken->prepare();

        $resp = $api->post('ws/tokens/cards', $params);
        if (isset($resp['error_messages'])) {
            throw new \RM_PagBank\Connect\Exception($resp['error_messages'], 40000);
        }
        
        $token = new WC_Payment_Token_CC();
        $token->set_token( $resp['id'] );
        $token->set_gateway_id( $this->id );
        $token->set_user_id( get_current_user_id() );
        $token->set_card_type( $resp['brand'] );
        $token->set_last4( $resp['last_digits']);
        $token->set_expiry_month( $resp['exp_month'] );
        $token->set_expiry_year( (int) $resp['exp_year'] );
        $token->update_meta_data( 'holder_name', $resp['holder']['name'] ?? '');
        $token->update_meta_data( 'cc_bin', $resp['first_digits'] );
        $token->update_meta_data( 'customer_document', $order->get_meta('_rm_pagbank_customer_document') );
        $token->save();
        // Assoc with order
        $order->add_payment_token( $token );
        return $order;
    }

    /**
     * Persist Vindi payment_profile and store as WooCommerce CC token.
     *
     * @throws \RM_PagBank\Connect\Exception
     */
    private function saveVindiCcToken(WC_Order $order, Api $api): void
    {
        $userId = get_current_user_id();

        $profileId = (int) OrderMeta::get($order, '_payment_profile_id');
        $profile = [];

        if ($profileId <= 0) {
            $gatewayToken = (string) (OrderMeta::get($order, '_card_gateway_token')
                ?: OrderMeta::get($order, '_card_encrypted'));
            if ($gatewayToken === '') {
                throw new \RM_PagBank\Connect\Exception([[
                    'code' => 'TOKEN_MISSING',
                    'description' => __('Token do cartão ausente para salvar.', 'pagbank-connect'),
                ]], 40000);
            }

            $company = (string) (OrderMeta::get($order, '_payment_company_code') ?: 'mastercard');
            if ($company === '' && !empty($_POST['rm-pagbank-payment-company-code'])) {
                $company = sanitize_text_field(wp_unslash($_POST['rm-pagbank-payment-company-code']));
            }

            $customerName = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            if ($customerName === '') {
                $customerName = (string) OrderMeta::get($order, '_card_holder_name') ?: 'Cliente';
            }
            $phone = preg_replace('/\D+/', '', (string) $order->get_billing_phone());
            if ($phone !== '' && strpos($phone, '55') !== 0) {
                $phone = '55' . $phone;
            }

            $payload = [
                'gateway_token' => $gatewayToken,
                'payment_company_code' => $company !== '' ? $company : 'mastercard',
                'payment_method_code' => 'credit_card',
                'customer' => [
                    'name' => $customerName,
                    'email' => strtolower(trim((string) $order->get_billing_email())),
                    'registry_code' => TaxId::sanitizeForApi((string) $order->get_meta('_rm_pagbank_customer_document')),
                    'mobile_phone' => $phone,
                ],
            ];

            $resp = $api->post('payment_profiles', $payload);
            if (isset($resp['error_messages']) || !empty($resp['error'])) {
                $msg = $resp['error_messages'] ?? [[
                    'code' => (string) ($resp['error'] ?? 'PROFILE_ERROR'),
                    'description' => (string) ($resp['message'] ?? 'Falha ao salvar cartão na Vindi.'),
                ]];
                throw new \RM_PagBank\Connect\Exception($msg, 40000);
            }

            $profile = is_array($resp['payment_profile'] ?? null) ? $resp['payment_profile'] : $resp;
            $profileId = (int) ($profile['id'] ?? $resp['payment_profile_id'] ?? 0);
            if ($profileId <= 0) {
                throw new \RM_PagBank\Connect\Exception([[
                    'code' => 'PROFILE_ID_MISSING',
                    'description' => __('Não foi possível obter o ID do perfil de pagamento.', 'pagbank-connect'),
                ]], 40000);
            }

            OrderMeta::set($order, '_payment_profile_id', $profileId, true);
            $customerId = (int) ($resp['customer_id'] ?? $profile['customer_id'] ?? 0);
            if ($customerId > 0) {
                OrderMeta::set($order, '_vindi_customer_id', $customerId, true);
            }
        }

        // Woo token only for logged-in customers (My Account saved cards).
        if ($userId <= 0) {
            return;
        }

        $last4 = (string) ($profile['card_number_last_four'] ?? '');
        if ($last4 === '') {
            $last4 = (string) (OrderMeta::get($order, 'card_last4') ?: '****');
        }
        $bin = (string) ($profile['card_number_first_six'] ?? '');
        if ($bin === '') {
            $bin = (string) OrderMeta::get($order, '_card_first_digits');
        }
        $brand = strtolower((string) (
            ($profile['payment_company']['code'] ?? null)
            ?: (OrderMeta::get($order, '_payment_company_code') ?: 'card')
        ));
        $holder = (string) ($profile['holder_name'] ?? '');
        if ($holder === '') {
            $holder = (string) OrderMeta::get($order, '_card_holder_name');
        }

        $expMonth = '12';
        $expYear = (int) gmdate('Y') + 3;
        $cardExpiration = (string) ($profile['card_expiration'] ?? '');
        if ($cardExpiration !== '' && preg_match('/^(\d{4})-(\d{2})/', $cardExpiration, $m)) {
            $expYear = (int) $m[1];
            $expMonth = $m[2];
        } elseif (!empty($_POST['rm-pagbank-card-expiry'])) {
            $parts = explode('/', (string) wp_unslash($_POST['rm-pagbank-card-expiry']));
            $expMonth = str_pad(preg_replace('/\D+/', '', $parts[0] ?? ''), 2, '0', STR_PAD_LEFT);
            $yy = preg_replace('/\D+/', '', $parts[1] ?? '');
            $expYear = (int) ('20' . substr($yy, -2));
        }

        $token = new WC_Payment_Token_CC();
        $token->set_token((string) $profileId);
        $token->set_gateway_id($this->id);
        $token->set_user_id($userId);
        $token->set_card_type($brand);
        $token->set_last4(substr($last4, -4));
        $token->set_expiry_month($expMonth);
        $token->set_expiry_year($expYear);
        $token->update_meta_data('holder_name', $holder);
        $token->update_meta_data('cc_bin', $bin);
        $token->update_meta_data('partner', 'vindi');
        $token->update_meta_data('vindi_payment_profile_id', $profileId);
        $token->update_meta_data('customer_document', $order->get_meta('_rm_pagbank_customer_document'));
        $token->save();
        $order->add_payment_token($token);
    }

    /**
     * Get the default installments for the credit card payment method using VISA as the default BIN
     * @return array
     */
    public function getDefaultInstallments(): array
    {
        $total = Api::getOrderTotal();

        return Params::getInstallments($total, '555566');
    }

    public function field_name( $name ) {
        return ' name="' . esc_attr( Connect::DOMAIN . '-' . $name ) . '" ';
    }

    /**
     * Add css files for checkout and success page
     * @return void
     */
    public static function addStyles($styles){
        //thank you page
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received'))) {
            $styles['pagseguro-connect-pix'] = [
                'src'     => plugins_url('public/css/success.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                'deps'    => [],
                'version' => WC_PAGSEGURO_CONNECT_VERSION,
                'media'   => 'all',
                'has_rtl' => false,
            ];
        }
        if ( is_checkout() ) {
            $styles['pagseguro-connect-checkout'] = [
                'src'     => plugins_url('public/css/checkout.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                'deps'    => [],
                'version' => WC_PAGSEGURO_CONNECT_VERSION,
                'media'   => 'all',
                'has_rtl' => false,
            ];
        }

        return $styles;
    }

    /**
     * Add js files for checkout and success page
     * @param bool $force
     * @return void
     */
    public function addScripts($force=false) {
        $force = (bool) $force;
        // If the method has already been called, return early
        if (self::$addedScripts) {
            return;
        }

        //thank you page
        $alreadyEnqueued = wp_script_is('pagseguro-connect');
        if (is_checkout() && !empty(is_wc_endpoint_url('order-received')) && !$alreadyEnqueued) {
            wp_enqueue_script(
                'pagseguro-connect',
                plugins_url('public/js/success.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
            );
        }

        $recHelper = new \RM_PagBank\Helpers\Recurring();
        $alreadyEnqueued = wp_script_is('pagseguro-checkout-sdk');
        if ($force || (is_checkout() && !is_order_received_page()) || $recHelper->isSubscriptionUpdatePage() || is_wc_endpoint_url('add-payment-method')) {
            if ( !$alreadyEnqueued ) {
                wp_enqueue_script(
                    'pagseguro-checkout-sdk',
                    'https://assets.pagseguro.com.br/checkout-sdk-js/rc/dist/browser/pagseguro.min.js',
                    [],
                    WC_PAGSEGURO_CONNECT_VERSION,
                    true
                );
            }
        }

        $isCheckoutBlocks = Functions::isCheckoutBlocks();
        if ($force || (is_checkout() && !is_order_received_page() && !$isCheckoutBlocks) || $recHelper->isSubscriptionUpdatePage() || is_wc_endpoint_url('add-payment-method')) {
            $alreadyEnqueued = wp_script_is('pagseguro-connect-checkout');
            if (!$alreadyEnqueued) {
                wp_enqueue_script(
                    'pagseguro-connect-checkout',
                    plugins_url('public/js/checkout.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                    ['jquery'],
                    WC_PAGSEGURO_CONNECT_VERSION,
                    true
                );
            }

            wp_add_inline_script(
                'pagseguro-connect-checkout',
                'const rm_pagbank_nonce = "' . wp_create_nonce('rm_pagbank_nonce') . '";',
                'before'
            );

            $api = new Api();
            if ( $this->get_option('enabled') == 'yes') {
                // Define handles baseados na versão do WooCommerce (novos handles desde 10.3.0)
                $blockui_handle = wp_script_is('wc-jquery-blockui', 'registered') ? 'wc-jquery-blockui' : 'jquery-blockui';
                $payment_handle = wp_script_is('wc-jquery-payment', 'registered') ? 'wc-jquery-payment' : 'jquery-payment';
                
                wp_enqueue_script(
                    'pagseguro-connect-tax-id',
                    plugins_url('public/js/tax-id-legacy.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                    [],
                    WC_PAGSEGURO_CONNECT_VERSION,
                    true
                );

                wp_enqueue_script(
                    'pagseguro-connect-creditcard',
                    plugins_url('public/js/creditcard.js', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                    ['jquery', $payment_handle, $blockui_handle, 'pagseguro-connect-tax-id'],
                    WC_PAGSEGURO_CONNECT_VERSION,
                    ['strategy' => 'defer', 'in_footer' => true]
                );
                wp_localize_script(
                    'pagseguro-connect-creditcard',
                    'ajax_object',
                    ['ajax_url' => admin_url('admin-ajax.php')]
                );
                wp_add_inline_script(
                    'pagseguro-connect-creditcard',
                    'var pagseguro_connect_public_key = \''.Params::getConfig('public_key').'\';',
                    'before'
                );

                $partner = $api->partner()->isVindi() ? 'vindi' : 'pagbank';
                wp_add_inline_script(
                    'pagseguro-connect-creditcard',
                    'var pagseguro_connect_partner = \''.$partner.'\';',
                    'before'
                );
                if ($api->partner()->isVindi()) {
                    try {
                        $tok = $api->get('tokenize/config', [], 30);
                        $pub = esc_js((string) ($tok['public_api_key'] ?? ''));
                        $url = esc_js((string) ($tok['payment_profiles_url'] ?? ''));
                        wp_add_inline_script(
                            'pagseguro-connect-creditcard',
                            "var pagseguro_vindi_public_key = '{$pub}'; var pagseguro_vindi_payment_profiles_url = '{$url}';",
                            'before'
                        );
                    } catch (Exception $e) {
                        Functions::log('tokenize/config failed: ' . $e->getMessage(), 'error');
                    }
                    $mpiOrigin = $api->getIsSandbox()
                        ? 'https://sandbox-mpi.vindi.com.br'
                        : 'https://mpi.vindi.com.br';
                    wp_add_inline_script(
                        'pagseguro-connect-creditcard',
                        'var pagseguro_vindi_mpi_origin = \'' . esc_js($mpiOrigin) . '\';',
                        'before'
                    );
                }

                $caps = new \RM_PagBank\Partner\Capabilities($api->partner());
                $can3ds = $caps->has3ds() && (wc_string_to_bool($this->get_option('cc_3ds')) || wc_string_to_bool($this->get_option('cc_3ds_retry')));

                if ($can3ds) {
                    wp_add_inline_script(
                        'pagseguro-connect-creditcard',
                        'var pagseguro_connect_cc_3ds_allow_continue = \''
                        . esc_js(Params::getCcConfig('cc_3ds_allow_continue', 'no'))
                        . '\';',
                        'before'
                    );
                }

                if ( $can3ds && !$api->partner()->isVindi()
                    && !$recHelper->isSubscriptionUpdatePage() && !is_wc_endpoint_url('add-payment-method')) {
                    $threeDSession = $api->get3DSession();
                    wp_add_inline_script(
                        'pagseguro-connect-creditcard',
                        'var pagseguro_connect_3d_session = \''.$threeDSession.'\';',
                        'before'
                    );
                    // add user notice
                    if ($threeDSession === '' && Params::getCcConfig('cc_3ds_allow_continue', 'no') === 'no') {
                        wc_add_notice(__('Erro ao obter a sessão 3D Secure. Contate o administrador da loja '
                            .'para verificar Connect Key. Por favor recarregue a página.', 'pagbank-connect'), 'error');
                    }
                }

                $retryWith3ds = ($can3ds && !$api->partner()->isVindi() && wc_string_to_bool($this->get_option('cc_3ds_retry'))) ? 'true' : 'false';
                wp_add_inline_script(
                    'pagseguro-connect-creditcard',
                    "var pagseguro_connect_3ds_retry_enabled = {$retryWith3ds};",
                    'before'
                );

                $enable3ds = ($can3ds && wc_string_to_bool($this->get_option('cc_3ds'))) ? 'true' : 'false';
                wp_add_inline_script(
                    'pagseguro-connect-creditcard',
                    "var pagseguro_connect_3ds_enabled = {$enable3ds};",
                    'before'
                );

                $cartCents = 0;
                if ($recHelper->isSubscriptionUpdatePage()) {
                    // Change-card has no cart — use subscription recurring amount for 3DS enroll.
                    $subId = $recHelper->getSubscriptionIdFromUpdatePage();
                    if ($subId > 0) {
                        $sub = (new \RM_PagBank\Connect\Recurring())->getSubscription($subId);
                        if ($sub && isset($sub->recurring_amount)) {
                            $cartCents = (int) round((float) $sub->recurring_amount * 100);
                        }
                    }
                } elseif (function_exists('WC') && WC()->cart) {
                    $cartCents = (int) round((float) WC()->cart->get_total('edit') * 100);
                }
                wp_add_inline_script(
                    'pagseguro-connect-creditcard',
                    'var pagseguro_connect_cart_total_cents = ' . $cartCents . ';',
                    'before'
                );

                $environment = $api->getIsSandbox() ? 'SANDBOX' : 'PROD';
                wp_add_inline_script(
                    'pagseguro-connect-checkout',
                    "const pagseguro_connect_environment = '$environment';",
                    'before'
                );
            }
            self::$addedScripts = true;
        }
        if (!in_array('change_card_page', self::$injectedScripts, true)) {
            $isUpdatePage = $recHelper->isSubscriptionUpdatePage() || is_wc_endpoint_url('add-payment-method') ? 'true' : 'false';
            wp_add_inline_script(
                'pagseguro-connect-checkout',
                "const pagseguro_connect_change_card_page = {$isUpdatePage};",
                'before'
            );
            self::$injectedScripts[] = 'change_card_page';
        }
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int        $order_id Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     * @return bool|WP_Error True or false based on success, or a WP_Error object.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        return Api::refund($order_id, $amount);
    }

    /**
     * @param string $code
     * @return bool
     */
    private function codeCanRetryPayment(string $code)
    {
        $allowedCodes = [
            '10000', // NAO AUTORIZADO PELO PAGSEGURO: NEGADO NO ANTIFRAUDE PAGBANK
            '10002', // NAO AUTORIZADO PELO EMISSOR DO CARTAO
            '20001', // CONTATE A CENTRAL DO SEU CARTAO: GENÉRICA, SUSPEITA DE FRAUDE ETC
            '20119', // REFAZER A TRANSAÇÃO (EMISSOR SOLICITA RETENTATIVA)
            '20158', // NAO AUTORIZADA - TENTE NOVAMENTE MAIS TARDE
            '20159', // NAO AUTORIZADA - TENTE NOVAMENTE USANDO AUTENTICACAO
        ];

        return in_array($code, $allowedCodes);
    }

    public function render_installments_field() {
        // Check if user has saved tokens before rendering the installments field
        if (!is_user_logged_in()) {
            return '';
        }

        $customer_tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->id);
        if (empty($customer_tokens)) {
            return '';
        }

        ob_start();
        include WC_PAGSEGURO_CONNECT_BASE_DIR . '/src/templates/payments/creditcard/installment_options.php';
        $html = ob_get_clean();
        return $html;
    }

    /**
	 * Gets saved payment method HTML from a token.
	 *
	 * @since 2.6.0
	 * @param  WC_Payment_Token $token Payment Token.
	 * @return string Generated payment method HTML
	 */
	public function get_saved_payment_method_option_html( $token ) {

        $bin = $token->get_meta( 'cc_bin' ) ?: '555566'; // WooCommerce >=3.0 usa get_meta()
		$html = sprintf(
            '<li class="woocommerce-SavedPaymentMethods-token">
                <input 
                    id="wc-%1$s-payment-token-%2$s" 
                    type="radio" 
                    name="wc-%1$s-payment-token" 
                    value="%2$s" 
                    data-cc-bin="%5$s"
                    style="width:auto;" 
                    class="woocommerce-SavedPaymentMethods-tokenInput" 
                    %4$s 
                />
                <label for="wc-%1$s-payment-token-%2$s">%3$s</label>
            </li>',
            esc_attr( $this->id ),
            esc_attr( $token->get_id() ),
            esc_html( $token->get_display_name() ),
            checked( $token->is_default(), true, false ),
            esc_attr( $bin ) // data-bin
        );
		return apply_filters( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this );
    }

    /**
     * Filter to remove add_payment_method support if save card option is disabled
     * This filter is called by WooCommerce's supports() method and is more reliable
     * than modifying the supports array directly
     * 
     * @param bool $supports Whether the gateway supports the feature
     * @param string $feature The feature name
     * @param WC_Payment_Gateway $gateway The gateway instance
     * @return bool
     */
    public function maybe_remove_add_payment_method_support_filter($supports, $feature, $gateway)
    {
        // Only apply to this gateway and the add_payment_method feature
        if ($gateway->id !== $this->id || $feature !== 'add_payment_method') {
            return $supports;
        }
        
        // Only apply on account pages (my-account area), not on checkout
        if (!is_account_page() || is_checkout()) {
            return $supports;
        }
        
        $recurringHelper = new \RM_PagBank\Helpers\Recurring();
        $isCartRecurring = $recurringHelper->isCartRecurring();
        $allowSaveCard = wc_string_to_bool($this->get_option('cc_allow_save_card', 'no'));
        
        // Remove support if save card is disabled and it's not a recurring subscription
        if (!$isCartRecurring && !$allowSaveCard) {
            return false;
        }
        
        return $supports;
    }

    /**
     * Filter out gateways that don't support add_payment_method on the add payment method page
     * This ensures the button only appears if there are gateways that actually support adding payment methods
     * 
     * @param array $gateways
     * @return array
     */
    public function filter_gateways_without_add_payment_method_support($gateways)
    {
        // Only apply on the add payment method page
        if (!is_wc_endpoint_url('add-payment-method')) {
            return $gateways;
        }
        
        // Filter out gateways that don't support add_payment_method
        foreach ($gateways as $gateway_id => $gateway) {
            if (!$gateway->supports('add_payment_method')) {
                unset($gateways[$gateway_id]);
            }
        }
        
        return $gateways;
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip()
    {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Add payment method for tokenization
     *
     * @return array
     */
    public function add_payment_method()
    {
        // Verificar se usuário está logado
        if (!is_user_logged_in()) {
            wc_add_notice(__('Você precisa estar logado para adicionar um método de pagamento.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_account_endpoint_url('payment-methods')
            ];
        }

        // Verificar se a opção de salvar cartão está habilitada
        $recurringHelper = new \RM_PagBank\Helpers\Recurring();
        $isCartRecurring = $recurringHelper->isCartRecurring();
        $allowSaveCard = wc_string_to_bool($this->get_option('cc_allow_save_card', 'no'));
        
        if (!$isCartRecurring && !$allowSaveCard) {
            wc_add_notice(__('Salvar cartões está desabilitado para este método de pagamento.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_endpoint_url('payment-methods')
            ];
        }

        // Verificar nonce (WooCommerce usa 'woocommerce-add-payment-method' com campo 'woocommerce-add-payment-method-nonce')
        $nonce_value = wc_get_var($_REQUEST['woocommerce-add-payment-method-nonce'], wc_get_var($_REQUEST['_wpnonce'], ''));
        if (!wp_verify_nonce($nonce_value, 'woocommerce-add-payment-method')) {
            wc_add_notice(__('Falha na verificação de segurança. Recarregue a página e tente novamente.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_endpoint_url('add-payment-method')
            ];
        }

        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        // Rate limiting: máximo 5 tentativas por hora por usuário
        $transient_key_user = 'pagbank_add_card_attempts_' . $user_id;
        $attempts_user = get_transient($transient_key_user) ?: 0;
        
        if ($attempts_user >= 5) {
            wc_add_notice(__('Muitas tentativas. Tente novamente em algumas horas.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_endpoint_url('add-payment-method')
            ];
        }
        
        // Rate limiting por IP: máximo 10 tentativas por hora por IP
        $transient_key_ip = 'pagbank_add_card_ip_' . md5($ip_address);
        $attempts_ip = get_transient($transient_key_ip) ?: 0;
        
        if ($attempts_ip >= 10) {
            wc_add_notice(__('Muitas tentativas deste endereço. Tente novamente mais tarde.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_endpoint_url('add-payment-method')
            ];
        }

        // Verificar limite de cartões salvos por usuário
        $existing_tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->id);
        $max_tokens = apply_filters('pagbank_max_saved_cards', 5); // Permitir filtro para customização

        if (count($existing_tokens) >= $max_tokens) {
            wc_add_notice(__('Você já possui o número máximo de cartões salvos. Remova um cartão antes de adicionar outro.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_endpoint_url('payment-methods')
            ];
        }

        try {
            // Incrementar contador de tentativas ANTES da chamada à API
            set_transient($transient_key_user, $attempts_user + 1, HOUR_IN_SECONDS);
            set_transient($transient_key_ip, $attempts_ip + 1, HOUR_IN_SECONDS);

            // Validate required fields
            if (empty($_POST['rm-pagbank-card-encrypted'])) {
                wc_add_notice(__('Card data is required.', 'pagbank-connect'), 'error');
                return [
                    'result' => 'failure',
                    'redirect' => wc_get_endpoint_url('add-payment-method')
                ];
            }

            if (empty($_POST['rm-pagbank-card-holder-name'])) {
                wc_add_notice(__('O nome do titular do cartão é obrigatório.', 'pagbank-connect'), 'error');
                return [
                    'result' => 'failure',
                    'redirect' => wc_get_endpoint_url('add-payment-method')
                ];
            }

            // Clean and validate holder name
            $holderName = htmlspecialchars($_POST['rm-pagbank-card-holder-name'], ENT_QUOTES, 'UTF-8');
            $holderName = preg_replace('/\s+/', ' ', trim($holderName));
            $holderName = preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ\s]/', '', $holderName);

            if (empty($holderName)) {
                wc_add_notice(__('Nome do titular do cartão inválido.', 'pagbank-connect'), 'error');
                return [
                    'result' => 'failure',
                    'redirect' => wc_get_endpoint_url('add-payment-method')
                ];
            }

            // Validate CPF/CNPJ field
            if (empty($_POST['rm-pagbank-card-cpf-cnpj'])) {
                wc_add_notice(__('CPF/CNPJ é obrigatório.', 'pagbank-connect'), 'error');
                return [
                    'result' => 'failure',
                    'redirect' => wc_get_endpoint_url('add-payment-method')
                ];
            }

            // Clean and validate CPF/CNPJ
            $cpfCnpj = TaxId::sanitizeForApi(wp_unslash($_POST['rm-pagbank-card-cpf-cnpj']));

            if (!TaxId::isValidFormat($cpfCnpj)) {
                wc_add_notice(__('CPF/CNPJ inválido.', 'pagbank-connect'), 'error');
                return [
                    'result' => 'failure',
                    'redirect' => wc_get_endpoint_url('add-payment-method')
                ];
            }

            // Get encrypted card data / Vindi gateway_token
            $encryptedCard = htmlspecialchars($_POST['rm-pagbank-card-encrypted'], ENT_QUOTES, 'UTF-8');

            $api = new Api();
            if ($api->partner()->isVindi()) {
                $company = !empty($_POST['rm-pagbank-payment-company-code'])
                    ? sanitize_text_field(wp_unslash($_POST['rm-pagbank-payment-company-code']))
                    : 'mastercard';
                $customer = new \WC_Customer(get_current_user_id());
                $name = trim($customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name());
                if ($name === '') {
                    $name = $holderName;
                }
                $phone = preg_replace('/\D+/', '', (string) $customer->get_billing_phone());
                if ($phone !== '' && strpos($phone, '55') !== 0) {
                    $phone = '55' . $phone;
                }
                $resp = $api->post('payment_profiles', [
                    'gateway_token' => $encryptedCard,
                    'payment_company_code' => $company,
                    'payment_method_code' => 'credit_card',
                    'customer' => [
                        'name' => $name,
                        'email' => strtolower(trim((string) $customer->get_email())),
                        'registry_code' => $cpfCnpj,
                        'mobile_phone' => $phone,
                    ],
                ]);
                if (isset($resp['error_messages']) || !empty($resp['error'])) {
                    throw new \RM_PagBank\Connect\Exception(
                        $resp['error_messages'] ?? [[
                            'code' => (string) ($resp['error'] ?? 'PROFILE_ERROR'),
                            'description' => (string) ($resp['message'] ?? 'Falha ao salvar cartão.'),
                        ]],
                        40000
                    );
                }
                $profile = is_array($resp['payment_profile'] ?? null) ? $resp['payment_profile'] : $resp;
                $profileId = (int) ($profile['id'] ?? $resp['payment_profile_id'] ?? 0);
                if ($profileId <= 0) {
                    return [
                        'result' => 'failure',
                        'redirect' => wc_get_endpoint_url('add-payment-method')
                    ];
                }
                delete_transient($transient_key_user);

                $last4 = (string) ($profile['card_number_last_four'] ?? '****');
                $bin = (string) ($profile['card_number_first_six'] ?? '');
                $brand = strtolower((string) ($profile['payment_company']['code'] ?? $company));
                $expMonth = '12';
                $expYear = (int) gmdate('Y') + 3;
                $cardExpiration = (string) ($profile['card_expiration'] ?? '');
                if ($cardExpiration !== '' && preg_match('/^(\d{4})-(\d{2})/', $cardExpiration, $m)) {
                    $expYear = (int) $m[1];
                    $expMonth = $m[2];
                }

                $token = new WC_Payment_Token_CC();
                $token->set_gateway_id($this->id);
                $token->set_user_id(get_current_user_id());
                $token->set_token((string) $profileId);
                $token->set_card_type($brand);
                $token->set_last4(substr($last4, -4));
                $token->set_expiry_month($expMonth);
                $token->set_expiry_year($expYear);
                $token->update_meta_data('holder_name', (string) ($profile['holder_name'] ?? $holderName));
                $token->update_meta_data('cc_bin', $bin);
                $token->update_meta_data('partner', 'vindi');
                $token->update_meta_data('vindi_payment_profile_id', $profileId);
                $token->update_meta_data('customer_document', $cpfCnpj, true);
                if (empty($existing_tokens)) {
                    $token->set_default(true);
                }
                if ($token->save()) {
                    return [
                        'result' => 'success',
                        'redirect' => wc_get_endpoint_url('payment-methods')
                    ];
                }
                wc_add_notice(__('Falha ao salvar o método de pagamento.', 'pagbank-connect'), 'error');
                return [
                    'result' => 'failure',
                    'redirect' => wc_get_endpoint_url('add-payment-method')
                ];
            }

            // PagBank: create token via tokens/cards
            $params = [
                'encrypted' => $encryptedCard
            ];

            $resp = $api->post('ws/tokens/cards', $params);

            if (isset($resp['error_messages'])) {
                throw new \RM_PagBank\Connect\Exception($resp['error_messages'], 40000);
            }

            if (empty($resp['id'])) {
                return [
                    'result' => 'failure',
                    'redirect' => wc_get_endpoint_url('add-payment-method')
                ];
            }

            // Se sucesso, resetar contador do usuário
            delete_transient($transient_key_user);

            // Create WooCommerce payment token
            $token = new WC_Payment_Token_CC();
            $token->set_gateway_id($this->id);
            $token->set_user_id(get_current_user_id());
            $token->set_token($resp['id']);
            $token->set_card_type(strtolower($resp['brand'] ?? 'card'));
            $token->set_last4($resp['last_digits'] ?? '****');
            $token->set_expiry_month($resp['exp_month'] ?? '');
            $token->set_expiry_year($resp['exp_year'] ?? '');
            $token->update_meta_data( 'holder_name', $resp['holder']['name'] ?? null );
            $token->update_meta_data( 'cc_bin', $resp['first_digits'] );
            $token->update_meta_data(
                'customer_document',
                $cpfCnpj,
                true
            );
            // Set as default if it's the first token for this user
            if (empty($existing_tokens)) {
                $token->set_default(true);
            }

            // Save the token
            if ($token->save()) {
                return [
                    'result' => 'success',
                    'redirect' => wc_get_endpoint_url('payment-methods')
                ];
            }

            wc_add_notice(__('Falha ao salvar o método de pagamento.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_endpoint_url('add-payment-method')
            ];

        } catch (Exception $e) {
            wc_add_notice(__('Ocorreu um erro ao adicionar o método de pagamento.', 'pagbank-connect'), 'error');
            return [
                'result' => 'failure',
                'redirect' => wc_get_endpoint_url('add-payment-method')
            ];
        }
    }

}