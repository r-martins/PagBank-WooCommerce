<?php
namespace RM_PagBank\Connect;

use RM_PagBank\Connect\Recurring\Admin\Subscriptions\Details\OrdersList;
use RM_PagBank\Connect\Recurring\Admin\Subscriptions\SubscriptionDetails;
use RM_PagBank\Connect\Recurring\Admin\Subscriptions\SubscriptionList;

/**
 * Adds the PagBank menu and some of its submenus.
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Connect
 */
class MenuPagBank
{

    /**
     * Adds the main PagBank menu item
     * @return void
     */
    public static function addPagBankMenu()
    {
        $iconSvg = <<<SVG
<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2"><path d="M9.172.034c-1.77.172-3.847 1-5.299 2.113a10.638 10.638 0 0 0-1.749 1.736C-.15 6.809-.632 10.677.858 14.033c.252.569.475.973.826 1.5.395.594.66.914 1.231 1.487 1.276 1.282 2.431 1.976 4.152 2.498 1.412.428 3.066.53 4.533.279a10.08 10.08 0 0 0 4.533-2.03 11.96 11.96 0 0 0 1.535-1.534c2.454-3.101 2.877-7.225 1.101-10.727-.492-.97-1.01-1.679-1.819-2.492-1.19-1.197-2.41-1.933-4.183-2.524-.966-.321-1.693-.451-2.65-.472-.349-.008-.774 0-.945.016m1.662.649a8 8 0 0 1 6.665 5.503c.273.85.351 1.394.351 2.437 0 .535-.013.853-.035.875-.023.024-.111-.034-.283-.185-.795-.7-1.747-1.08-2.848-1.135l-.416-.022-.158-.203a8.737 8.737 0 0 0-1.091-1.084 7.123 7.123 0 0 0-3.552-1.522c-.464-.066-1.378-.064-1.867.003a7.11 7.11 0 0 0-4.068 2.017c-.565.56-.896.998-1.294 1.714l-.199.357-.024-.224c-.042-.393.005-1.49.083-1.931A7.647 7.647 0 0 1 2.8 5.117a8.083 8.083 0 0 1 2.783-3.166 9.15 9.15 0 0 1 1.844-.897C8.066.842 8.618.73 9.4.655a9.27 9.27 0 0 1 1.434.028M9.067 5.934a6.42 6.42 0 0 1 1.892.446 6.339 6.339 0 0 1 1.504.846c.282.21 1.07.964 1.07 1.023 0 .021-.078.058-.175.082-.469.115-1.107.428-1.541.754-1.612 1.213-2.248 3.287-1.582 5.165a4.652 4.652 0 0 0 2.4 2.63c.173.082.356.159.407.171.05.013.091.035.091.05 0 .048-.635.562-.966.783a6.468 6.468 0 0 1-6.156.583 6.605 6.605 0 0 1-3.346-3.184c-1.575-3.235-.212-7.128 3.054-8.72a6.425 6.425 0 0 1 3.348-.629m6.369 2.978c.802.205 1.442.58 1.981 1.162 1.592 1.72 1.343 4.392-.543 5.826-.307.233-.953.545-1.357.655-.31.085-.38.091-.984.091-.603 0-.673-.006-.983-.091-.408-.111-1.051-.422-1.366-.662a4.026 4.026 0 0 1-1.553-2.627 4.48 4.48 0 0 1 .082-1.476 4.006 4.006 0 0 1 3.295-2.957 4.755 4.755 0 0 1 1.428.079" fill="#010101"/></svg>
SVG;

        $icon = 'data:image/svg+xml;base64,' . base64_encode($iconSvg);

        add_menu_page(
            'PagBank Connect', // Page title
            'PagBank', // Menu title
            'manage_woocommerce', // Required capability to view the menu
            'rm-pagbank', // Page slug
            [MenuPagBank::class, 'defaultPagBankMenuAction'], // Function that renders the menu page
            $icon, // Menu icon
            56.1 // Position in the menu
        );
    }
    
    public static function defaultPagBankMenuAction()
    {
        // Redirects to the plugin settings page
        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=rm-pagbank'));
        exit;
    }
    
