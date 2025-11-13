<?php
namespace RM_PagBank\Connect\Recurring\Admin\Subscriptions;

use RM_PagBank\Helpers\Recurring;
use WP_List_Table;

if ( ! class_exists ( 'WP_List_Table' ) ) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Page that shows the subscription details in the admin area
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Recurring\Admin\Subscriptions
 */
class SubscriptionDetails extends WP_List_Table
{
    protected $subscription;

    public function display() {
        $singular = $this->_args['singular'];

        $this->display_tablenav( 'top' );

        $this->screen->render_screen_reader_content( 'heading_list' );
        ?>
        <table class="wp-list-table <?php echo esc_attr(implode(' ', $this->get_table_classes())); ?>">
            <?php $this->print_table_description(); ?>
            <thead>
            <tr>
                <?php $this->print_column_headers(); ?>
            </tr>
            </thead>

            <tbody id="the-list"
                <?php if ($singular) {?> 
                    data-wp-lists="list:<?php echo esc_attr($singular) ?>"
                    <?php
                }
                ?>>
            <?php $this->display_rows_or_placeholder(); ?>
            </tbody>
        </table>
        <?php
        $this->display_tablenav( 'bottom' );
    }

    public function __construct($subscription)
    {
        parent::__construct([
            'singular' => __('Detalhe', 'rm-pagbank'),
            'plural'   => __('Detalhes', 'rm-pagbank'),
            'ajax'     => false
        ]);

        $this->subscription = $subscription;
    }

    public function get_columns()
    {
        return [
            'name' => __('Nome', 'rm-pagbank'),
            'value' => __('Valor', 'rm-pagbank'),
        ];
    }

    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    public function column_value($item)
    {
        $name = $item['name'] ?? '';
        $value = $item['value'] ?? '';
        if ($name !== 'Pedido Inicial') {
            return $value;
        }

        $order = wc_get_order($value);
        if (!$order) {
            return $value;
        }

        return '<a href="' . $order->get_edit_order_url() . '">' . $value . '</a>';
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns()];
        global $wpdb;

        $recHelper = new Recurring();
        $status = $recHelper->getFriendlyStatus($this->subscription->status);
        $type = $recHelper->translateFrequency($this->subscription->recurring_type);


    $order = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pagb_recurring.*, wc_customer.email as billing_email"
                    . " , CONCAT(wc_customer.first_name, ' ', wc_customer.last_name) as billing_name"
                    . " FROM {$wpdb->prefix}pagbank_recurring pagb_recurring"
                        . " inner join {$wpdb->prefix}wc_orders wc_order ON pagb_recurring.initial_order_id = wc_order.id"
                        . " inner join {$wpdb->prefix}wc_customer_lookup wc_customer ON wc_order.customer_id = wc_customer.customer_id"
                        . " WHERE pagb_recurring.initial_order_id = %d",
                    $this->subscription->initial_order_id
                ),
                ARRAY_A
            );



        $this->items = [
            ['name' => 'ID', 'value' => $this->subscription->id],
            ['name' => 'Pedido Inicial', 'value' => $this->subscription->initial_order_id],
            ['name' => 'Valor Recorrente', 'value' => $this->subscription->recurring_amount],
            ['name' => 'Status', 'value' => $status],
            ['name' => 'Nome Cliente', 'value' => $order['0']['billing_name']],
            ['name'=> 'Email', 'value'=> $order[0]['billing_email']]
        ];

        if ($this->subscription->recurring_trial_period) {
            $this->items[] = ['name' => 'Período de testes (dias)', 'value' => $this->subscription->recurring_trial_period];
        }

        if ((int)$this->subscription->recurring_discount_cycles && (float)$this->subscription->recurring_discount_amount) {
            $this->items[] = ['name' => 'Desconto', 'value' => $this->subscription->recurring_discount_amount];
            $this->items[] = ['name' => 'Ciclos com desconto', 'value' => $this->subscription->recurring_discount_cycles];
        }

        $this->items[] = ['name' => 'Tipo Recorrente', 'value' => $type];
        $this->items[] = ['name' => 'Criado em', 'value' => date_i18n(get_option('date_format'), strtotime($this->subscription->created_at))];
        $this->items[] = ['name' => 'Atualizado em', 'value' => date_i18n(get_option('date_format'), strtotime($this->subscription->updated_at))];
        if ( in_array($this->subscription->status, ['ACTIVE', 'PENDING', 'SUSPENDED']) ):
            $this->items[] = ['name' => 'Próxima Cobrança', 'value' => date_i18n(get_option('date_format'), strtotime($this->subscription->next_bill_at))];
        endif;
    }
}