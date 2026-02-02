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
            'recurring_amount'   => __('Valor Recorrente', 'rm-pagbank'),
            'status'             => __('Status', 'rm-pagbank'),
            'recurring_type'     => __('Tipo Recorrente', 'rm-pagbank'),
            'created_at'         => __('Criado em', 'rm-pagbank'),
            'updated_at'         => __('Atualizado em', 'rm-pagbank'),
            'next_bill_at'       => __('Próxima Cobrança', 'rm-pagbank'),
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'created_at':
            case 'updated_at':
                $date_format = get_option('date_format');
                $time_format = get_option('time_format');
                return date_i18n($date_format . ' ' . $time_format, strtotime($item[$column_name]));
            case 'next_bill_at':
                return in_array($item['status'], ['ACTIVE', 'PENDING', 'SUSPENDED']) ? date_i18n(get_option('date_format'), strtotime($item[$column_name])) : "N/A";
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

        $orderby = (isset($_GET['orderby']) && in_array($_GET['orderby'], array_keys($this->get_sortable_columns()))) ? $_GET['orderby'] : 'initial_order_id';
        $orderby = wp_unslash($orderby);
        $order = (isset($_GET['order']) && in_array($_GET['order'], array('asc', 'desc'))) ? $_GET['order'] : 'desc'; //phpcs:ignore WordPress.Security.NonceVerification

        $where = "1=1";
        $where_params = [];
        
        if (!empty($_REQUEST['status'])) {
            $status = sanitize_text_field(wp_unslash($_REQUEST['status']));
            $where .= " AND status = %s";
            $where_params[] = $status;
        }
        if (!empty($_REQUEST['order_id'])) {
            $order_id = intval($_REQUEST['order_id']);
            $where .= " AND initial_order_id = %d";
            $where_params[] = $order_id;
        }
        if (!empty($_REQUEST['customer_email'])) {
            $customer_email = sanitize_email(wp_unslash($_REQUEST['customer_email']));
            // Find order IDs by customer email (works with both HPOS and legacy)
            $order_ids = $this->get_order_ids_by_email($customer_email);
            if (!empty($order_ids)) {
                $order_ids_escaped = array_map('intval', $order_ids);
                $order_ids_string = implode(',', $order_ids_escaped);
                $where .= " AND initial_order_id IN ($order_ids_string)";
            } else {
                // No orders found with this email, return empty result
                $where .= " AND 1=0";
            }
        }

        // Get total items count with the same WHERE clause
        if (!empty($where_params)) {
            $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}pagbank_recurring WHERE $where", ...$where_params));
        } else {
            $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}pagbank_recurring WHERE $where");
        }

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        // Build the query with parameters
        $query = "SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $query_params = array_merge($where_params, [$per_page, ($current_page - 1) * $per_page]);
        
        if (!empty($where_params)) {
            $this->items = $wpdb->get_results(
                $wpdb->prepare($query, ...$query_params),
                ARRAY_A
            );
        } else {
            // If no where params, we still need to prepare for LIMIT and OFFSET
            $this->items = $wpdb->get_results(
                $wpdb->prepare($query, $per_page, ($current_page - 1) * $per_page),
                ARRAY_A
            );
        }
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

    /**
     * Get order IDs by customer email (works with both HPOS and legacy order storage)
     *
     * @param string $email Customer email address
     * @return array Array of order IDs
     */
    private function get_order_ids_by_email($email)
    {
        global $wpdb;
        
        // Check if HPOS is enabled
        if (\RM_PagBank\Helpers\Functions::isHposEnabled()) {
            // HPOS: Use wc_get_orders with billing_email parameter
            $orders = wc_get_orders([
                'billing_email' => $email,
                'limit' => -1,
                'return' => 'ids',
            ]);
            return is_array($orders) ? $orders : [];
        } else {
            // Legacy: Query posts directly using SQL to avoid meta_query issue
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT p.ID 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status NOT IN ('auto-draft', 'trash')
                AND pm.meta_key = '_billing_email'
                AND pm.meta_value = %s",
                $email
            ));
            return is_array($order_ids) ? array_map('intval', $order_ids) : [];
        }
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
                    <?php 
                    $selected_status = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : '';
                    foreach (Recurring::getAllStatuses() as $value => $status):?>
                        <option value="<?php echo esc_attr($value);?>" <?php selected($selected_status, $value); ?>><?php echo esc_attr($status);?></option>
                    <?php endforeach;?>
                </select>
                <input type="text" name="order_id" id="filter-by-order-id" placeholder="<?php echo esc_attr(__('ID do Pedido', 'rm-pagbank'));?>" value="<?php echo isset($_REQUEST['order_id']) ? esc_attr(wp_unslash($_REQUEST['order_id'])) : ''; ?>">
                <input type="email" name="customer_email" id="filter-by-customer-email" placeholder="<?php echo esc_attr(__('Email do Cliente', 'rm-pagbank'));?>" value="<?php echo isset($_REQUEST['customer_email']) ? esc_attr(wp_unslash($_REQUEST['customer_email'])) : ''; ?>">
                <?php submit_button(__('Filtrar'), 'button', 'filter_action', false);?>
            </div>
        </form>
        <?php
    }
}
}