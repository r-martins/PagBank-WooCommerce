<?php
namespace RM_PagBank\Connect\Recurring\Admin\Subscriptions;

use RM_PagBank\Helpers\Recurring;
use WP_List_Table;

if ( ! class_exists ( 'WP_List_Table' ) ) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * List of subscriptions
 * 
 * @package RM_PagBank\Connect\Recurring\Admin\Subscriptions
 */
class SubscriptionList extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Assinatura', 'rm-pagbank'),
            'plural'   => __('Assinaturas', 'rm-pagbank'),
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return [
            'id'                 => __('ID', 'rm-pagbank'),
            'initial_order_id'   => __('Pedido Inicial', 'rm-pagbank'),
            'recurring_amount'   => __('Valor Recorrente', 'rm-pagbank'),
            'status'             => __('Status', 'rm-pagbank'),
            'recurring_type'     => __('Tipo Recorrente', 'rm-pagbank'),
            'created_at'         => __('Criado em', 'rm-pagbank'),
            'updated_at'         => __('Atualizado em', 'rm-pagbank'),
            'next_bill_at'       => __('Próxima Cobrança', 'rm-pagbank'),
            'view'                 => __('Visualizar', 'rm-pagbank'),
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'created_at':
            case 'updated_at':
            case 'next_bill_at':
                return date_i18n(get_option('date_format'), strtotime($item[$column_name]));
            case 'recurring_type':
                $recHelper = new Recurring();
                return $recHelper->translateFrequency($item[$column_name]);
            case 'status':
                $recHelper = new Recurring();
                return $recHelper->getFriendlyStatus($item[$column_name]);
            default:
                return $item[$column_name];
        }
    }

    public function column_initial_order_id($item)
    {
        if (!isset($item['initial_order_id'])) {
            return '';
        }
        
        $order = wc_get_order($item['initial_order_id']);
        if (!$order || is_bool($order)) {
            return htmlspecialchars($item['initial_order_id']);
        }
        
        return '<a href="' . $order->get_edit_order_url() . '">' . htmlspecialchars($item['initial_order_id']) . '</a>';
    }

    public function column_view($item)
    {
        return sprintf('<a href="?page=%s&action=%s&id=%s">Visualizar</a>', 'rm-pagbank-subscriptions-view', 'view', $item['id']);
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns(), array(), $this->get_sortable_columns()];

        global $wpdb;
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}pagbank_recurring");

        $orderby = (isset($_GET['orderby']) && in_array($_GET['orderby'], array_keys($this->get_sortable_columns()))) ? $_GET['orderby'] : 'id';
        $orderby = wp_unslash($orderby);
        $order = (isset($_GET['order']) && in_array($_GET['order'], array('asc', 'desc'))) ? $_GET['order'] : 'asc'; //phpcs:ignore WordPress.Security.NonceVerification

        $where = "1=1";
        if (!empty($_REQUEST['status'])) {
            $status = sanitize_text_field(wp_unslash($_REQUEST['status']));
            $where .= " AND status = '$status'";
        }
        if (!empty($_REQUEST['order_id'])) {
            $order_id = intval($_REQUEST['order_id']);
            $where .= " AND initial_order_id = $order_id";
        }


        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page,
                ($current_page - 1) * $per_page
            ),
            ARRAY_A
        );
    }

    public function get_sortable_columns()
    {
        return array(
            'id' => array('id', false),
            'initial_order_id' => array('initial_order_id', false),
            'recurring_amount' => array('recurring_amount', false),
            'status' => array('status', false),
            'recurring_type' => array('recurring_type', false),
            'created_at' => array('created_at', false),
            'updated_at' => array('updated_at', false),
            'next_bill_at' => array('next_bill_at', false),
        );
    }
    
    public function extra_tablenav($which) {
        $page = $_REQUEST['page'] ?? '';
    if ($which == "top"){
        ?>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($page) ?>" />
            <div class="alignleft actions bulkactions">
                <select name="status" id="filter-by-status">
                    <option value=""><?php echo esc_attr('Todos os status', 'rm-pagbank');?></option>
                    <?php foreach (Recurring::getAllStatuses() as $value => $status):?>
                        <option value="<?php echo esc_attr($value);?>"><?php echo esc_attr($status);?></option>
                    <?php endforeach;?>
                </select>
                <input type="text" name="order_id" id="filter-by-order-id" placeholder="<?php echo esc_attr(__('ID do Pedido', 'rm-pagbank'));?>">
                <?php submit_button(__('Filtrar'), 'button', 'filter_action', false);?>
            </div>
        </form>
        <?php
    }
}
}