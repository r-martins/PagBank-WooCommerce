<?php
namespace RM_PagBank\Connect\Recurring;

use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;

/**
 * Related to recurring orders and its creations
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect\Recurring
 */
class RecurringOrder
{
    private $subscription;

    /**
     * @param $subscription
     */
    public function __construct($subscription)
    {
        $this->subscription = $subscription;
    }

    public function createRecurringOrderFromSub()
    {
        $subscription = $this->subscription;
        $initialOrder = wc_get_order($subscription->initial_order_id);
        $order = wc_create_order([
            'customer_id' => $initialOrder->get_customer_id('edit'),
            'parent'    => $initialOrder->get_id(),
            'total' => $initialOrder->get_total('edit')
        ]);

        /** @var WC_Order_Item_Product $item */
        foreach ($initialOrder->get_items() as $item){
            /** @var WC_Product $itemObj */
            $itemObj = wc_get_product($item->get_product_id());
            $itemObj->update_meta_data('_frequency', $initialOrder->get_meta('_recurring_frequency'));
            $itemObj->update_meta_data('_cycle', $initialOrder->get_meta('_recurring_cycle'));

            $order->add_product($itemObj, $item->get_quantity('edit'));
        }

        $order->set_address($initialOrder->get_address('billing'), 'billing');
        $order->set_address($initialOrder->get_address('shipping'), 'shipping');
        $order->set_payment_method_title($initialOrder->get_payment_method_title('edit'));
        $order->set_payment_method($initialOrder->get_payment_method('edit'));
        $order->set_total($subscription->recurring_amount);
        
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title($initialOrder->get_shipping_method());
        
        $order->add_item($shipping);
        $order->set_shipping_total($initialOrder->get_shipping_total('edit'));
        
        $order->save();

//        $this->updateSubscription($subscription, $order);
    }
}