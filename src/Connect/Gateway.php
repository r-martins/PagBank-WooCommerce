<?php

namespace RM_PagBank\Connect;

use Exception;
use RM_PagBank\Connect;
use RM_PagBank\Helpers\Api;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Traits\PaymentUnavailable;
use RM_PagBank\Traits\ProcessPayment;
use RM_PagBank\Traits\StaticResources;
use RM_PagBank\Traits\ThankyouInstructions;
use WC_Admin_Settings;
use WC_Payment_Gateway_CC;

/**
 * Class Gateway
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 */
class Gateway extends WC_Payment_Gateway_CC
{
    use PaymentUnavailable;
    use ProcessPayment;
    use StaticResources;
    use ThankyouInstructions;

    public function __construct()
    {
        $this->id = Connect::DOMAIN;
		$this->icon = apply_filters(
			'wc_pagseguro_connect_icon',
			plugins_url('public/images/pagbank.svg', WC_PAGSEGURO_CONNECT_PLUGIN_FILE)
		);
        $this->method_title = __('PagBank Connect por Ricardo Martins', 'pagbank-connect');
		$this->method_description = __(
			'Aceite PIX, Cartão e Boleto de forma transparente com PagBank (PagSeguro).',
			'pagbank-connect'
		);
		$this->title = $this->get_option('title', __('PagBank (PagSeguro UOL)', 'pagbank-connect'));
		$this->description = $this->get_option('description');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        add_action('wp_enqueue_styles', [$this, 'addStyles']);
        add_action('wp_enqueue_scripts', [$this, 'addScripts']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'addAdminScripts'], 10, 1);

