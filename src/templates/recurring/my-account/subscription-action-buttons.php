<?php
/**
 * Subscription Action Buttons
 *
 * Shown in My Account > Subscriptions > View Subscription Details under the subscription details.
 *
 * This template can be overridden by copying it to yourtheme/rm-pagbank/recurring/my-account/subscription-action-buttons.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package RM_PagBank\Templates
 * @version 4.0.0
 */

/** @var stdClass $subscription */

use RM_PagBank\Connect;
use RM_PagBank\Connect\Recurring\RecurringDashboard;
defined( 'ABSPATH' ) || exit;
do_action('rm_pagbank_before_account_recurring_action_buttons', $subscription);

if ( ! isset($subscription->id) || ! $subscription->id ) {
    return;
}

$actions = apply_filters('rm_pagbank_account_recurring_actions', [
    'cancel' => [
        'name' => __('Cancelar Assinatura', 'pagbank-connect'),
        'url' => subscriptionActionButtonsUrl('cancel', $subscription),
        'class' => 'subscription-button cancel',
    ],
    'uncancel' => [
        'name' => __('Suspender Cancelamento', 'pagbank-connect'),
        'url' => subscriptionActionButtonsUrl('uncancel', $subscription),
        'class' => 'subscription-button uncancel',
    ],
    'pause' => [
        'name' => __('Pausar Assinatura', 'pagbank-connect'),
        'url' => subscriptionActionButtonsUrl('pause', $subscription),
        'class' => 'subscription-button suspend',
    ],
    'unpause' => [
        'name' => __('Resumir Assinatura', 'pagbank-connect'),
        'url' => subscriptionActionButtonsUrl('unpause', $subscription),
        'class' => 'subscription-button suspend',
    ],
    'edit' => [
        'name' => __('Editar Assinatura', 'pagbank-connect'),
        'url' => admin_url('admin.php?page=rm-pagbank-subscriptions-edit&_action=edit&id=' . $subscription->id) . '&fromAdmin=1',
        'class' => 'subscription-button edit',
    ],
    'update' => [
        'name' => __('Atualizar Cartão', 'pagbank-connect'),
        'url' => pagbank_subscription_update_url($subscription->id),
        'class' => 'subscription-button update',
    ]
], $subscription);
if ( ! empty( $actions ) ) {
    foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button ' . esc_attr( $action['class'] ) . ' button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
    }
}

function subscriptionActionButtonsUrl($endpoint, $subscription){

    if ( ! $subscription || ! isset($subscription->id) ) {
        return false;
    }

    $isAdmin = is_admin() && ! defined( 'DOING_AJAX' );
    $url = WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=' . $endpoint . '&id=' . $subscription->id;
    $url .= $isAdmin ? '&fromAdmin=1' : '';
    return $url;

}

function pagbank_subscription_update_url($subscription_id) {
    global $wp_rewrite;
    if (! $wp_rewrite->using_permalinks()) {
        // Permalinks padrão: usa query string
        $account_page_id = wc_get_page_id('myaccount');
        $url = get_permalink($account_page_id);
        $url = add_query_arg('rm-pagbank-subscriptions-update', $subscription_id, $url);
        return $url;
    } else {
        // Permalinks amigáveis
        return wc_get_account_endpoint_url('rm-pagbank-subscriptions-update/' . $subscription_id);
    }
}

