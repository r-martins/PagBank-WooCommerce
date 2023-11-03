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
        'name' => __('Cancelar Assinatura', RM_PagBank\Connect::DOMAIN),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=cancel&id=' . $subscription->id,
        'class' => 'subscription-button cancel',
    ],
    'uncancel' => [
        'name' => __('Suspender Cancelamento', RM_PagBank\Connect::DOMAIN),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=uncancel&id=' . $subscription->id,
        'class' => 'subscription-button uncancel',
    ],
    'pause' => [
        'name' => __('Pausar Assinatura', RM_PagBank\Connect::DOMAIN),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=pause&id=' . $subscription->id,
        'class' => 'subscription-button suspend',
    ],
    'unpause' => [
        'name' => __('Resumir Assinatura', RM_PagBank\Connect::DOMAIN),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=unpause&id=' . $subscription->id,
        'class' => 'subscription-button suspend',
    ],
    'update' => [
        'name' => __('Atualizar Cartão', RM_PagBank\Connect::DOMAIN),
        'url' => WC()->api_request_url('rm-pagbank-subscription-edit'). '?action=update&id=' . $subscription->id,
        'class' => 'subscription-button update',
    ]
], $subscription);
if ( ! empty( $actions ) ) {
    foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button ' . esc_attr( $action['class'] ) . ' button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
    }
}