        add_filter('woocommerce_available_payment_gateways', [$this, 'disableIfOrderLessThanOneReal'], 10, 1);
        add_action('woocommerce_thankyou_' . Connect::DOMAIN, [$this, 'addThankyouInstructions']);
    }

    /**
     * Process admin options - override to handle custom field types
     */
    public function process_admin_options()
    {
        // Process split_payments_receivers field manually before parent processes
        $field_key = $this->get_field_key('split_payments_receivers');
        
        // Check if split payments is enabled
        $split_enabled_key = $this->get_field_key('split_payments_enabled');
        $split_enabled = isset($_POST[$split_enabled_key]) ? 'yes' : 'no';
        
        // Temporarily remove the custom field from form_fields to prevent parent from processing it
        $custom_field = null;
        if (isset($this->form_fields['split_payments_receivers'])) {
            $custom_field = $this->form_fields['split_payments_receivers'];
            unset($this->form_fields['split_payments_receivers']);
        }
        
        // Debug logging - check for array notation fields
        $post_keys_with_field = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, $field_key) === 0) {
                $post_keys_with_field[] = $key . ' => ' . (is_array($value) ? 'array(' . count($value) . ')' : gettype($value));
            }
        }
        
        \RM_PagBank\Helpers\Functions::log(
            sprintf(
                'Gateway::process_admin_options - Split enabled: %s, Field key: %s, Field in POST: %s, Field value type: %s, POST keys matching: %s',
                $split_enabled,
                $field_key,
                isset($_POST[$field_key]) ? 'yes' : 'no',
                isset($_POST[$field_key]) ? gettype($_POST[$field_key]) : 'N/A',
                !empty($post_keys_with_field) ? implode(', ', $post_keys_with_field) : 'none'
            ),
            'info'
        );
        
        // Mutual exclusivity check is now handled by validate_split_payments_enabled_field()
        
        // Fetch Account ID from API when split is being enabled
        $primary_account_id_to_save = null;
        if ($split_enabled === 'yes') {
            $primary_account_id_key = $this->get_field_key('split_payments_primary_account_id');
            $primary_account_id = isset($_POST[$primary_account_id_key]) ? sanitize_text_field($_POST[$primary_account_id_key]) : '';
            $current_split_enabled = $this->get_option('split_payments_enabled', 'no');
            $saved_account_id = $this->get_option('split_payments_primary_account_id', '');
            
            // Check if split is being enabled (was 'no' and now is 'yes')
            $is_being_enabled = ($current_split_enabled !== 'yes' && $split_enabled === 'yes');
            
            // If Account ID is not provided manually and (split is being enabled OR Account ID is not saved), fetch from API
            if (empty($primary_account_id) && ($is_being_enabled || empty($saved_account_id))) {
                try {
                    $api = new \RM_PagBank\Helpers\Api();
                    $account_info = $api->get('accountId', [], 0); // No cache, we want fresh data
                    
                    if (!empty($account_info['accountId'])) {
                        $primary_account_id = $account_info['accountId'];
                        // Store to save after parent::process_admin_options()
                        $primary_account_id_to_save = $primary_account_id;
                        // Add to $_POST so WooCommerce processes it correctly
                        $_POST[$primary_account_id_key] = $primary_account_id;
                        \RM_PagBank\Helpers\Functions::log(
                            'Gateway::process_admin_options - Account ID Principal obtido da API: ' . $primary_account_id,
                            'info'
                        );
                    } else {
                        WC_Admin_Settings::add_error(
                            __('Não foi possível obter o Account ID Principal da API. Configure manualmente o Account ID Principal ou verifique se a Connect Key está correta.', 'pagbank-connect')
                        );
                        // Don't enable split if we can't get Account ID
                        $split_enabled = 'no';
                        $this->update_option('split_payments_enabled', 'no');
                    }
                } catch (\Exception $e) {
                    \RM_PagBank\Helpers\Functions::log(
                        'Gateway::process_admin_options - Erro ao buscar Account ID da API: ' . $e->getMessage(),
                        'error'
                    );
                    WC_Admin_Settings::add_error(
                        sprintf(
                            __('Erro ao obter Account ID Principal da API: %s. Configure manualmente o Account ID Principal ou verifique se a Connect Key está correta.', 'pagbank-connect'),
                            esc_html($e->getMessage())
                        )
                    );
                    // Don't enable split if we can't get Account ID
                    $split_enabled = 'no';
                    $this->update_option('split_payments_enabled', 'no');
                }
            }
            
            // Use saved Account ID if not provided manually and not fetched from API
            if (empty($primary_account_id) && !empty($saved_account_id)) {
                $primary_account_id = $saved_account_id;
            }
            
            // Validate Account ID format if provided manually
            if (!empty($primary_account_id)) {
                $pattern = '/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/';
                if (!preg_match($pattern, $primary_account_id)) {
                    WC_Admin_Settings::add_error(
                        __('Account ID Principal com formato inválido. Use o formato: ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'pagbank-connect')
                    );
                }
            }
            
            // Final check: if split is enabled but Account ID is still empty, show error
            if ($split_enabled === 'yes' && empty($primary_account_id)) {
                WC_Admin_Settings::add_error(
                    __('Account ID Principal é obrigatório quando a Divisão de Pagamentos está ativada. Configure o Account ID Principal ou verifique se a Connect Key está correta.', 'pagbank-connect')
                );
                // Don't enable split if Account ID is missing
                $split_enabled = 'no';
                $this->update_option('split_payments_enabled', 'no');
            }
        }
        
        // Only process receivers if split is enabled
        if ($split_enabled === 'yes') {
            $receivers_data = null;
            
            // Check if field is in POST as array (PHP auto-converts field[0][key] to array)
            if (isset($_POST[$field_key]) && is_array($_POST[$field_key])) {
                $receivers_data = $_POST[$field_key];
            } else {
                // Try to build array manually from individual fields (field[0][account_id], field[0][percentage], etc.)
                $receivers_data = [];
                $index = 0;
                while (isset($_POST[$field_key . '[' . $index . '][account_id]']) || isset($_POST[$field_key . '[' . $index . '][percentage]'])) {
                    $account_id = isset($_POST[$field_key . '[' . $index . '][account_id]']) ? $_POST[$field_key . '[' . $index . '][account_id]'] : '';
                    $percentage = isset($_POST[$field_key . '[' . $index . '][percentage]']) ? $_POST[$field_key . '[' . $index . '][percentage]'] : '';
                    
                    if (!empty($account_id) || !empty($percentage)) {
                        $receivers_data[$index] = [
                            'account_id' => $account_id,
                            'percentage' => $percentage
                        ];
                    }
                    $index++;
                }
                
                // Clean up individual fields from POST
                foreach ($_POST as $key => $value) {
                    if (strpos($key, $field_key . '[') === 0) {
                        unset($_POST[$key]);
                    }
                }
            }
            
            if (!empty($receivers_data) && is_array($receivers_data)) {
                $value = $this->validate_split_payments_repeater_field('split_payments_receivers', $receivers_data);
                \RM_PagBank\Helpers\Functions::log(
                    sprintf(
                        'Gateway::process_admin_options - Saving %d receivers: %s',
                        count($value),
                        json_encode($value)
                    ),
                    'info'
                );
                $this->update_option('split_payments_receivers', $value);
            } else {
                // Field not in POST - this happens when table is empty
                // Set to empty array to clear any existing data
                \RM_PagBank\Helpers\Functions::log(
                    'Gateway::process_admin_options - Split enabled but no receivers data found, clearing receivers',
                    'info'
                );
                $this->update_option('split_payments_receivers', []);
            }
        } else {
            // If split is disabled, clear the receivers
            $this->update_option('split_payments_receivers', []);
        }
        
        // Remove from POST to prevent any other processing
        unset($_POST[$field_key]);

        // Call parent to process other fields (this will call validate_split_payments_enabled_field)
        parent::process_admin_options();
        
        // After parent processes, read the validated value
        $validated_split_enabled = $this->get_option('split_payments_enabled', 'no');
        
        // If validation rejected the split (returned 'no'), clear receivers
        if ($validated_split_enabled !== 'yes' && $split_enabled === 'yes') {
            $this->update_option('split_payments_receivers', []);
            \RM_PagBank\Helpers\Functions::log(
                'Gateway::process_admin_options - Split de pagamentos foi rejeitado pela validação, limpando receivers',
                'info'
            );
        }
        
        // Save Account ID if it was fetched from API and split is still enabled
        if ($primary_account_id_to_save !== null && $validated_split_enabled === 'yes') {
            $this->update_option('split_payments_primary_account_id', $primary_account_id_to_save);
            \RM_PagBank\Helpers\Functions::log(
                'Gateway::process_admin_options - Account ID Principal salvo após process_admin_options: ' . $primary_account_id_to_save,
                'info'
            );
        }
        
        // Restore the custom field to form_fields
        if ($custom_field !== null) {
            $this->form_fields['split_payments_receivers'] = $custom_field;
        }
    }

    public function init_form_fields()
    {
        $fields = [];
        $fields[] = include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/settings/general-fields.php';
        $this->form_fields = array_merge(...$fields);
    }

    public function admin_options() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        include WC_PAGSEGURO_CONNECT_BASE_DIR.'/admin/views/html-settings-page.php';
