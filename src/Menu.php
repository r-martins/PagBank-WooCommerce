<?php
/** @TODO Organize classes in other files */
use RM_PagBank\Helpers\Recurring;

/**
 * PagBank Connect Admin Menu
 */

function add_custom_menu_item(){
    $iconSvg = <<<SVG
<svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" xml:space="preserve" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2"><path d="M9.172.034c-1.77.172-3.847 1-5.299 2.113a10.638 10.638 0 0 0-1.749 1.736C-.15 6.809-.632 10.677.858 14.033c.252.569.475.973.826 1.5.395.594.66.914 1.231 1.487 1.276 1.282 2.431 1.976 4.152 2.498 1.412.428 3.066.53 4.533.279a10.08 10.08 0 0 0 4.533-2.03 11.96 11.96 0 0 0 1.535-1.534c2.454-3.101 2.877-7.225 1.101-10.727-.492-.97-1.01-1.679-1.819-2.492-1.19-1.197-2.41-1.933-4.183-2.524-.966-.321-1.693-.451-2.65-.472-.349-.008-.774 0-.945.016m1.662.649a8 8 0 0 1 6.665 5.503c.273.85.351 1.394.351 2.437 0 .535-.013.853-.035.875-.023.024-.111-.034-.283-.185-.795-.7-1.747-1.08-2.848-1.135l-.416-.022-.158-.203a8.737 8.737 0 0 0-1.091-1.084 7.123 7.123 0 0 0-3.552-1.522c-.464-.066-1.378-.064-1.867.003a7.11 7.11 0 0 0-4.068 2.017c-.565.56-.896.998-1.294 1.714l-.199.357-.024-.224c-.042-.393.005-1.49.083-1.931A7.647 7.647 0 0 1 2.8 5.117a8.083 8.083 0 0 1 2.783-3.166 9.15 9.15 0 0 1 1.844-.897C8.066.842 8.618.73 9.4.655a9.27 9.27 0 0 1 1.434.028M9.067 5.934a6.42 6.42 0 0 1 1.892.446 6.339 6.339 0 0 1 1.504.846c.282.21 1.07.964 1.07 1.023 0 .021-.078.058-.175.082-.469.115-1.107.428-1.541.754-1.612 1.213-2.248 3.287-1.582 5.165a4.652 4.652 0 0 0 2.4 2.63c.173.082.356.159.407.171.05.013.091.035.091.05 0 .048-.635.562-.966.783a6.468 6.468 0 0 1-6.156.583 6.605 6.605 0 0 1-3.346-3.184c-1.575-3.235-.212-7.128 3.054-8.72a6.425 6.425 0 0 1 3.348-.629m6.369 2.978c.802.205 1.442.58 1.981 1.162 1.592 1.72 1.343 4.392-.543 5.826-.307.233-.953.545-1.357.655-.31.085-.38.091-.984.091-.603 0-.673-.006-.983-.091-.408-.111-1.051-.422-1.366-.662a4.026 4.026 0 0 1-1.553-2.627 4.48 4.48 0 0 1 .082-1.476 4.006 4.006 0 0 1 3.295-2.957 4.755 4.755 0 0 1 1.428.079" fill="#010101"/></svg>
SVG;
    
    $icon = 'data:image/svg+xml;base64,' . base64_encode($iconSvg);
    
    add_menu_page(
        'PagBank Connect', // Título da página
        'PagBank', // Título do menu
        'manage_options', // Capacidade necessária para ver o menu
        'rm-pagbank', // Slug da página
        'custom_menu_page_render', // Função que renderiza a página do menu
        $icon, // Ícone do menu
        56.1 // Posição no menu
    );
}

// Esta função será chamada quando o menu for clicado para renderizar a página
function custom_menu_page_render(){
    // Redireciona para a página de configurações do plugin
    wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=rm-pagbank'));
    exit;
}

add_action('admin_menu', 'add_custom_menu_item');
function add_custom_submenu_item(){
    add_submenu_page(
        'rm-pagbank', // Slug da página do menu pai
        'Configurações', // Título da página
        'Configurações', // Título do submenu
        'manage_options', // Capacidade necessária para ver o submenu
        'rm-pagbank', // Slug da página do submenu
        'custom_menu_page_render' // Função que renderiza a página do submenu
    );
    add_submenu_page(
        'rm-pagbank', // Slug da página do menu pai
        'Assinaturas', // Título da página
        'Assinaturas', // Título do submenu
        'manage_options', // Capacidade necessária para ver o submenu
        'rm-pagbank-subscriptions', // Slug da página do submenu
        'renderPagbankSubscriptionsListPage' // Função que renderiza a página do submenu
    );
}



