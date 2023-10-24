<?php
/**
 * Subscription Order List
 *
 * List of Orders created from this subscription
 *
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/my-account/subscription-order-list.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package RM_PagBank\Templates
 * @version 4.0.0
 */

/** @var stdClass $subscription */


defined( 'ABSPATH' ) || exit;
do_action('rm_pagbank_before_account_recurring_orders_list', $subscription);