//        parent::admin_options();
    }

    /**
     * Generate HTML for split payments repeater field
     *
     * @param string $key Field key
     * @param array  $data Field data
     * @return string
     */
    public function generate_split_payments_repeater_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title'             => '',
            'label'             => '',
            'description'       => '',
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'custom_attributes' => [],
            'default'           => [],
        ];

        $data = wp_parse_args($data, $defaults);
        $value = $this->get_option($key, $data['default']);
        
        // Ensure value is an array
        if (!is_array($value)) {
            $value = [];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp forminp-<?php echo esc_attr($data['type']); ?>">
                <?php if ($data['description']): ?>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                <?php endif; ?>
                
                <style>
                    .pagbank-split-payments-table {
                        margin-top: 10px;
                    }
                    .pagbank-split-payments-table th {
                        padding: 10px;
                        background: #f9f9f9;
                        font-weight: 600;
                    }
                    .pagbank-split-payments-table td {
                        padding: 8px 10px;
                        vertical-align: middle;
                    }
                    .pagbank-split-payments-table .account-id-column {
                        width: 50%;
                    }
                    .pagbank-split-payments-table .percentage-column {
                        width: 25%;
                    }
                    .pagbank-split-payments-table .actions-column {
                        width: 25%;
                        text-align: right;
                    }
                    .pagbank-split-payment-row .pagbank-account-id {
                        width: 100%;
                    }
                    .pagbank-split-payment-row .pagbank-percentage {
                        width: 100px;
                    }
                    .pagbank-split-payment-row small a {
                        color: #2271b1;
                        text-decoration: underline;
                    }
                    .pagbank-split-payment-row small a:hover {
                        color: #135e96;
                    }
                </style>
                
                <div class="pagbank-split-payments-repeater" id="<?php echo esc_attr($field_key); ?>_container">
                    <table class="widefat pagbank-split-payments-table" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="account-id-column"><?php esc_html_e('Account ID PagBank', 'pagbank-connect'); ?></th>
                                <th class="percentage-column"><?php esc_html_e('Percentual (%)', 'pagbank-connect'); ?></th>
                                <th class="actions-column"><?php esc_html_e('Ações', 'pagbank-connect'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="pagbank-split-payments-tbody">
                            <?php if (!empty($value)): ?>
                                <?php foreach ($value as $index => $receiver): ?>
                                    <tr class="pagbank-split-payment-row">
                                        <td class="account-id-column">
                                            <input 
                                                type="text" 
                                                name="<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][account_id]" 
                                                value="<?php echo esc_attr($receiver['account_id'] ?? ''); ?>" 
                                                placeholder="ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                                pattern="ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}"
                                                class="regular-text pagbank-account-id"
                                                maxlength="41"
                                            />
                                            <br>
                                            <small>
                                                <a href="https://ws.pbintegracoes.com/pspro/v7/connect/account-id/authorize" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">
                                                    <?php esc_html_e('Qual é meu Account Id?', 'pagbank-connect'); ?>
                                                </a>
                                            </small>
                                        </td>
                                        <td class="percentage-column">
                                            <input 
                                                type="number" 
                                                name="<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($index); ?>][percentage]" 
                                                value="<?php echo esc_attr($receiver['percentage'] ?? ''); ?>" 
                                                placeholder="0.00"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                class="small-text pagbank-percentage"
                                            />
                                        </td>
                                        <td class="actions-column">
                                            <button type="button" class="button pagbank-remove-row"><?php esc_html_e('Remover', 'pagbank-connect'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">
                                    <button type="button" class="button button-secondary pagbank-add-row">
                                        <?php esc_html_e('+ Adicionar Conta', 'pagbank-connect'); ?>
                                    </button>
                                    <span class="pagbank-total-percentage" style="margin-left: 15px; font-weight: bold;">
                                        <?php esc_html_e('Total:', 'pagbank-connect'); ?> <span class="total-value">0</span>%
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Validates the eligibility of the key used in the recurring feature
     * Note: attempting to modify this behavior will not make the plugin work in your favor
     *
     * @param $key
     * @param $recurring_enabled
     *
     * @return string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate_recurring_enabled_field($key, $recurring_enabled): string
    {
        $connect_key = $this->get_option('connect_key');
        if (substr($connect_key, 0, 9) == 'CONPSFLEX' && $recurring_enabled) {
            WC_Admin_Settings::add_message(__('A recorrência foi desativada pois'
                .' a Connect Key informada usa taxas personalizadas.', 'pagbank-connect'));
            return 'no';
        }
        
        return $recurring_enabled ? 'yes' : 'no';
    }
    
    /**
     * Validates split payments enabled field - checks mutual exclusivity with Dokan Split
     *
     * @param string $key
     * @param mixed $value
     * @return string
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate_split_payments_enabled_field($key, $value): string
    {
        $split_enabled = $value ? 'yes' : 'no';
        
        // Check mutual exclusivity with Dokan Split
        $integrations_settings = get_option('woocommerce_rm-pagbank-integrations_settings', []);
        $dokan_split_enabled = $integrations_settings['dokan_split_enabled'] ?? 'no';
        
        if ($split_enabled === 'yes' && $dokan_split_enabled === 'yes') {
            WC_Admin_Settings::add_error(
                __('Não é possível ativar Divisão de Pagamentos enquanto o Split Dokan estiver ativo. Desative o Split Dokan primeiro.', 'pagbank-connect')
            );
            return 'no';
        }
        
        return $split_enabled;
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
        //check if it looks like a token (contains lower case and dashes)
        if (preg_match('/[a-z-]/', $connect_key)) {
            WC_Admin_Settings::add_error(__('Parece que você informou o Token PagBank no lugar da Connect Key. Clique em Obter Connect Key para obter a sua gratuitamente e ainda economizar nas taxas oficiais.', 'pagbank-connect'));
            return '';
        }

        $api = new Api();
        $api->setConnectKey($connect_key);
        
        try {
            $ret = $api->post('ws/public-keys', ['type' => 'card']);
            if (isset($ret['public_key'])) {
                $this->update_option('public_key', $ret['public_key']);
                $this->update_option('public_key_created_at', $ret['created_at']);
				$isSandbox = strpos($connect_key, 'CONSANDBOX') !== false;
				$this->update_option('is_sandbox', $isSandbox);
            }

            if (isset($ret['error_messages'])){
                //implode error_messages showing code and description
                $error_messages = array_map(function($error){
                    return $error['code'] . ' - ' . $error['description'];
                }, $ret['error_messages']);
                WC_Admin_Settings::add_error(implode('<br/>', $error_messages));
                $connect_key = '';
            }
        } catch (Exception $e) {
            WC_Admin_Settings::add_error('Validação da Connect Key Falhou. ' . $e->getMessage());
            $connect_key = '';
        }

        return $connect_key;

    }
    
    /**
     * Validate split payments repeater field
     *
     * @param string $key Field key
     * @param mixed  $value Field value (array of receivers)
     * @return array
     */
    public function validate_split_payments_repeater_field($key, $value)
    {
        \RM_PagBank\Helpers\Functions::log(
            sprintf(
                'Gateway::validate_split_payments_repeater_field - Input value type: %s, value: %s',
                gettype($value),
                is_array($value) ? json_encode($value) : var_export($value, true)
            ),
            'info'
        );
        
        // Ensure value is an array
        if (!is_array($value)) {
            \RM_PagBank\Helpers\Functions::log(
                'Gateway::validate_split_payments_repeater_field - Value is not array, returning empty',
                'info'
            );
            return [];
        }

        $sanitized = [];
        
        foreach ($value as $index => $receiver) {
            if (!is_array($receiver)) {
                \RM_PagBank\Helpers\Functions::log(
                    sprintf('Gateway::validate_split_payments_repeater_field - Receiver %d is not array, skipping', $index),
                    'info'
                );
                continue;
            }

            $account_id = isset($receiver['account_id']) ? sanitize_text_field($receiver['account_id']) : '';
            $percentage = isset($receiver['percentage']) ? floatval($receiver['percentage']) : 0;

            \RM_PagBank\Helpers\Functions::log(
                sprintf(
                    'Gateway::validate_split_payments_repeater_field - Processing receiver %d: account_id=%s, percentage=%s',
                    $index,
                    $account_id,
                    $percentage
                ),
                'info'
            );

            // Skip empty entries
            if (empty($account_id) && empty($percentage)) {
                \RM_PagBank\Helpers\Functions::log(
                    sprintf('Gateway::validate_split_payments_repeater_field - Receiver %d is empty, skipping', $index),
                    'info'
                );
                continue;
            }

            // Validate Account ID format if provided
            if (!empty($account_id)) {
                $pattern = '/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/';
                if (!preg_match($pattern, $account_id)) {
                    WC_Admin_Settings::add_error(
                        sprintf(
                            __('Account ID inválido na linha %d: %s', 'pagbank-connect'),
                            $index + 1,
                            esc_html($account_id)
                        )
                    );
                    \RM_PagBank\Helpers\Functions::log(
                        sprintf('Gateway::validate_split_payments_repeater_field - Receiver %d has invalid account_id format', $index),
                        'info'
                    );
                    continue;
                }
            }

            // Validate percentage (0-100)
            if ($percentage < 0 || $percentage > 100) {
                WC_Admin_Settings::add_error(
                    sprintf(
                        __('Percentual inválido na linha %d: deve estar entre 0 e 100', 'pagbank-connect'),
                        $index + 1
                    )
                );
                \RM_PagBank\Helpers\Functions::log(
                    sprintf('Gateway::validate_split_payments_repeater_field - Receiver %d has invalid percentage', $index),
                    'info'
                );
                continue;
            }

            // Only add if both account_id and percentage are valid
            if (!empty($account_id) && $percentage > 0) {
                $sanitized[] = [
                    'account_id' => $account_id,
                    'percentage' => round($percentage, 2)
                ];
                \RM_PagBank\Helpers\Functions::log(
                    sprintf('Gateway::validate_split_payments_repeater_field - Receiver %d added to sanitized array', $index),
                    'info'
                );
            } else {
                \RM_PagBank\Helpers\Functions::log(
                    sprintf('Gateway::validate_split_payments_repeater_field - Receiver %d skipped: account_id empty or percentage <= 0', $index),
                    'info'
                );
            }
        }

        // Validate that total percentage is less than 100% (primary account also receives a portion)
        $total_percentage = 0;
        foreach ($sanitized as $receiver) {
            $total_percentage += $receiver['percentage'];
        }

        if ($total_percentage >= 100) {
            WC_Admin_Settings::add_error(
                sprintf(
                    __('A soma dos percentuais das contas secundárias deve ser menor que 100%%. Total atual: %.2f%%. A conta principal também receberá uma parte do pagamento.', 'pagbank-connect'),
                    $total_percentage
                )
            );
            \RM_PagBank\Helpers\Functions::log(
                sprintf(
                    'Gateway::validate_split_payments_repeater_field - Total percentage %.2f%% is >= 100%%, validation failed',
                    $total_percentage
                ),
                'info'
            );
            // Return empty array to prevent saving invalid configuration
            return [];
        }

        \RM_PagBank\Helpers\Functions::log(
            sprintf(
                'Gateway::validate_split_payments_repeater_field - Returning %d sanitized receivers with total percentage %.2f%%',
                count($sanitized),
                $total_percentage
            ),
            'info'
        );

        return $sanitized;
    }

    public function validate_icons_color_field($key, $icon_color)
    {
        //Validate if dynamic icon is accessible
        delete_transient('rm_pagbank_dynamic_ico_accessible');
        $isDynamicIcoAccessible = Params::getIsDynamicIcoAccessible();
        if (!$isDynamicIcoAccessible) {
            WC_Admin_Settings::add_error(__('A personalização da cor dos ícones foi desativada, pois alguma configuração de sua loja ou ambiente impede ele de ser utilizado/acessado.', 'pagbank-connect'));
            $icon_color = 'gray';
        }
        
        return $icon_color;
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

    public static function addStylesWoo($styles)
    {
        if ( Recurring::isRecurringEndpoint() )
        {
            $styles['rm-pagbank-recurring'] = [
                'src'     => plugins_url('public/css/recurring.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                'deps'    => [],
                'version' => WC_PAGSEGURO_CONNECT_VERSION,
                'media'   => 'all',
                'has_rtl' => false,
            ];
        }
        return $styles;
    }

    /**
     * Retrieves cached Connect info or fetches fresh data from the API.
     *
     * @param bool $force_refresh Whether to force refresh the cached data.
     * @return array|null The connect information or null if unavailable.
     */
    public function getCachedConnectInfo($transient_key, $force_refresh = false)
    {
        // Return cached data if not forcing refresh
        if (! $force_refresh ) {
            $cached = get_transient($transient_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch fresh data from the API
        $api = new Api();
        $info = $api->getConnectInfo();

        // Cache the result if it's valid
        if (! empty($info) && empty($info['error_messages'])) {
            set_transient($transient_key, $info, DAY_IN_SECONDS);
        }

        return $info;
    }

    /**
     * Generates the connection status HTML badge based on the current Connect key info.
     *
     * @return string|null HTML output of the status badge or null if not applicable.
     */
    public function connectKeyStatus()
    {
        // Retrieve plugin settings
        $settings = get_option('woocommerce_' . $this->id . '_settings');
        $connect_key = $settings['connect_key'] ?? '';
        $last_four = strlen($connect_key) == 40 ? substr($connect_key, -4) : null;

        if (empty($connect_key) || !$last_four) {
            return null;
        }

        $transient_key = sprintf('pagbank_connect_key_info_%s', $last_four);
   
        $force_refresh = isset($_GET['refresh_connect_info']);
        // Force refresh if requested via URL
        if ($force_refresh) {
            delete_transient($transient_key);
        }

        // Get cached or fresh Connect info
        $info = $this->getCachedConnectInfo($transient_key, $force_refresh);

        if (!$info) {
            return null;
        }

        // Extract and sanitize connect status info
        $dateFormat = get_option('date_format');
        $status   = strtoupper($info['status'] ?? 'UNKNOWN');
        $email    = esc_html($info['authorizerEmail'] ?? 'N/A');
        $accountId    = esc_html($info['accountId'] ?? 'N/A');
        $expires  = esc_html(isset($info['expiresAt']) && $info['expiresAt'] ? date_i18n($dateFormat, strtotime($info['expiresAt'])) : '-');
        $isSandbox = !empty($info['isSandbox']);
        $sandbox  = $isSandbox ? 'Sim' : 'Não';
        $sandbox = !isset($info['isSandbox']) ? 'Desconhecido' : $sandbox;
        $message  = "Conta PagBank: $email <br>";
        $message .= "Account ID: $accountId <br>";
        $message .= !$isSandbox ? "Expira em: $expires <br>" : null;
        $message .= "Sandbox: $sandbox <br>";
        $message .= !$isSandbox ? "* renova automaticamente" : null;
        // Tooltip with detailed connect information
        $tooltip = esc_attr($message);

        // Generate status badge based on the current status
        switch ($status) {
            case "VALID":
                $btn = $this->buildStatusBadge('#4caf50', 'dashicons-yes', 'Conectado');
                break;
            case "INVALID":
                $btn = $this->buildStatusBadge('#f44336', 'dashicons-dismiss', 'Chave inválida');
                break;
            case "UNAUTHORIZED":
                $btn = $this->buildStatusBadge('#ff9800', 'dashicons-lock', 'Não autorizado');
                break;
            case "UNKNOWN":
                $btn = $this->buildStatusBadge('#9e9e9e', 'dashicons-info-outline', 'Desconhecido');
                break;
            default:
                $btn = $this->buildStatusBadge('#888', 'dashicons-info-outline', 'Erro ao obter informação');
                break;
        }

        $this->setStyleConnectKeyInfo();
        // Generate final HTML with badge, refresh button, and tooltip
        $html = '<div class="rm-pagbank-status-container">';
        $html .= $btn;
        $html .= '<a href="' . esc_url(add_query_arg('refresh_connect_info', 1)) . '" title="Atualizar" class="rm-pagbank-refresh-button">';
        $html .=  '<span class="dashicons dashicons-update-alt"></span>';
        $html .= '</a>';
        $html .= '<span class="dashicons dashicons-info" data-tip="' . $tooltip . '"></span>';
        $html .= '</div>';

       if ($isSandbox) {
            $html .= '<div class="rm-pagbank-connect-key-info">';
            $html .= '<span class="dashicons dashicons-warning"></span>';
            $html .= '<strong>' . __('Sandbox ativo', 'pagbank-connect') . ': </strong> '
                . __('você está testando o PagBank. Pedidos feitos neste ambiente não aparecerão no PagBank.', 'pagbank-connect')
                . '<br>';

            $html .= '<a href="' . esc_url('https://developer.pagbank.com.br/docs/simulador') . '" target="_blank" class="rm-pagbank-doc-link">';
            $html .= __('Documentação do Simulador', 'pagbank-connect');
            $html .= '</a><br>';

            $html .= '<a href="' . esc_url('https://ajuda.pbintegracoes.com/hc/pt-br/articles/22375426666253-Cart%C3%B5es-de-Cr%C3%A9dito-para-Testes-PagBank') . '" target="_blank" class="rm-pagbank-doc-link">';
            $html .= __('Cartões de Teste', 'pagbank-connect');
            $html .= '</a>';

            $html .= '</div>';
        }
       
        return $html;
    }
    /**
     * Adds custom styles for the Connect Key info badge in the admin area.
     * @return void
     */
    public function setStyleConnectKeyInfo()
    {
        wp_enqueue_style(
            'rm-pagbank-admin-connect-key-info', 
            plugins_url('public/css/admin/connect-key-info.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            false, 
            WC_PAGSEGURO_CONNECT_VERSION
        );
    }
    /**
     * Helper to build a styled status badge.
     *
     * @param string $color       Background color of the badge.
     * @param string $icon_class  Dashicon class to use.
     * @param string $label       Text label of the badge.
     * @return string             HTML of the badge.
     */
    private function buildStatusBadge($color, $icon_class, $label)
    {
        return sprintf(
            '<div class="rm-pagbank-status-badge learn-more">
                    <span class="circle" aria-hidden="true" style="background: %s;">
                        <span class="dashicons %s"></span>
                    </span>
                    <span class="button-text"> %s</span>
            </div>',
            esc_attr($color),
            esc_attr($icon_class),
            esc_html($label)
        );
    }
}
