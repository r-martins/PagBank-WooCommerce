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
            'view'                 => __('Visualizar', 'rm-pagbank'),
            'recurring_amount'   => __('Valor Recor.', 'rm-pagbank'),
            'status'             => __('Status', 'rm-pagbank'),
            'recurring_type'     => __('Tipo Recor.', 'rm-pagbank'),
            'created_at'         => __('Criado em', 'rm-pagbank'),
            'updated_at'         => __('Atualizado em', 'rm-pagbank'),
            'next_bill_at'       => __('Próxima Cobrança', 'rm-pagbank'),
            'billing_name'       => __('Cliente', 'rm-pagbank'),
            'billing_email'       => __('Email', 'rm-pagbank'),
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'created_at':
            case 'updated_at':
                return date_i18n(get_option('date_format'), strtotime($item[$column_name]));
            case 'next_bill_at':
                return in_array($item['status'], ['ACTIVE', 'PENDING', 'SUSPENDED']) ? date_i18n(get_option('date_format'), strtotime($item[$column_name])) : "N/A";
            case 'recurring_type':
                $recHelper = new Recurring();
                return $recHelper->translateFrequency($item[$column_name]);
            case 'status':
                $recHelper = new Recurring();
                return $recHelper->getFriendlyStatus($item[$column_name]);
            case 'billing_name':
                return $item['first_name'] . ' '. $item['last_name'];
            case 'initial_order_id':
                 $item['initial_order_id'];
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

        $orderby = (isset($_GET['orderby']) && in_array($_GET['orderby'], array_keys($this->get_sortable_columns()))) ? $_GET['orderby'] : 'initial_order_id';
        $orderby = wp_unslash($orderby);
        $order = (isset($_GET['order']) && in_array($_GET['order'], array('asc', 'desc'))) ? $_GET['order'] : 'desc'; //phpcs:ignore WordPress.Security.NonceVerification

        global $wpdb;
        $where = "1=1";
        if (!empty($_REQUEST['status'])) {
            $status = sanitize_text_field(wp_unslash($_REQUEST['status']));
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        if (!empty($_REQUEST['order_id'])) {
            $order_id = intval($_REQUEST['order_id']);
            $where .= $wpdb->prepare(" AND initial_order_id = %d", $order_id);
        }
        if (!empty($_REQUEST['customer_name'])) {
            $customer_name = sanitize_text_field(wp_unslash($_REQUEST['customer_name']));
            $where .= $wpdb->prepare(" AND (CONCAT(first_name, ' ', last_name) like %s )", '%' . $wpdb->esc_like($customer_name) . '%');
        }
        if (!empty($_REQUEST['customer_email'])) {
            $customer_email = sanitize_text_field(wp_unslash($_REQUEST['customer_email']));
            $where .= $wpdb->prepare(" AND billing_email like %s", '%' . $wpdb->esc_like($customer_email) . '%');
        }


        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pagb_recurring.*,  wc_customer.*, "
                . "CONCAT(wc_customer.first_name, ' ', wc_customer.last_name) as billing_name FROM {$wpdb->prefix}pagbank_recurring pagb_recurring"
                    . " inner join {$wpdb->prefix}wc_orders wc_order ON pagb_recurring.initial_order_id = wc_order.id"
                    . " inner join {$wpdb->prefix}wc_customer_lookup wc_customer ON wc_order.customer_id = wc_customer.customer_id"
                    . " WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
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
            'billing_name' => array('billing_name', false),
            'billing_email' => array('billing_email', false),
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
                <input type="text" name="customer_name" id="filter-by-customer-name" placeholder="<?php echo esc_attr(__('Nome', 'rm-pagbank'));?>">
                <input type="text" name="customer_email" id="filter-by-customer-email" placeholder="<?php echo esc_attr(__('Email', 'rm-pagbank'));?>">
                <?php submit_button(__('Filtrar'), 'button', 'filter_action', false);?>
            </div>
        </form>
        <?php
    }
}
}