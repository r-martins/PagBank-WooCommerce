<?php

namespace RM_PagBank\Connect\Recurring\Admin\Subscriptions;

class SubscriptionReportingSummary
{

    public function basic()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pagbank_recurring';
        $date_30_days = date('Y-m-d H:i:s', strtotime('-30 days'));

        $query = $wpdb->prepare(
            "SELECT
                    SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS news,
                    SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS enables,
                    SUM(CASE WHEN status = 'PENDING_CANCEL' AND updated_at >= %s THEN 1 ELSE 0 END) AS pending_cancel,
                    SUM(CASE WHEN status = 'PAUSED' AND updated_at >= %s THEN 1 ELSE 0 END) AS pause,
                    SUM(CASE WHEN status = 'CANCELED' AND updated_at >= %s THEN 1 ELSE 0 END) AS canceleds
            FROM $table",
            $date_30_days,
            $date_30_days,
            $date_30_days,
            $date_30_days
        );

        $result = $wpdb->get_row($query);

        return [
            "enables"   => (int) $result->enables ?? 0,
            "news"      => (int) $result->news ?? 0,
            "canceleds" => (int) $result->canceleds ?? 0,
            "pending_cancel"  => (int) $result->pending_cancel ?? 0,
            "pause"     => (int) $result->pause ?? 0,
        ];
    }

    public static function styleReportingBasic()
    {
        // Add the CSS for the reporting
        wp_enqueue_style(
            'rm-pagbank-admin-subscription-reporting-basic', 
            plugins_url('public/css/admin/subscription-reporting-basic.css', WC_PAGSEGURO_CONNECT_PLUGIN_FILE),
            false, 
            WC_PAGSEGURO_CONNECT_VERSION
        );
    }
    
    public function renderPagbankReportingBasic()
    {
        $data = $this->basic(); 
        $this->styleReportingBasic(); ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Relatórios de Assinaturas - PagBank', 'rm-pagbank')); ?></h1>
            <div class="report-cards">
                <div class="report-card">
                    <strong><?php echo esc_html(__('Assinaturas Ativas', 'rm-pagbank')); ?></strong>
                    <div class="value"><?php echo esc_html($data['enables'] ?? 0); ?></div>
                </div>
                <div class="report-card">
                    <strong><?php echo esc_html(__('Novas (últimos 30 dias)', 'rm-pagbank')); ?></strong>
                    <div class="value"><?php echo esc_html($data['news'] ?? 0); ?></div>
                </div>
                <div class="report-card">
                    <strong><?php echo esc_html(__('Pausada (últimos 30 dias)', 'rm-pagbank')); ?></strong>
                    <div class="value"><?php echo esc_html($data['pause'] ?? 0); ?></div>
                </div>
                <div class="report-card">
                    <strong><?php echo esc_html(__('Cancelamento Pendente (últimos 30 dias)', 'rm-pagbank')); ?></strong>
                    <div class="value"><?php echo esc_html($data['pending_cancel'] ?? 0); ?></div>
                </div>
                <div class="report-card">
                    <strong><?php echo esc_html(__('Canceladas (últimos 30 dias)', 'rm-pagbank')); ?></strong>
                    <div class="value"><?php echo esc_html($data['canceleds'] ?? 0); ?></div>
                </div>
            </div>
        </div>
<?php
    }
}