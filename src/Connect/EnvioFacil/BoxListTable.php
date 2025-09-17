<?php

namespace RM_PagBank\Connect\EnvioFacil;

use WP_List_Table;

/**
 * Class BoxListTable
 *
 * Displays Envio Fácil boxes using WP_List_Table.
 * Developer commentary translated to English; user facing strings (i18n) preserved in Portuguese.
 *
 * Responsibilities:
 *  - Render list table (columns, sorting, pagination)
 *  - Handle bulk actions (delete / activate / deactivate)
 *  - Provide basic filters (availability)
 *
 * @author    Ricardo Martins
 * @copyright 2024 Magenteiro
 * @package   RM_PagBank\Connect\EnvioFacil
 */
class BoxListTable extends WP_List_Table
{
    private Box $box_manager;
    
    public function __construct()
    {
        parent::__construct([
            'singular' => 'caixa',
            'plural' => 'caixas',
            'ajax' => false
        ]);
        
        $this->box_manager = new Box();
    }
    
    /**
     * Define table columns (keys map to column_* methods or direct output).
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'reference' => __('Referência', 'pagbank-connect'),
            'dimensions' => __('Dimensões (mm)', 'pagbank-connect'),
            'weight' => __('Peso (g)', 'pagbank-connect'),
            //'cost' => __('Custo (R$)', 'pagbank-connect'),
            'is_available' => __('Disponível', 'pagbank-connect'),
            'created_at' => __('Criado em', 'pagbank-connect')
        ];
    }
    
    /**
     * Define sortable columns.
     */
    public function get_sortable_columns(): array
    {
        return [
            'reference' => ['reference', false],
            'cost' => ['cost', false],
            'is_available' => ['is_available', false],
            'created_at' => ['created_at', true]
        ];
    }
    
    /**
     * Define bulk actions.
     */
    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Excluir', 'pagbank-connect'),
            'activate' => __('Ativar', 'pagbank-connect'),
            'deactivate' => __('Desativar', 'pagbank-connect')
        ];
    }
    
    /**
     * Process bulk actions (called early in prepare_items).
     */
    public function process_bulk_action(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Check if this is a POST request with bulk action
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        $action = $this->current_action();
        if (!$action) {
            return;
        }
        
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bulk-' . $this->_args['plural'])) {
            return;
        }
        
        $box_ids = $_POST['box'] ?? [];
        if (empty($box_ids)) {
            return;
        }
        
        $box_ids = array_map('intval', $box_ids);
        
        switch ($action) {
            case 'delete':
                foreach ($box_ids as $box_id) {
                    $this->box_manager->delete($box_id);
                }
                $this->add_admin_notice(__('Caixas excluídas com sucesso.', 'pagbank-connect'), 'success');
                break;
                
            case 'activate':
                foreach ($box_ids as $box_id) {
                    $this->box_manager->update($box_id, ['is_available' => 1]);
                }
                $this->add_admin_notice(__('Caixas ativadas com sucesso.', 'pagbank-connect'), 'success');
                break;
                
            case 'deactivate':
                foreach ($box_ids as $box_id) {
                    $this->box_manager->update($box_id, ['is_available' => 0]);
                }
                $this->add_admin_notice(__('Caixas desativadas com sucesso.', 'pagbank-connect'), 'success');
                break;
        }
        
        // Redirect to avoid form resubmission
        wp_redirect(remove_query_arg(['action', 'action2', '_wpnonce', 'box']));
        exit;
    }
    
    /**
     * Prepare items (query, pagination, headers).
     */
    public function prepare_items(): void
    {
        $this->process_bulk_action();
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $args = [
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => $_GET['orderby'] ?? 'reference',
            'order' => $_GET['order'] ?? 'ASC'
        ];
        
    // Availability filter
        if (isset($_GET['filter_available']) && $_GET['filter_available'] !== '') {
            $args['is_available'] = (int) $_GET['filter_available'];
        }
        
        $this->items = $this->box_manager->get_all($args);
        $total_items = $this->box_manager->count($args);
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns()
        ];
    }
    
    /**
     * Checkbox column (bulk selector).
     */
    protected function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="box[]" value="%s" />', $item->box_id);
    }
    
    /**
     * Reference column (links + row actions).
     */
    protected function column_reference($item): string
    {
        $edit_url = admin_url('admin.php?page=rm-pagbank-boxes-edit&id=' . $item->box_id);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=rm-pagbank-boxes&action=delete&id=' . $item->box_id),
            'delete_box_' . $item->box_id
        );
        
        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', $edit_url, __('Editar', 'pagbank-connect')),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'%s\')">%s</a>', 
                $delete_url, 
                __('Tem certeza que deseja excluir esta caixa?', 'pagbank-connect'),
                __('Excluir', 'pagbank-connect')
            )
        ];
        
        return sprintf('<strong><a href="%s">%s</a></strong>%s', 
            $edit_url, 
            esc_html($item->reference),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Dimensions column (external & internal in mm).
     */
    protected function column_dimensions($item): string
    {
        return sprintf(
            '<strong>Externas:</strong> %d × %d × %d mm<br><strong>Internas:</strong> %d × %d × %d mm',
            $item->outer_width, $item->outer_depth, $item->outer_length,
            $item->inner_width, $item->inner_depth, $item->inner_length
        );
    }
    
    /**
     * Weight column (max & empty in grams).
     */
    protected function column_weight($item): string
    {
        return sprintf(
            '<strong>Máximo:</strong> %d g<br><strong>Vazia:</strong> %d g',
            $item->max_weight, $item->empty_weight
        );
    }
    
    /**
     * Cost column (commented out in columns array, kept for future usage).
     */
    protected function column_cost($item): string
    {
        return 'R$ ' . number_format($item->cost, 2, ',', '.');
    }
    
    /**
     * Availability column (icons).
     */
    protected function column_is_available($item): string
    {
        if ($item->is_available) {
            return '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . __('Disponível', 'pagbank-connect') . '"></span>';
        } else {
            return '<span class="dashicons dashicons-dismiss" style="color: #dc3232;" title="' . __('Indisponível', 'pagbank-connect') . '"></span>';
        }
    }
    
    /**
     * Created at column (localized date/time).
     */
    protected function column_created_at($item): string
    {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
    }
    
    /**
     * No items message.
     */
    public function no_items(): void
    {
        echo __('Nenhuma caixa encontrada.', 'pagbank-connect');
    }
    
    /**
     * Extra controls above the table (availability filter).
     */
    protected function extra_tablenav($which): void
    {
        if ($which === 'top') {
            $current_filter = $_GET['filter_available'] ?? '';
            ?>
            <div class="alignleft actions">
                <select name="filter_available">
                    <option value=""><?php _e('Todas as caixas', 'pagbank-connect'); ?></option>
                    <option value="1" <?php selected($current_filter, '1'); ?>><?php _e('Disponíveis', 'pagbank-connect'); ?></option>
                    <option value="0" <?php selected($current_filter, '0'); ?>><?php _e('Indisponíveis', 'pagbank-connect'); ?></option>
                </select>
                <input type="submit" class="button" value="<?php _e('Filtrar', 'pagbank-connect'); ?>">
            </div>
            <?php
        }
    }
    
    /**
     * Render admin notice (helper).
     */
    private function add_admin_notice(string $message, string $type = 'info'): void
    {
        add_action('admin_notices', function() use ($message, $type) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $type, esc_html($message));
        });
    }
}
