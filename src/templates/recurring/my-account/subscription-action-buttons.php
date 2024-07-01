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
$isAdminEndpoint = is_admin() && ! defined( 'DOING_AJAX' );
$cancelURL = WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=cancel&id=' . $subscription->id;
$cancelURL .= $isAdminEndpoint ? '&fromAdmin=1' : '';
$actions = apply_filters('rm_pagbank_account_recurring_actions', [
    'cancel' => [
        'name' => __('Cancelar Assinatura', 'pagbank-connect'),
        'url' => $cancelURL,
        'class' => 'subscription-button cancel',
    ],
    'uncancel' => [
        'name' => __('Suspender Cancelamento', 'pagbank-connect'),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=uncancel&id=' . $subscription->id,
        'class' => 'subscription-button uncancel',
    ],
    'pause' => [
        'name' => __('Pausar Assinatura', 'pagbank-connect'),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=pause&id=' . $subscription->id,
        'class' => 'subscription-button suspend',
    ],
    'unpause' => [
        'name' => __('Resumir Assinatura', 'pagbank-connect'),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=unpause&id=' . $subscription->id,
        'class' => 'subscription-button suspend',
    ],
    'edit' => [
        'name' => __('Editar Assinatura', 'pagbank-connect'),
        'url' => admin_url('admin.php?page=rm-pagbank-subscriptions-edit&action=edit&id=' . $subscription->id),
        'class' => 'subscription-button edit',
    ],
    // Coming soon
    /*'update' => [
        'name' => __('Atualizar Cartão', 'pagbank-connect'),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=update&id=' . $subscription->id,
        'class' => 'subscription-button update',
    ]*/
], $subscription);
if ( ! empty( $actions ) ) {
    foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button ' . esc_attr( $action['class'] ) . ' button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
    }
}