if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Subscriptions_List extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Assinatura', 'rm-pagbank'),
            'plural'   => __('Assinaturas', 'rm-pagbank'),
            'ajax'     => false
        ]);
    }

    public function get_columns()
    {
        return [
            'id'                 => __('ID', 'rm-pagbank'),
            'initial_order_id'   => __('Pedido Inicial', 'rm-pagbank'),
            'recurring_amount'   => __('Valor Recorrente', 'rm-pagbank'),
            'status'             => __('Status', 'rm-pagbank'),
            'recurring_type'     => __('Tipo Recorrente', 'rm-pagbank'),
            'created_at'         => __('Criado em', 'rm-pagbank'),
            'updated_at'         => __('Atualizado em', 'rm-pagbank'),
            'next_bill_at'       => __('Próxima Cobrança', 'rm-pagbank'),
            'view'                 => __('Visualizar', 'rm-pagbank'),
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'created_at':
            case 'updated_at':
            case 'next_bill_at':
                return date_i18n(get_option('date_format'), strtotime($item[$column_name]));
            case 'recurring_type':
                $recHelper = new Recurring();
                return $recHelper->translateFrequency($item[$column_name]);
            case 'status':
                $recHelper = new Recurring();
                return $recHelper->getFriendlyStatus($item[$column_name]);
            default:
                return $item[$column_name];
        }
    }

    public function column_initial_order_id($item)
    {
        $order = wc_get_order($item['initial_order_id']);
        return '<a href="' . $order->get_edit_order_url() . '">' . $item['initial_order_id'] . '</a>';
    }

    public function column_view($item)
    {
        return sprintf('<a href="?page=%s&action=%s&id=%s">Visualizar</a>', 'rm-pagbank-subscriptions-view', 'view', $item['id']);
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns(), array(), $this->get_sortable_columns()];

        global $wpdb;
        $per_page = 10;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}pagbank_recurring");

        $orderby = (isset($_GET['orderby']) && in_array($_GET['orderby'], array_keys($this->get_sortable_columns()))) ? $_GET['orderby'] : 'id';
        $order = (isset($_GET['order']) && in_array($_GET['order'], array('asc', 'desc'))) ? $_GET['order'] : 'asc';

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pagbank_recurring ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, ($current_page - 1) * $per_page), ARRAY_A);
    }

    public function get_sortable_columns()
    {
        return array(
            'id' => array('id', false),
            'initial_order_id' => array('initial_order_id', false),
            'recurring_amount' => array('recurring_amount', false),
            'status' => array('status', false),
            'recurring_type' => array('recurring_type', false),
            'created_at' => array('created_at', false),
            'updated_at' => array('updated_at', false),
            'next_bill_at' => array('next_bill_at', false),
        );
    }
}

function renderPagbankSubscriptionsListPage()
{
    $subscriptionsListTable = new Subscriptions_List();
    $subscriptionsListTable->prepare_items();

    echo '<div class="wrap">';
    echo '<h1>' . __('Assinaturas', 'rm-pagbank') . '</h1>';
    $subscriptionsListTable->display();
    echo '</div>';
}

add_action('admin_menu', 'add_custom_submenu_item');

function rm_pagbank_admin_styles() {
    // Registra uma folha de estilo vazia
    wp_register_style('rm-pagbank-admin', false);
    wp_enqueue_style('rm-pagbank-admin');

    // Adiciona estilos CSS personalizados
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
add_action('admin_enqueue_scripts', 'rm_pagbank_admin_styles');


function add_viewsub_submenu_item(){
    // ...
    add_submenu_page(
        null, // null para que não apareça no menu
        'Visualizar Assinatura', // Título da página
        'Visualizar Assinatura', // Título do submenu
        'manage_options', // Capacidade necessária para ver o submenu
        'rm-pagbank-subscriptions-view', // Slug da página do submenu
        'renderPagbankSubscriptionViewPage' // Função que renderiza a página do submenu
    );
}


class Subscription_Details_List extends WP_List_Table
{
    private $subscription;

    public function display() {
        $singular = $this->_args['singular'];

        $this->display_tablenav( 'top' );

        $this->screen->render_screen_reader_content( 'heading_list' );
        ?>
        <table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
            <?php $this->print_table_description(); ?>
            <thead>
            <tr>
                <?php $this->print_column_headers(); ?>
            </tr>
            </thead>

            <tbody id="the-list"
                <?php
                if ( $singular ) {
                    echo " data-wp-lists='list:$singular'";
                }
                ?>
            >
            <?php $this->display_rows_or_placeholder(); ?>
            </tbody>
        </table>
        <?php
        $this->display_tablenav( 'bottom' );
    }
    
    public function __construct($subscription)
    {
        parent::__construct([
            'singular' => __('Detalhe', 'rm-pagbank'),
            'plural'   => __('Detalhes', 'rm-pagbank'),
            'ajax'     => false
        ]);

        $this->subscription = $subscription;
    }

    public function get_columns()
    {
        return [
            'name' => __('Nome', 'rm-pagbank'),
            'value' => __('Valor', 'rm-pagbank'),
        ];
    }

    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    public function column_value($item)
    {
        if ($item['name'] === 'Pedido Inicial') {
            $order = wc_get_order($item['value']);
            return '<a href="' . $order->get_edit_order_url() . '">' . $item['value'] . '</a>';
        }
        return $item['value'];
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns()];

        $recHelper = new Recurring();
        $status = $recHelper->getFriendlyStatus($this->subscription->status);
        $type = $recHelper->translateFrequency($this->subscription->recurring_type);

        $this->items = [
            ['name' => 'ID', 'value' => $this->subscription->id],
            ['name' => 'Pedido Inicial', 'value' => $this->subscription->initial_order_id],
            ['name' => 'Valor Recorrente', 'value' => $this->subscription->recurring_amount],
            ['name' => 'Status', 'value' => $status],
            ['name' => 'Tipo Recorrente', 'value' => $type],
            ['name' => 'Criado em', 'value' => date_i18n(get_option('date_format'), strtotime($this->subscription->created_at))],
            ['name' => 'Atualizado em', 'value' => date_i18n(get_option('date_format'), strtotime($this->subscription->updated_at))],
            ['name' => 'Próxima Cobrança', 'value' => date_i18n(get_option('date_format'), strtotime($this->subscription->next_bill_at))],
        ];
    }
}

