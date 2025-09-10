<?php

namespace RM_PagBank\Connect\EnvioFacil;

use Exception;
use WP_Error;

/**
 * Class Box
 *
 * Manages CRUD operations for Envio Fácil boxes.
 * Developer comments translated to English; user‑facing strings kept in Portuguese for localization.
 *
 * Responsibilities:
 *  - Persist box definitions (outer/inner dimensions, weights)
 *  - Validate & sanitize input
 *  - Provide listing & availability helpers
 *  - Derive inner dimensions from thickness
 *
 * @author    Ricardo Martins
 * @copyright 2024 Magenteiro
 * @package   RM_PagBank\Connect\EnvioFacil
 */
class Box
{
    private string $table_name;
    
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pagbank_ef_boxes';
    }
    
    /**
     * Create a new box.
     *
     * @param array $data Box data
     * @return int|WP_Error Box ID or error
     */
    public function create(array $data)
    {
        global $wpdb;
        
    // Validate required fields
        $required_fields = [
            'reference',
            'outer_width',
            'outer_depth', 
            'outer_length',
            'thickness',
            'max_weight',
            'empty_weight'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Campo obrigatório: %s', 'pagbank-connect'), $field));
            }
        }
        
        // Sanitize input
        $sanitized_data = $this->sanitize_data($data);
        
        // Compute inner dimensions
        $sanitized_data = $this->calculate_inner_dimensions($sanitized_data);
        
        // Prevent duplicate references
        if ($this->reference_exists($sanitized_data['reference'])) {
            return new WP_Error('duplicate_reference', __('Esta referência já existe. Escolha outra.', 'pagbank-connect'));
        }
        
        // Insert into DB
        $result = $wpdb->insert($this->table_name, $sanitized_data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao salvar no banco de dados.', 'pagbank-connect'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get box by ID.
     *
     * @param int $box_id Box ID
     * @return object|null Box row or null
     */
    public function get_by_id(int $box_id)
    {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE box_id = %d",
            $box_id
        ));
    }
    
    /**
     * Get all boxes (with optional filters & pagination).
     *
     * @param array $args Filter args
     * @return array Box list
     */
    public function get_all(array $args = [])
    {
        global $wpdb;
        
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'reference',
            'order' => 'ASC',
            'is_available' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = [];
        $where_values = [];
        
        if ($args['is_available'] !== null) {
            $where_conditions[] = 'is_available = %d';
            $where_values[] = $args['is_available'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        $sql = "SELECT * FROM {$this->table_name} {$where_clause} {$order_clause} {$limit_clause}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Count boxes (filter aware).
     *
     * @param array $args Filter args
     * @return int Total count
     */
    public function count(array $args = [])
    {
        global $wpdb;
        
        $where_conditions = [];
        $where_values = [];
        
        if (isset($args['is_available']) && $args['is_available'] !== null) {
            $where_conditions[] = 'is_available = %d';
            $where_values[] = $args['is_available'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Return all available boxes (no pagination).
     * @return array
     */
    public function get_all_available(): array
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table_name} WHERE is_available = 1 ORDER BY reference ASC";
        return $wpdb->get_results($sql);
    }
    
    /**
     * Update a box.
     *
     * @param int $box_id Box ID
     * @param array $data Data to update
     * @return bool|WP_Error True or error
     */
    public function update(int $box_id, array $data)
    {
        global $wpdb;
        
        // Ensure box exists
        if (!$this->get_by_id($box_id)) {
            return new WP_Error('box_not_found', __('Caixa não encontrada.', 'pagbank-connect'));
        }
        
        // Sanitize
        $sanitized_data = $this->sanitize_data($data);
        
        // Recalculate inner dimensions when outer dims or thickness changed
        if (isset($sanitized_data['outer_width']) || isset($sanitized_data['outer_depth']) || 
            isset($sanitized_data['outer_length']) || isset($sanitized_data['thickness'])) {
            $sanitized_data = $this->calculate_inner_dimensions($sanitized_data);
        }
        
        // Prevent duplicate reference (excluding current box)
        if (isset($sanitized_data['reference']) && $this->reference_exists($sanitized_data['reference'], $box_id)) {
            return new WP_Error('duplicate_reference', __('Esta referência já existe. Escolha outra.', 'pagbank-connect'));
        }
        
        // Run DB update
        $result = $wpdb->update(
            $this->table_name,
            $sanitized_data,
            ['box_id' => $box_id],
            $this->get_format_array($sanitized_data),
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao atualizar no banco de dados.', 'pagbank-connect'));
        }
        
        return true;
    }
    
    /**
     * Delete a box.
     *
     * @param int $box_id Box ID
     * @return bool|WP_Error True or error
     */
    public function delete(int $box_id)
    {
        global $wpdb;
        
        // Ensure exists before deleting
        if (!$this->get_by_id($box_id)) {
            return new WP_Error('box_not_found', __('Caixa não encontrada.', 'pagbank-connect'));
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            ['box_id' => $box_id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao remover do banco de dados.', 'pagbank-connect'));
        }
        
        return true;
    }
    
    /**
     * Check if a reference already exists.
     *
     * @param string $reference Reference value
     * @param int $exclude_id Excluded box ID (when updating)
     * @return bool
     */
    private function reference_exists(string $reference, int $exclude_id = 0): bool
    {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE reference = %s";
        $values = [$reference];
        
        if ($exclude_id > 0) {
            $sql .= " AND box_id != %d";
            $values[] = $exclude_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($sql, $values));
        
        return (int) $count > 0;
    }
    
    /**
     * Sanitize input array.
     *
     * @param array $data Raw data
     * @return array Sanitized data
     */
    private function sanitize_data(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['reference'])) {
            $sanitized['reference'] = sanitize_text_field($data['reference']);
        }
        
        if (isset($data['is_available'])) {
            $sanitized['is_available'] = (int) $data['is_available'];
        }
        
        $dimension_fields = [
            'outer_width', 'outer_depth', 'outer_length',
            'thickness'
        ];
        
        foreach ($dimension_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = (float) $data[$field];
            }
        }
        
        $weight_fields = [
            'max_weight', 'empty_weight'
        ];
        
        foreach ($weight_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = (int) $data[$field];
            }
        }
        

        
        return $sanitized;
    }
    
    /**
     * Calculate inner dimensions from outer dimensions and thickness.
     *
     * @param array $data Box data
     * @return array Updated data
     */
    private function calculate_inner_dimensions(array $data): array
    {
    // Extract outer dims + thickness
        $outer_width = $data['outer_width'] ?? 0;
        $outer_depth = $data['outer_depth'] ?? 0;
        $outer_length = $data['outer_length'] ?? 0;
        $thickness = $data['thickness'] ?? 2;
        
    // Derive usable inner dimensions
        $data['inner_width'] = max(0, $outer_width - ($thickness * 2));
        $data['inner_depth'] = max(0, $outer_depth - ($thickness * 2));
        $data['inner_length'] = max(0, $outer_length - ($thickness * 2));
        
        return $data;
    }
    
    /**
     * Return formats array for $wpdb operations.
     *
     * @param array $data Data
     * @return array Formats
     */
    private function get_format_array(array $data): array
    {
        $formats = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['outer_width', 'outer_depth', 'outer_length', 'thickness', 'inner_length', 'inner_width', 'inner_depth'])) {
                $formats[] = '%s';
            } elseif (in_array($key, ['max_weight', 'empty_weight', 'is_available'])) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        return $formats;
    }
    
    /**
     * Get boxes that can fit a product with given specs.
     *
     * @param int $width Product width
     * @param int $length Product length
     * @param int $depth Product depth
     * @param int $weight Product weight
     * @return array Matching boxes
     */
    public function get_available_boxes(int $width, int $length, int $depth, int $weight): array
    {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE is_available = 1 
             AND inner_width >= %d 
             AND inner_length >= %d 
             AND inner_depth >= %d 
             AND max_weight >= %d 
             ORDER BY cost ASC",
            $width, $length, $depth, $weight
        ));
    }
}
