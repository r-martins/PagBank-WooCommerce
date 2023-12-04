<?php
namespace RM_PagBank\Connect\Recurring\Admin\Subscriptions\Details;

use WP_List_Table;

/**
 * Orders related to a subscription
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Recurring\Admin\Subscriptions\Details
 */
class OrdersList extends WP_List_Table
{
    private $subscription;

    public function __construct($subscription)
    {
        parent::__construct([
            'singular' => __('Pedido', 'rm-pagbank'),
            'plural'   => __('Pedidos', 'rm-pagbank'),
            'ajax'     => false
        ]);

        $this->subscription = $subscription;
    }

    public function get_columns()
    {
        return [
            'id' => __('ID', 'rm-pagbank'),
            'date' => __('Data', 'rm-pagbank'),
            'status' => __('Status', 'rm-pagbank'),
            'total' => __('Total', 'rm-pagbank'),
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'id':
                return '<a href="' . $item->get_edit_order_url() . '">' . $item->get_id() . '</a>';
            case 'date':
                return date_i18n(get_option('date_format'), strtotime($item->get_date_created()));
            default:
                return $item->get_data()[$column_name];
        }
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns()];

        $orders = wc_get_orders(['post_parent' => $this->subscription->initial_order_id]);
        $this->items = $orders;
    }
}