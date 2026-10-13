<?php
namespace RM_PagBank\Connect;

use RM_PagBank\Connect\Recurring\Admin\Subscriptions\Details\OrdersList;
use RM_PagBank\Connect\Recurring\Admin\Subscriptions\SubscriptionDetails;
use RM_PagBank\Connect\Recurring\Admin\Subscriptions\SubscriptionEdit;
use RM_PagBank\Connect\Recurring\Admin\Subscriptions\SubscriptionList;
use RM_PagBank\Connect\Recurring\Admin\Subscriptions\SubscriptionReportingSummary;

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
        // WP admin menu: data-URI base64 SVG with fill="#010101" (recolored by color scheme).
        $iconSvg = '';
        $iconFile = WC_PAGSEGURO_CONNECT_BASE_DIR . '/public/images/pbicon.svg';
        if (is_readable($iconFile)) {
            $iconSvg = self::prepareMenuIconSvg((string) file_get_contents($iconFile));
        }

        $icon = $iconSvg !== ''
            ? 'data:image/svg+xml;base64,' . base64_encode($iconSvg)
            : 'dashicons-money-alt';

        add_menu_page(
            'PB Integrações', // Page title
            'PB Integrações', // Menu title
            'manage_woocommerce', // Required capability to view the menu
            'rm-pagbank', // Page slug
            [MenuPagBank::class, 'defaultPagBankMenuAction'], // Function that renders the menu page
            $icon, // Menu icon
            56.1 // Position in the menu
        );
    }

    /**
     * Affinity often exports mask+PNG and multi-color fills; WP menu needs a silhouette SVG.
     * Opaque pixels (any fill) become the admin color — white/"empty" fills are NOT holes.
     * Cutouts must be real transparency (no path, or fill none / evenodd holes).
     */
    private static function prepareMenuIconSvg(string $svg): string
    {
        $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg) ?? $svg;
        // Empty Affinity mask hides the icon entirely.
        $svg = preg_replace('/<defs>.*?<\/defs>/s', '', $svg) ?? $svg;
        $svg = preg_replace('/\s*mask="url\([^"]+\)"/', '', $svg) ?? $svg;

        // Drop "fake holes": Affinity white/near-white fills (opaque → would become solid ink in WP).
        $svg = preg_replace(
            '/<(path|rect|circle|ellipse|polygon|polyline)[^>]*?(?:fill\s*:\s*#(?:fff(?:fff)?|f{3,6}|e{3,6}|d{3,6})|fill="#(?:fff(?:fff)?|f{3,6}|e{3,6}|d{3,6})")[^>]*?\/?>/i',
            '',
            $svg
        ) ?? $svg;
        $svg = preg_replace(
            '/<(path|rect|circle|ellipse|polygon|polyline)[^>]*?(?:fill\s*:\s*#(?:fff(?:fff)?|f{3,6}|e{3,6}|d{3,6})|fill="#(?:fff(?:fff)?|f{3,6}|e{3,6}|d{3,6})")[^>]*?>.*?<\/\1>/is',
            '',
            $svg
        ) ?? $svg;

        // Keep fill:none / fill="none" (true transparency). Recolor only real ink.
        $svg = preg_replace_callback(
            '/fill\s*:\s*#([0-9a-fA-F]{3,8})/',
            static function (array $m): string {
                return self::isNearWhiteHex($m[1]) ? 'fill:none' : 'fill:#010101';
            },
            $svg
        ) ?? $svg;
        $svg = preg_replace_callback(
            '/fill="#([0-9a-fA-F]{3,8})"/',
            static function (array $m): string {
                return self::isNearWhiteHex($m[1]) ? 'fill="none"' : 'fill="#010101"';
            },
            $svg
        ) ?? $svg;

        $svg = preg_replace('/\bwidth="100%"/', 'width="20"', $svg) ?? $svg;
        $svg = preg_replace('/\bheight="100%"/', 'height="20"', $svg) ?? $svg;

        return trim($svg);
    }

    /** Treat light greys/whites as transparent (Affinity “empty” areas). */
    private static function isNearWhiteHex(string $hex): bool
    {
        $hex = strtolower($hex);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return false;
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return $r >= 0xcc && $g >= 0xcc && $b >= 0xcc;
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
            'rm-pagbank',
            __('Relatórios', 'pagbank-connect'),
            __('Relatórios', 'pagbank-connect'),
            'manage_woocommerce',
            'rm-pagbank-reports',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=wc-reports&tab=pagbank'));
                exit;
            }
        );
        
        add_submenu_page(
            'rm-pagbank',
            __('Embalagens', 'pagbank-connect'),
            __('Embalagens', 'pagbank-connect'),
            'manage_woocommerce',
            'rm-pagbank-boxes',
            [MenuPagBank::class, 'renderPagbankBoxesListPage']
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                'rm-pagbank',
                __('Logs', 'pagbank-connect'),
                __('Logs', 'pagbank-connect'),
                'manage_woocommerce',
                'rm-pagbank-logs',
                function () {
                    wp_safe_redirect(admin_url('admin.php?page=wc-status&tab=logs&source=pbintegracoes'));
                    exit;
                }
            );
        }

        add_submenu_page(
            'rm-pagbank-hidden', // parent_slug doesn't exist, so it doesn't appear in the menu
            'Visualizar Assinatura', // Page title
            'Visualizar Assinatura', // Submenu title
            'manage_woocommerce', // Required capability to view the submenu
            'rm-pagbank-subscriptions-view', // Submenu page slug
            [MenuPagBank::class, 'renderPagbankSubscriptionViewPage'] // Function that renders the submenu page
        );

        add_submenu_page(
            'rm-pagbank-hidden', // parent_slug doesn't exist, so it doesn't appear in the menu
            'Editar Assinatura', // Page title
            'Editar Assinatura', // Submenu title
            'manage_woocommerce', // Required capability to view the submenu
            'rm-pagbank-subscriptions-edit', // Submenu page slug
            [MenuPagBank::class, 'renderPagbankSubscriptionEditPage'] // Function that renders the submenu page
        );
        
        add_submenu_page(
            'rm-pagbank-hidden', // parent_slug doesn't exist, so it doesn't appear in the menu
            'Nova Caixa', // Page title
            'Nova Caixa', // Submenu title
            'manage_woocommerce', // Required capability to view the submenu
            'rm-pagbank-boxes-new', // Submenu page slug
            [MenuPagBank::class, 'renderPagbankBoxNewPage'] // Function that renders the submenu page
        );
        
        add_submenu_page(
            'rm-pagbank-hidden', // parent_slug doesn't exist, so it doesn't appear in the menu
            'Editar Caixa', // Page title
            'Editar Caixa', // Submenu title
            'manage_woocommerce', // Required capability to view the submenu
            'rm-pagbank-boxes-edit', // Submenu page slug
            [MenuPagBank::class, 'renderPagbankBoxEditPage'] // Function that renders the submenu page
        );
    }
    
    public static function renderPagbankSubscriptionsListPage()
    {
        $subscriptionsListTable = new SubscriptionList();
        $subscriptionsListTable->prepare_items();

        $SubscriptionReportingSummary = new SubscriptionReportingSummary();
        $SubscriptionReportingSummary->renderPagbankReportingBasic();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Assinaturas', 'pagbank-connect')) . '</h1>';
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
        
        // Enqueue boxes CSS for box management pages
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'rm-pagbank-boxes') !== false) {
            wp_enqueue_style(
                'rm-pagbank-boxes-admin',
                plugins_url('public/css/admin/boxes.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
                [],
                WC_PAGSEGURO_CONNECT_VERSION
            );
        }
        
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

        $paymentInfoAdmin = \RM_PagBank\Helpers\Recurring::normalizePaymentInfo($subscription->payment_info ?? null);
        if (!empty($paymentInfoAdmin['needs_retoken'])) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__(
                'Aguardando atualização de cartão pelo cliente (assinatura criada em outro processador / Connect Key atual incompatível).',
                'pagbank-connect'
            );
            echo '</p></div>';
        }

        echo '<h2>' . esc_html( __('Detalhes da Assinatura', 'pagbank-connect') ) . '</h2>';
        $subscriptionDetailsListTable = new SubscriptionDetails($subscription);
        $subscriptionDetailsListTable->prepare_items();
        $subscriptionDetailsListTable->display();


        echo '<h2>' . esc_html( __('Ações', 'pagbank-connect') ). '</h2>';
        ob_start();
        do_action( 'rm_pagbank_view_subscription_actions', $subscription );
        $actions_output = ob_get_clean();
        if ( empty( trim( $actions_output ) ) ) {
            echo '<p>' . esc_html( __('Não há ações disponíveis para esta assinatura.', 'pagbank-connect') ) . '</p>';
        } else {
            echo $actions_output;
        }

        echo '<h2>' . esc_html( __('Pedidos Associados', 'pagbank-connect') ) . '</h2>';
        $ordersListTable = new OrdersList($subscription);
        $ordersListTable->prepare_items();
        $ordersListTable->display();
        echo '</div>';
    }

    public static function renderPagbankSubscriptionEditPage(){
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

        // Render the subscription view page
        echo '<div class="wrap">';
        echo '<h1>' . esc_html( __('Editar Assinatura', 'pagbank-connect') ). '</h1>';

        echo '<a href="?page=rm-pagbank-subscriptions" class="button">' . esc_html( __('Voltar para a listagem de assinaturas', 'rm-pagbank') ) . '</a>';

        echo '<h2>' . esc_html( __('Detalhes da Assinatura', 'pagbank-connect') ) . '</h2>';
        $subscriptionDetailsListTable = new SubscriptionEdit($subscription);
        $subscriptionDetailsListTable->prepare_items();
        $subscriptionDetailsListTable->display();

        echo '</div>';
    }
    
    /**
     * render the PagBank boxes list page
     */
    public static function renderPagbankBoxesListPage()
    {
        // processing deletion of a single box
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_box_' . $_GET['id'])) {
                $box_manager = new \RM_PagBank\Connect\EnvioFacil\Box();
                $result = $box_manager->delete(intval($_GET['id']));
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . esc_html(__('Caixa excluída com sucesso!', 'pagbank-connect')) . '</p></div>';
                }
            }
        }
        
        $boxesListTable = new \RM_PagBank\Connect\EnvioFacil\BoxListTable();
        $boxesListTable->prepare_items();
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Embalagens Envio Fácil', 'pagbank-connect')) . '</h1>';
        
        // Button to add a new box
        echo '<a href="' . admin_url('admin.php?page=rm-pagbank-boxes-new') . '" class="page-title-action">';
        echo esc_html(__('Adicionar Nova Caixa', 'pagbank-connect'));
        echo '</a>';
        
        // Display the list table
        echo '<form method="post">';
        $boxesListTable->display();
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Renderiza a página de nova caixa
     */
    public static function renderPagbankBoxNewPage()
    {
        $box_manager = new \RM_PagBank\Connect\EnvioFacil\Box();

        // Process form submission
        if ($_POST && wp_verify_nonce($_POST['_wpnonce'], 'create_box')) {
            // If the field is not set, default to 0
            if (!isset($_POST['is_available'])) {
                $_POST['is_available'] = 0;
            }
            $result = $box_manager->create($_POST);
            
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html(__('Caixa criada com sucesso!', 'pagbank-connect')) . '</p></div>';
                // Redirect to the list
                wp_safe_redirect(admin_url('admin.php?page=rm-pagbank-boxes'));
                exit;
            }
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Nova Caixa', 'pagbank-connect')) . '</h1>';
        
        echo '<a href="' . admin_url('admin.php?page=rm-pagbank-boxes') . '" class="button">';
        echo esc_html(__('Voltar para Listagem', 'pagbank-connect'));
        echo '</a>';
        
        self::renderBoxForm();
        echo '</div>';
    }
    
    /**
     * Page to edit an existing box
     * @return void
     */
    public static function renderPagbankBoxEditPage()
    {
        if (!isset($_GET['id'])) {
            echo '<h1>' . esc_html(__('ID da caixa não fornecido', 'pagbank-connect')) . '</h1>';
            return;
        }
        
        $box_id = intval($_GET['id']);
        $box_manager = new \RM_PagBank\Connect\EnvioFacil\Box();
        $box = $box_manager->get_by_id($box_id);
        
        if (!$box) {
            echo '<h1>' . esc_html(__('Caixa não encontrada', 'pagbank-connect')) . '</h1>';
            return;
        }
        
        // Process form submission
        if ($_POST && wp_verify_nonce($_POST['_wpnonce'], 'edit_box')) {
            // If the field is not set, default to 0
            if (!isset($_POST['is_available'])) {
                $_POST['is_available'] = 0;
            }
            $result = $box_manager->update($box_id, $_POST);
            
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html(__('Caixa atualizada com sucesso!', 'pagbank-connect')) . '</p></div>';
                // Update box data
                $box = $box_manager->get_by_id($box_id);
            }
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(__('Editar Caixa', 'pagbank-connect')) . '</h1>';
        
        echo '<a href="' . admin_url('admin.php?page=rm-pagbank-boxes') . '" class="button">';
        echo esc_html(__('Voltar para Listagem', 'pagbank-connect'));
        echo '</a>';
        
        self::renderBoxForm($box);
        echo '</div>';
    }
    
    /**
     * Renders the box form, used for both creating and editing boxes.
     * @param object|null $box The box object when editing, or null when creating a
     */
    private static function renderBoxForm($box = null)
    {
        $is_edit = !is_null($box);
        $action = $is_edit ? 'edit_box' : 'create_box';
        $nonce = wp_create_nonce($action);
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field($action); ?>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="reference"><?php _e('Referência', 'pagbank-connect'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="reference" name="reference" value="<?php echo $is_edit ? esc_attr($box->reference) : ''; ?>" class="regular-text" required />
                            <p class="description"><?php _e('Identificador único da caixa (ex: CAIXA_PEQUENA_001)', 'pagbank-connect'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="is_available"><?php _e('Disponível', 'pagbank-connect'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_available" name="is_available" value="1" <?php checked($is_edit ? $box->is_available : 1, 1); ?> />
                                <?php _e('Esta caixa está disponível para uso', 'pagbank-connect'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row" colspan="2">
                            <h3><?php _e('Dimensões Externas (cm)', 'pagbank-connect'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="outer_width"><?php _e('Largura Externa', 'pagbank-connect'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="outer_width" name="outer_width" value="<?php echo $is_edit ? esc_attr($box->outer_width/10) : ''; ?>" class="small-text" min="10" max="100" step="0.1" required />
                            <span class="description">cm (mín: 10cm, máx: 100cm)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="outer_depth"><?php _e('Altura Externa', 'pagbank-connect'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="outer_depth" name="outer_depth" value="<?php echo $is_edit ? esc_attr($box->outer_depth/10) : ''; ?>" class="small-text" min="1" max="100" step="0.1" required />
                            <span class="description">cm (mín: 1cm, máx: 100cm)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="outer_length"><?php _e('Comprimento Externo', 'pagbank-connect'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="outer_length" name="outer_length" value="<?php echo $is_edit ? esc_attr($box->outer_length/10) : ''; ?>" class="small-text" min="15" max="100" step="0.1" required />
                            <span class="description">cm (mín: 15cm, máx: 100cm)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="thickness"><?php _e('Espessura', 'pagbank-connect'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="thickness" name="thickness" value="<?php echo $is_edit ? esc_attr($box->thickness/10) : '0.2'; ?>" class="small-text" min="0.1" step="0.1" required />
                            <span class="description">cm</span>
                            <p class="description"><?php _e('As dimensões internas serão calculadas automaticamente', 'pagbank-connect'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row" colspan="2">
                            <h3><?php _e('Peso (gramas)', 'pagbank-connect'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_weight"><?php _e('Peso Máximo', 'pagbank-connect'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="max_weight" name="max_weight" value="<?php echo $is_edit ? esc_attr($box->max_weight) : ''; ?>" class="small-text" min="300" max="10000" required />
                            <span class="description">g (mín: 300g, máx: 10kg)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="empty_weight"><?php _e('Peso da Caixa Vazia', 'pagbank-connect'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="empty_weight" name="empty_weight" value="<?php echo $is_edit ? esc_attr($box->empty_weight) : ''; ?>" class="small-text" min="1" max="9999" required />
                            <span class="description">g (deve ser menor que peso máximo)</span>
                        </td>
                    </tr>
                    

                </tbody>
            </table>
            
            <?php submit_button($is_edit ? __('Atualizar Caixa', 'pagbank-connect') : __('Criar Caixa', 'pagbank-connect')); ?>
        </form>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Real-time validation of empty weight vs max weight
            function validateWeights() {
                var maxWeight = parseInt($('#max_weight').val()) || 0;
                var emptyWeight = parseInt($('#empty_weight').val()) || 0;
                
                if (emptyWeight > 0 && maxWeight > 0 && emptyWeight >= maxWeight) {
                    $('#empty_weight').css('border-color', '#dc3232');
                    $('#empty_weight').next('.description').css('color', '#dc3232').text('<?php _e('Peso vazio deve ser menor que o peso máximo', 'pagbank-connect'); ?>');
                    return false;
                } else {
                    $('#empty_weight').css('border-color', '');
                    $('#empty_weight').next('.description').css('color', '').text('g (deve ser menor que peso máximo)');
                    return true;
                }
            }
            
            $('#max_weight, #empty_weight').on('input change', validateWeights);

            // Submissão normal; conversão cm->mm agora realizada no backend (Box::sanitize_data)
            $('form').on('submit', function(e) {
                if (!validateWeights()) {
                    e.preventDefault();
                    alert('<?php _e('Por favor, corrija os erros no formulário antes de continuar.', 'pagbank-connect'); ?>');
                    return false;
                }
            });
        });
        </script>
        <?php
    }
    
}