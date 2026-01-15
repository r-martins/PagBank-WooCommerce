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

    /**
     * Handles the display of default column values for the orders list table.
     * @param array|object $item
     * @param mixed $column_name
     */
    public function column_default($item, $column_name)
    {
        $editOrderUrl = method_exists( $item, 'get_edit_order_url') ? $item->get_edit_order_url() : '';
        if (!$editOrderUrl) {
            $parentOrderId = $item->get_parent_id();
            $parentOrder = wc_get_order($parentOrderId);
            $editOrderUrl = $parentOrder ? $parentOrder->get_edit_order_url() : '';
        }
        switch ($column_name) {
            case 'id':
                $id = isset($parentOrder) ? $parentOrder->get_id() : $item->get_id();
                return '<a href="' . $editOrderUrl . '">' . $id . '</a>';
            case 'date':
                $date_format = get_option('date_format');
                $time_format = get_option('time_format');
                return date_i18n($date_format . ' ' . $time_format, strtotime($item->get_date_created()));
            default:
                return (isset($parentOrder)) ? $parentOrder->get_data()[$column_name] : $item->get_data()[$column_name];
        }
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns()];

        $orders = wc_get_orders(['post_parent' => $this->subscription->initial_order_id]);
        $this->items = $orders;
    }
}