<?php

namespace RM_PagBank\Connect\Recurring\Admin\Reports\Block;

use WC_Admin_Report;

class RecurringsReport extends WC_Admin_Report
{
    public static function output()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pagbank_recurring';

        // Filter data
        $current_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '30';
        $date_filter = gmdate('Y-m-d H:i:s', strtotime("-{$current_range} days"));
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : null;
        
        // Pagination
        $per_page = isset($_GET['per_page']) ? sanitize_text_field($_GET['per_page']) : '12';
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * ($per_page === 'all' ? 0 : intval($per_page));
        // Build summary query - Cards show statistics for the selected period, regardless of status filter
        $summary = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS news,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'PAUSED' AND paused_at >= %s THEN 1 ELSE 0 END) AS paused,
                SUM(CASE WHEN status = 'PENDING_CANCEL' AND canceled_at >= %s THEN 1 ELSE 0 END) AS pending_cancel,
                SUM(CASE WHEN status = 'CANCELED' AND canceled_at >= %s THEN 1 ELSE 0 END) AS canceled,
                SUM(CASE WHEN status = 'ACTIVE' THEN recurring_amount ELSE 0 END) AS active_revenue,
                AVG(CASE WHEN status = 'ACTIVE' THEN recurring_amount ELSE NULL END) AS avg_ticket
            FROM {$table}
        ", $date_filter, $date_filter, $date_filter, $date_filter));

        // Data to graph month
        $monthly_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m') as month,
                COUNT(*) as total_subscriptions,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'CANCELED' THEN 1 ELSE 0 END) as canceled_count,
                SUM(recurring_amount) as total_revenue
            FROM {$table}
            WHERE created_at >= %s
            GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
            ORDER BY month DESC
            LIMIT 12
        ", gmdate('Y-m-d H:i:s', strtotime('-12 months'))));

        // Top products recurring
        $top_products = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.post_title as product_name,
                COUNT(r.id) as subscription_count,
                SUM(r.recurring_amount) as total_revenue,
                AVG(r.recurring_amount) as avg_amount
            FROM {$table} r
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON r.initial_order_id = oi.order_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            LEFT JOIN {$wpdb->posts} p ON oim.meta_value = p.ID
            WHERE r.status = 'ACTIVE' 
            AND oim.meta_key = '_product_id'
            AND r.created_at >= %s
            GROUP BY p.ID
            ORDER BY subscription_count DESC
            LIMIT 10
        ", $date_filter));

        // Detect HPOS
        $is_hpos = class_exists('WC_Order_Storage') && method_exists('WC_Order_Storage', 'get_order_type') && wc_get_container()->get('order.store')::class === 'Automattic\WooCommerce\Internal\Order\Storage';

        if ($is_hpos) {
            // HPOS: fetch data from wp_wc_orders and wp_wc_order_addresses
            $orders_sql = "SELECT 
                r.*,
                o.date_created_gmt as post_date,
                oa.email as customer_email,
                oa.first_name as billing_first_name,
                oa.last_name as billing_last_name,
                o.total as order_total
            FROM {$table} r
            LEFT JOIN {$wpdb->prefix}wc_orders o ON r.initial_order_id = o.id
            LEFT JOIN {$wpdb->prefix}wc_order_addresses oa ON o.id = oa.order_id AND oa.address_type = 'billing'
            WHERE 1=1";
            
            $orders_params = array();
            
            if (!is_null($status_filter) && !empty($status_filter)) {
                $orders_sql .= " AND r.status = %s";
                $orders_params[] = $status_filter;
                
                // Filter by the correct date field based on status
                switch ($status_filter) {
                    case 'CANCELED':
                        $orders_sql .= " AND r.canceled_at >= %s";
                        break;
                    case 'PAUSED':
                        $orders_sql .= " AND r.paused_at >= %s";
                        break;
                    case 'PENDING_CANCEL':
                        $orders_sql .= " AND r.canceled_at >= %s";
                        break;
                    default:
                        // For ACTIVE, PENDING, SUSPENDED, etc., use created_at
                        $orders_sql .= " AND r.created_at >= %s";
                        break;
                }
                $orders_params[] = $date_filter;
            } else {
                // No status filter, just filter by creation date
                $orders_sql .= " AND r.created_at >= %s";
                $orders_params[] = $date_filter;
            }
            
            // Count total records for pagination (sem JOINs, só filtros)
            $count_sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
            $count_params = array();
            if (!is_null($status_filter) && !empty($status_filter)) {
                $count_sql .= " AND status = %s";
                $count_params[] = $status_filter;
                switch ($status_filter) {
                    case 'CANCELED':
                        $count_sql .= " AND canceled_at >= %s";
                        break;
                    case 'PAUSED':
                        $count_sql .= " AND paused_at >= %s";
                        break;
                    case 'PENDING_CANCEL':
                        $count_sql .= " AND canceled_at >= %s";
                        break;
                    default:
                        $count_sql .= " AND created_at >= %s";
                        break;
                }
                $count_params[] = $date_filter;
            } else {
                $count_sql .= " AND created_at >= %s";
                $count_params[] = $date_filter;
            }
            $total_records = $wpdb->get_var($wpdb->prepare($count_sql, $count_params));

            $orders_sql .= " ORDER BY r.created_at DESC";

            // Add pagination
            if ($per_page !== 'all') {
                $orders_sql .= " LIMIT %d OFFSET %d";
                $orders_params[] = intval($per_page);
                $orders_params[] = $offset;
            }

            $orders = $wpdb->get_results($wpdb->prepare($orders_sql, $orders_params));
        } else {
            // Classic: fetch data from wp_posts and wp_postmeta
            $sql = "SELECT 
                r.*,
                p.post_date,
                pm.meta_value as customer_email,
                pm2.meta_value as billing_first_name,
                pm3.meta_value as billing_last_name,
                pm4.meta_value as order_total
            FROM {$table} r
            LEFT JOIN {$wpdb->posts} p ON r.initial_order_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm ON r.initial_order_id = pm.post_id AND pm.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm2 ON r.initial_order_id = pm2.post_id AND pm2.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm3 ON r.initial_order_id = pm3.post_id AND pm3.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} pm4 ON r.initial_order_id = pm4.post_id AND pm4.meta_key = '_order_total'
            WHERE 1=1";
            
            $orders_params = array();
            
            if (!is_null($status_filter) && !empty($status_filter)) {
                $sql .= " AND r.status = %s";
                $orders_params[] = $status_filter;
                
                // Filter by the correct date field based on status
                switch ($status_filter) {
                    case 'CANCELED':
                        $sql .= " AND r.canceled_at >= %s";
                        break;
                    case 'PAUSED':
                        $sql .= " AND r.paused_at >= %s";
                        break;
                    case 'PENDING_CANCEL':
                        $sql .= " AND r.canceled_at >= %s";
                        break;
                    default:
                        // For ACTIVE, PENDING, SUSPENDED, etc., use created_at
                        $sql .= " AND r.created_at >= %s";
                        break;
                }
                $orders_params[] = $date_filter;
            } else {
                // No status filter, just filter by creation date
                $sql .= " AND r.created_at >= %s";
                $orders_params[] = $date_filter;
            }
            
            // Count total records for pagination (sem JOINs, só filtros)
            $count_sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
            $count_params = array();
            if (!is_null($status_filter) && !empty($status_filter)) {
                $count_sql .= " AND status = %s";
                $count_params[] = $status_filter;
                switch ($status_filter) {
                    case 'CANCELED':
                        $count_sql .= " AND canceled_at >= %s";
                        break;
                    case 'PAUSED':
                        $count_sql .= " AND paused_at >= %s";
                        break;
                    case 'PENDING_CANCEL':
                        $count_sql .= " AND canceled_at >= %s";
                        break;
                    default:
                        $count_sql .= " AND created_at >= %s";
                        break;
                }
                $count_params[] = $date_filter;
            } else {
                $count_sql .= " AND created_at >= %s";
                $count_params[] = $date_filter;
            }
            $total_records = $wpdb->get_var($wpdb->prepare($count_sql, $count_params));

            $sql .= " ORDER BY r.created_at DESC";

            // Add pagination
            if ($per_page !== 'all') {
                $sql .= " LIMIT %d OFFSET %d";
                $orders_params[] = intval($per_page);
                $orders_params[] = $offset;
            }

            $orders = $wpdb->get_results($wpdb->prepare($sql, $orders_params));
        }

        self::render_dashboard($summary, $monthly_data, $top_products, $orders, $current_range, $total_records, $per_page, $current_page);
    }

    protected static function render_dashboard($summary, $monthly_data, $top_products, $orders, $current_range, $total_records = 0, $per_page = '12', $current_page = 1)
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Relatórios - Assinaturas PagBank', 'pagbank-connect'); ?></h1>

            <!-- Report Cards -->
            <div class="rm-pagbank-report-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <?php
                self::render_card('Total de Assinaturas', $summary->total ?? 0, 'dashicons-admin-users');
                self::render_card('Ativas', $summary->active ?? 0, 'dashicons-yes-alt', '#46b450');
                self::render_card('Novas (' . $current_range . ' dias)', $summary->news ?? 0, 'dashicons-plus-alt', '#00a0d2', admin_url('admin.php?page=wc-reports&tab=pagbank&section&range='.$current_range.'&status_filter#rm-pagbank-subscriptions-table'));
                self::render_card('Pausadas (' . $current_range . ' dias)', $summary->paused ?? 0, 'dashicons-controls-pause', '#ffb900', admin_url('admin.php?page=wc-reports&tab=pagbank&section&range='.$current_range.'&status_filter=PAUSED#rm-pagbank-subscriptions-table'));
                self::render_card('Canceladas (' . $current_range . ' dias)', $summary->canceled ?? 0, 'dashicons-no-alt', '#dc3232', admin_url('admin.php?page=wc-reports&tab=pagbank&section&range='.$current_range.'&status_filter=CANCELED#rm-pagbank-subscriptions-table'));
                ?>
            </div>

            <!-- Revenue Cards -->
            <div class="rm-pagbank-revenue-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <?php
                self::render_revenue_card(
                    'Receita Recorrente Ativa',
                    'R$ ' . number_format($summary->active_revenue ?? 0, 2, ',', '.'),
                    'Valor total das assinaturas ativas'
                );
                self::render_revenue_card(
                    'Ticket Médio',
                    'R$ ' . number_format($summary->avg_ticket ?? 0, 2, ',', '.'),
                    'Valor médio por assinatura'
                );
                ?>
            </div>

            <!-- Filters-->
            <div class="rm-pagbank-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
                <form method="get" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                    <input type="hidden" name="tab" value="<?php echo esc_attr($_GET['tab'] ?? ''); ?>" />
                    <input type="hidden" name="section" value="<?php echo esc_attr($_GET['section'] ?? ''); ?>" />

                    <div>
                        <label for="range"><?php esc_html_e('Período:', 'pagbank-connect'); ?></label>
                        <select name="range" id="range">
                            <option value="7" <?php selected($current_range, '7'); ?>>Últimos 7 dias</option>
                            <option value="30" <?php selected($current_range, '30'); ?>>Últimos 30 dias</option>
                            <option value="90" <?php selected($current_range, '90'); ?>>Últimos 90 dias</option>
                            <option value="365" <?php selected($current_range, '365'); ?>>Último ano</option>
                        </select>
                    </div>

                    <div>
                        <label for="status_filter"><?php esc_html_e('Status:', 'pagbank-connect'); ?></label>
                        <select name="status_filter" id="status_filter">
                            <option value=""><?php esc_html_e('Todos', 'pagbank-connect'); ?></option>
                            <option value="ACTIVE" <?php selected($_GET['status_filter'] ?? '', 'ACTIVE'); ?>>Ativas</option>
                            <option value="PAUSED" <?php selected($_GET['status_filter'] ?? '', 'PAUSED'); ?>>Pausadas</option>
                            <option value="CANCELED" <?php selected($_GET['status_filter'] ?? '', 'CANCELED'); ?>>Canceladas</option>
                            <option value="SUSPENDED" <?php selected($_GET['status_filter'] ?? '', 'SUSPENDED'); ?>>Suspensa</option>
                            <option value="PENDING" <?php selected($_GET['status_filter'] ?? '', 'PENDING'); ?>>Pendente</option>
                            <option value="PENDING_CANCEL" <?php selected($_GET['status_filter'] ?? '', 'PENDING_CANCEL'); ?>>Cancelamento Pendente</option>
                        </select>
                    </div>

                    <div>
                        <label for="per_page"><?php esc_html_e('Exibir:', 'pagbank-connect'); ?></label>
                        <select name="per_page" id="per_page">
                            <option value="12" <?php selected($per_page, '12'); ?>>12 registros</option>
                            <option value="24" <?php selected($per_page, '24'); ?>>24 registros</option>
                            <option value="32" <?php selected($per_page, '32'); ?>>32 registros</option>
                            <option value="all" <?php selected($per_page, 'all'); ?>>Todos</option>
                        </select>
                    </div>

                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Filtrar', 'pagbank-connect'); ?>
                    </button>
                </form>

            </div>
            
            <!-- Table Recurrings -->
            <div id="rm-pagbank-subscriptions-table" class="rm-pagbank-subscriptions-table" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                <h3><?php esc_html_e('Assinaturas Recentes', 'pagbank-connect'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Pedido', 'pagbank-connect'); ?></th>
                            <th><?php esc_html_e('Assinatura ID', 'pagbank-connect'); ?></th>
                            <th><?php esc_html_e('Cliente', 'pagbank-connect'); ?></th>
                            <th><?php esc_html_e('Status', 'pagbank-connect'); ?></th>
                            <th><?php esc_html_e('Valor', 'pagbank-connect'); ?></th>
                            <th><?php esc_html_e('Próxima Cobrança', 'pagbank-connect'); ?></th>
                            <th><?php esc_html_e('Data Criação', 'pagbank-connect'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $row->initial_order_id . '&action=edit')); ?>">
                                        <strong>#<?php echo esc_html($row->initial_order_id); ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($row->id)): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=rm-pagbank-subscriptions-view&action=view&id=' . $row->id)); ?>">
                                            <code><?php echo esc_html($row->id); ?></code>
                                        </a>
                                    <?php else: ?>
                                        <code>-</code>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    // Try names from query first
                                    $customer_name = trim(($row->billing_first_name ?? '') . ' ' . ($row->billing_last_name ?? ''));
                                    $customer_email = $row->customer_email ?? '';

                                    // Fallback: resolve from WooCommerce order if missing
                                    if (($customer_name === '' && $customer_email === '') || ($customer_name === '')) {
                                        $order_id = isset($row->initial_order_id) ? intval($row->initial_order_id) : 0;
                                        if ($order_id > 0) {
                                            $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
                                            if ($order) {
                                                $first_name = method_exists($order, 'get_billing_first_name') ? (string) $order->get_billing_first_name() : '';
                                                $last_name  = method_exists($order, 'get_billing_last_name') ? (string) $order->get_billing_last_name() : '';
                                                $email      = method_exists($order, 'get_billing_email') ? (string) $order->get_billing_email() : '';

                                                if ($customer_name === '') {
                                                    $resolved_name = trim($first_name . ' ' . $last_name);
                                                    if ($resolved_name !== '') {
                                                        $customer_name = $resolved_name;
                                                    }
                                                }

                                                if ($customer_email === '' && $email !== '') {
                                                    $customer_email = $email;
                                                }
                                            }
                                        }
                                    }

                                    if ($customer_name !== '') {
                                        echo esc_html($customer_name);
                                        if ($customer_email !== '') {
                                            echo '<br><small>' . esc_html($customer_email) . '</small>';
                                        }
                                    } elseif ($customer_email !== '') {
                                        echo esc_html($customer_email);
                                    } else {
                                        echo esc_html('Cliente não identificado');
                                    }
                                    ?>
                                </td>
                                <td><?php echo self::render_status_badge($row->status ?? 'UNKNOWN'); ?></td>
                                <td><strong>R$ <?php echo number_format($row->recurring_amount ?? 0, 2, ',', '.'); ?></strong></td>
                                <td>
                                    <?php
                                    if ($row->next_bill_at && $row->next_bill_at !== '0000-00-00 00:00:00') {
                                        echo esc_html(date('d/m/Y', strtotime($row->next_bill_at)));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html(date('d/m/Y H:i', strtotime($row->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php 
                // Only show pagination if we have more records than what fits on one page
                // AND we actually have results to show
                if ($per_page !== 'all' && $total_records > intval($per_page) && !empty($orders)): ?>
                    <?php
                    $total_pages = ceil($total_records / intval($per_page));
                    // Only show pagination controls if there are actually multiple pages worth of data
                    if ($total_pages > 1):
                    ?>
                        <div class="rm-pagbank-pagination" style="margin-top: 20px; text-align: center;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <span style="color: #666;">
                                    <?php 
                                    $showing_start = (($current_page - 1) * intval($per_page)) + 1;
                                    $showing_end = min($current_page * intval($per_page), $total_records);
                                    printf(
                                        esc_html__('Exibindo %d-%d de %d registros', 'pagbank-connect'),
                                        $showing_start,
                                        $showing_end,
                                        $total_records
                                    ); 
                                    ?>
                                </span>
                                <span style="color: #666;">
                                    <?php printf(esc_html__('Página %d de %d', 'pagbank-connect'), $current_page, $total_pages); ?>
                                </span>
                            </div>
                            
                            <div class="pagination-links" style="display: flex; justify-content: center; gap: 5px;">
                                <?php
                                $current_url = remove_query_arg('paged');
                                
                                // Previous page
                                if ($current_page > 1): ?>
                                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $current_url)); ?>" 
                                       class="button" style="margin: 0 2px;">‹ <?php esc_html_e('Anterior', 'pagbank-connect'); ?></a>
                                <?php endif; ?>
                                
                                <?php
                                // Page numbers
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                if ($start_page > 1): ?>
                                    <a href="<?php echo esc_url(add_query_arg('paged', 1, $current_url)); ?>" 
                                       class="button" style="margin: 0 2px;">1</a>
                                    <?php if ($start_page > 2): ?>
                                        <span style="margin: 0 5px;">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <?php if ($i == $current_page): ?>
                                        <span class="button button-primary" style="margin: 0 2px;"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $i, $current_url)); ?>" 
                                           class="button" style="margin: 0 2px;"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <span style="margin: 0 5px;">...</span>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(add_query_arg('paged', $total_pages, $current_url)); ?>" 
                                       class="button" style="margin: 0 2px;"><?php echo $total_pages; ?></a>
                                <?php endif; ?>
                                
                                <?php
                                // Next page
                                if ($current_page < $total_pages): ?>
                                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $current_url)); ?>" 
                                       class="button" style="margin: 0 2px;"><?php esc_html_e('Próxima', 'pagbank-connect'); ?> ›</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>


            <!-- Top Products -->
            <?php if (!empty($top_products)): ?>
                <div class="rm-pagbank-top-products" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 30px;">
                    <h3><?php esc_html_e('Produtos Mais Assinados', 'pagbank-connect'); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Produto', 'pagbank-connect'); ?></th>
                                <th><?php esc_html_e('Assinaturas', 'pagbank-connect'); ?></th>
                                <th><?php esc_html_e('Receita Total', 'pagbank-connect'); ?></th>
                                <th><?php esc_html_e('Valor Médio', 'pagbank-connect'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($product->product_name ?: 'Produto não encontrado'); ?></strong></td>
                                    <td><?php echo intval($product->subscription_count); ?></td>
                                    <td>R$ <?php echo number_format($product->total_revenue, 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($product->avg_amount, 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .rm-pagbank-report-cards .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                text-align: center;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .rm-pagbank-report-cards .card.onclick {
                transition: box-shadow 0.2s, border-color 0.2s, transform 0.2s;
                cursor: pointer !important;
                position: relative;
            }
            .rm-pagbank-report-cards .card.onclick:hover {
                border-color: #0295f8ff;
                box-shadow: 0 4px 16px rgba(6, 160, 244, 0.12);
                transform: translateY(-2px) scale(1.01);
            }
            .rm-pagbank-report-cards .card.onclick::after {
                content: '';
                position: absolute;
                top: 12px;
                right: 12px;
                width: 18px;
                height: 18px;
                background: url('data:image/svg+xml;utf8,<svg fill="none" stroke="%230295f8" stroke-width="2" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M12 5l7 7-7 7"/></svg>') no-repeat center center;
                opacity: 0.7;
                pointer-events: none;
            }

            .rm-pagbank-report-cards .card .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                margin-bottom: 10px;
            }

            .rm-pagbank-report-cards .card .number {
                font-size: 32px;
                font-weight: bold;
                margin: 10px 0;
            }

            .rm-pagbank-report-cards .card .label {
                font-size: 14px;
                color: #666;
                font-weight: 600;
            }

            .status-badge {
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }

            .status-active {
                background: #46b450;
                color: white;
            }

            .status-paused {
                background: #ffb900;
                color: white;
            }

            .status-canceled {
                background: #dc3232;
                color: white;
            }

            .status-pending-cancel {
                background: #f56e28;
                color: white;
            }

            .rm-pagbank-pagination .pagination-links .button {
                min-width: 40px;
                text-align: center;
                text-decoration: none;
                border-radius: 3px;
                padding: 6px 12px;
            }

            .rm-pagbank-pagination .pagination-links .button:hover:not(.button-primary) {
                background: #f0f0f1;
                border-color: #0073aa;
                color: #0073aa;
            }

            .rm-pagbank-pagination .pagination-links .button.button-primary {
                cursor: default;
            }
        </style>
    <?php
    }

    protected static function render_card($label, $value, $icon = 'dashicons-chart-bar', $color = '#0073aa', $href = null, $target = '_self')
    {
    ?>
        <div class="card <?php echo null !== $href ? 'onclick' : '' ?>" <?php echo $href !== null ? 'onclick="window.location.href=\'' . esc_url($href) . '\'"' : ''; ?>>
            <div class="dashicons <?php echo esc_attr($icon); ?>" style="color: <?php echo esc_attr($color); ?>;"></div>
            <div class="number" style="color: <?php echo esc_attr($color); ?>;"><?php echo intval($value); ?></div>
            <div class="label"><?php echo esc_html($label); ?></div>
        </div>
    <?php
    }

    protected static function render_revenue_card($title, $value, $description)
    {
    ?>
        <div class="card" style="text-align: left;">
            <h4 style="margin: 0 0 10px 0; color: #23282d;"><?php echo esc_html($title); ?></h4>
            <div style="font-size: 24px; font-weight: bold; color: #46b450; margin: 10px 0;"><?php echo esc_html($value); ?></div>
            <p style="margin: 0; color: #666; font-size: 13px;"><?php echo esc_html($description); ?></p>
        </div>
    <?php
    }

    protected static function render_status_badge($status)
    {
        $status_map = [
            'ACTIVE' => ['label' => 'Ativa', 'class' => 'status-active'],
            'PAUSED' => ['label' => 'Pausada', 'class' => 'status-paused'],
            'CANCELED' => ['label' => 'Cancelada', 'class' => 'status-canceled'],
            'PENDING_CANCEL' => ['label' => 'Pend. Cancel.', 'class' => 'status-pending-cancel'],
        ];

        $status_info = $status_map[$status] ?? ['label' => $status, 'class' => 'status-unknown'];

        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($status_info['class']),
            esc_html($status_info['label'])
        );
    }
}