    public static function addPagBankSubmenuItems()
    {
        add_submenu_page(
            'rm-pagbank', // Slug of the parent menu page
            __('Configurações', 'pagbank-connect'), // Page title
            __('Configurações', 'pagbank-connect'), // Submenu title
            'manage_woocommerce', // Required capability to view the submenu
            'rm-pagbank', // Submenu page slug
            [MenuPagBank::class, 'addPagBankMenu'] // Function that renders the submenu page
        );
        add_submenu_page(
            'rm-pagbank',
            __('Assinaturas', 'pagbank-connect'),
            __('Assinaturas', 'pagbank-connect'),
            'manage_woocommerce',
            'rm-pagbank-subscriptions',
            [MenuPagBank::class, 'renderPagbankSubscriptionsListPage']
        );

        add_submenu_page(
            null, /// null so it doesn't appear in the menu
            'Visualizar Assinatura', // Page title
            'Visualizar Assinatura', // Submenu title
            'manage_woocommerce', // Required capability to view the submenu
            'rm-pagbank-subscriptions-view', // Submenu page slug
            [MenuPagBank::class, 'renderPagbankSubscriptionViewPage'] // Function that renders the submenu page
        );
    }
    
    public static function renderPagbankSubscriptionsListPage()
    {
        $subscriptionsListTable = new SubscriptionList();
        $subscriptionsListTable->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Assinaturas PagBank', 'pagbank-connect')) . '</h1>';
        $subscriptionsListTable->display();
        echo '</div>';
    }
    
    public static function adminPagesStyle()
    {
        // Register an empty style sheet
        wp_register_style('rm-pagbank-admin', false);
        wp_enqueue_style('rm-pagbank-admin');
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Add custom CSS styles
        $custom_css = '
        .wp-list-table .column-id {
            width: 5%;
        }
        .wp-list-table .column-initial_order_id {
            width: 10%;
        }
        .wp-list-table .column-recurring_amount {
            width: 10%;
        }
    ';
        wp_add_inline_style('rm-pagbank-admin', $custom_css);
    }

    public static function renderPagbankSubscriptionViewPage(){
        // Check if the subscription ID was passed
        if (!isset($_GET['id'])) { //phpcs:ignore WordPress.Security.NonceVerification
            echo '<h1>' . esc_html( __('ID da assinatura não fornecido', 'pagbank-connect') ) . '</h1>';
            return;
        }

        // Get the subscription ID
        $subscriptionId = intval($_GET['id']);

        // Get the subscription from the database
        global $wpdb;
        $subscription = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE id = %d", $subscriptionId));

        // Check if the subscription exists
        if (!$subscription) {
            echo '<h1>' . esc_html( __('Assinatura não encontrada', 'pagbank-connect') ). '</h1>';
            return;
        }

        // Get the IDs of the previous and next subscriptions
        $prevSubscriptionId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pagbank_recurring WHERE id < %d ORDER BY id DESC LIMIT 1", $subscriptionId));
        $nextSubscriptionId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pagbank_recurring WHERE id > %d ORDER BY id ASC LIMIT 1", $subscriptionId));

        // Render the subscription view page
        echo '<div class="wrap">';
        echo '<h1>' . esc_html( __('Visualizar Assinatura', 'pagbank-connect') ). '</h1>';

        echo '<a href="?page=rm-pagbank-subscriptions" class="button">' . esc_html( __('Voltar para a listagem de assinaturas', 'rm-pagbank') ) . '</a>';
        
        // Add the "View next subscription" and "View previous subscription" buttons
        if ($prevSubscriptionId) {
            echo '<a href="?page=rm-pagbank-subscriptions-view&id=' . intval($prevSubscriptionId) . '" class="button">' . esc_html( __('Ver Assinatura Anterior', 'pagbank-connect') ) . '</a> ';
        }
        if ($nextSubscriptionId) {
            echo '<a href="?page=rm-pagbank-subscriptions-view&id=' . intval($nextSubscriptionId) . '" class="button">' . esc_html( __('Ver Próxima Assinatura', 'pagbank-connect') ) . '</a>';
        }

        echo '<h2>' . esc_html( __('Detalhes da Assinatura', 'pagbank-connect') ) . '</h2>';
        $subscriptionDetailsListTable = new SubscriptionDetails($subscription);
        $subscriptionDetailsListTable->prepare_items();
        $subscriptionDetailsListTable->display();


        echo '<h2>' . esc_html( __('Ações', 'pagbank-connect') ). '</h2>';
        do_action( 'rm_pagbank_view_subscription_actions', $subscription );

        echo '<h2>' . esc_html( __('Pedidos Associados', 'pagbank-connect') ) . '</h2>';
        $ordersListTable = new OrdersList($subscription);
        $ordersListTable->prepare_items();
        $ordersListTable->display();
        echo '</div>';
    }
    
}