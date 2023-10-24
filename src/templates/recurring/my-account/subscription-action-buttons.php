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
        'url' => wc_get_endpoint_url( 'rm-pagbank-subscriptions-view/' . $subscription->id, '', wc_get_page_permalink( 'myaccount' ) ) . '?action=cancel&id=' . $subscription->id,
        'class' => 'subscription-button cancel',
    ],
    'pause' => [
        'name' => __('Pausar Assinatura', RM_PagBank\Connect::DOMAIN),
        'url' => wc_get_endpoint_url( 'rm-pagbank-subscriptions-view/' . $subscription->id, '', wc_get_page_permalink( 'myaccount' ) ) . '?action=pause&id=' . $subscription->id,
        'class' => 'subscription-button suspend',
    ],
    'unpause' => [
        'name' => __('Resumir Assinatura', RM_PagBank\Connect::DOMAIN),
        'url' => wc_get_endpoint_url( 'rm-pagbank-subscriptions-view/' . $subscription->id, '', wc_get_page_permalink( 'myaccount' ) ) . '?action=unpause&id=' . $subscription->id,
        'class' => 'subscription-button suspend',
    ],
    'activate' => [
        'name' => __('Ativar Assinatura', RM_PagBank\Connect::DOMAIN),
        'url' => wc_get_endpoint_url( 'rm-pagbank-subscriptions-view/' . $subscription->id, '', wc_get_page_permalink( 'myaccount' ) ) . '?action=activate&id=' . $subscription->id,
        'class' => 'subscription-button activate',
    ],
    'update' => [
        'name' => __('Atualizar Cartão', RM_PagBank\Connect::DOMAIN),
        'url' => wc_get_endpoint_url( 'rm-pagbank-subscriptions-view/' . $subscription->id, '', wc_get_page_permalink( 'myaccount' ) ) . '?action=update&id=' . $subscription->id,
        'class' => 'subscription-button update',
    ]
]);
if ( ! empty( $actions ) ) {
    foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button ' . esc_attr( $action['class'] ) . ' button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
    }
}