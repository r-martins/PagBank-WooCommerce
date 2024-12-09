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
        $editOrderUrl = method_exists('get_edit_order_url', $item) ? $item->get_edit_order_url() : '';
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
                return date_i18n(get_option('date_format'), strtotime($item->get_date_created()));
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