class Orders_List extends WP_List_Table
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
        switch ($column_name) {
            case 'id':
                return '<a href="' . $item->get_edit_order_url() . '">' . $item->get_id() . '</a>';
            case 'date':
                return date_i18n(get_option('date_format'), strtotime($item->get_date_created()));
            default:
                return $item->get_data()[$column_name];
        }
    }

    public function prepare_items()
    {
        $this->_column_headers = [$this->get_columns()];

        $orders = wc_get_orders(['post_parent' => $this->subscription->initial_order_id]);
        $this->items = $orders;
    }
}


function renderPagbankSubscriptionViewPage(){
    // Verifica se o ID da assinatura foi passado
    if (!isset($_GET['id'])) {
        echo '<h1>' . __('ID da assinatura não fornecido', 'rm-pagbank') . '</h1>';
        return;
    }

    // Obtém o ID da assinatura
    $subscriptionId = intval($_GET['id']);

    // Obtém a assinatura do banco de dados
    global $wpdb;
    $subscription = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pagbank_recurring WHERE id = %d", $subscriptionId));

    // Verifica se a assinatura existe
    if (!$subscription) {
        echo '<h1>' . __('Assinatura não encontrada', 'rm-pagbank') . '</h1>';
        return;
    }

    // Obtém as IDs das assinaturas anterior e próxima
    $prevSubscriptionId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pagbank_recurring WHERE id < %d ORDER BY id DESC LIMIT 1", $subscriptionId));
    $nextSubscriptionId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pagbank_recurring WHERE id > %d ORDER BY id ASC LIMIT 1", $subscriptionId));

    // Renderiza a página de visualização de assinaturas
    echo '<div class="wrap">';
    echo '<h1>' . __('Visualizar Assinatura', 'rm-pagbank') . '</h1>';

    echo '<a href="?page=rm-pagbank-subscriptions" class="button">' . __('Voltar para a listagem de assinaturas', 'rm-pagbank') . '</a>';
    // Adiciona os botões de "Ver próxima assinatura" e "Ver assinatura anterior"
    if ($prevSubscriptionId) {
        echo '<a href="?page=rm-pagbank-subscriptions-view&id=' . $prevSubscriptionId . '" class="button">' . __('Ver Assinatura Anterior', 'rm-pagbank') . '</a> ';
    }
    if ($nextSubscriptionId) {
        echo '<a href="?page=rm-pagbank-subscriptions-view&id=' . $nextSubscriptionId . '" class="button">' . __('Ver Próxima Assinatura', 'rm-pagbank') . '</a>';
    }

    echo '<h2>' . __('Detalhes da Assinatura', 'rm-pagbank') . '</h2>';
    $subscriptionDetailsListTable = new Subscription_Details_List($subscription);
    $subscriptionDetailsListTable->prepare_items();
    $subscriptionDetailsListTable->display();


    echo '<h2>' . __('Ações', 'rm-pagbank') . '</h2>';
    do_action( 'rm_pagbank_view_subscription_actions', $subscription );

    echo '<h2>' . __('Pedidos Associados', 'rm-pagbank') . '</h2>';
    $ordersListTable = new Orders_List($subscription);
    $ordersListTable->prepare_items();
    $ordersListTable->display();
    echo '</div>';
}


add_action('admin_menu', 'add_viewsub_submenu_item');