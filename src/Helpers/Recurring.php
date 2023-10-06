<?php

namespace RM_PagBank\Helpers;

use WC_Cart;
use WC_Order;

class Recurring
{


    /**
     * Checks if the $cart or the current cart contains recurring products
     * @param WC_Cart|null $cart
     *
     * @return bool
     */
    public function isCartRecurring(WC_Cart $cart = null): bool
    {
        if (!$cart) $cart = WC()->cart;
        foreach ($cart->get_cart() as $cartItem) {
            $product = $cartItem['data'];
            if ($product->get_meta('_recurring_enabled') == 'yes') 
                return true;
        }
        
        return false;
    }
    
}