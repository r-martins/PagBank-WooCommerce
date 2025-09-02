<?php

namespace RM_PagBank\Connect\EnvioFacil;

use Exception;
use WP_Error;

/**
 * Class Box
 * 
 * Gerencia operações CRUD para caixas do EnvioFácil
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
     * Cria uma nova caixa
     *
     * @param array $data Dados da caixa
     * @return int|WP_Error ID da caixa criada ou erro
     */
    public function create(array $data)
    {
        global $wpdb;
        
        // Validar dados obrigatórios
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
        
        // Sanitizar dados
        $sanitized_data = $this->sanitize_data($data);
        
        // Calcular dimensões internas
        $sanitized_data = $this->calculate_inner_dimensions($sanitized_data);
        
        // Verificar se a referência já existe
        if ($this->reference_exists($sanitized_data['reference'])) {
            return new WP_Error('duplicate_reference', __('Esta referência já existe. Escolha outra.', 'pagbank-connect'));
        }
        
        // Inserir no banco
        $result = $wpdb->insert($this->table_name, $sanitized_data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Erro ao salvar no banco de dados.', 'pagbank-connect'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Busca uma caixa por ID
     *
     * @param int $box_id ID da caixa
     * @return object|null Objeto da caixa ou null se não encontrada
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
     * Busca todas as caixas
     *
     * @param array $args Argumentos para filtros
     * @return array Lista de caixas
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
     * Conta o total de caixas
     *
     * @param array $args Argumentos para filtros
     * @return int Total de caixas
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
     * Atualiza uma caixa
     *
     * @param int $box_id ID da caixa
     * @param array $data Dados para atualizar
     * @return bool|WP_Error True se sucesso ou erro
     */
    public function update(int $box_id, array $data)
    {
        global $wpdb;
        
        // Verificar se a caixa existe
        if (!$this->get_by_id($box_id)) {
            return new WP_Error('box_not_found', __('Caixa não encontrada.', 'pagbank-connect'));
        }
        
        // Sanitizar dados
        $sanitized_data = $this->sanitize_data($data);
        
        // Calcular dimensões internas se dimensões externas ou espessura foram alteradas
        if (isset($sanitized_data['outer_width']) || isset($sanitized_data['outer_depth']) || 
            isset($sanitized_data['outer_length']) || isset($sanitized_data['thickness'])) {
            $sanitized_data = $this->calculate_inner_dimensions($sanitized_data);
        }
        
        // Verificar se a referência já existe (exceto para a própria caixa)
        if (isset($sanitized_data['reference']) && $this->reference_exists($sanitized_data['reference'], $box_id)) {
            return new WP_Error('duplicate_reference', __('Esta referência já existe. Escolha outra.', 'pagbank-connect'));
        }
        
        // Atualizar no banco
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
     * Remove uma caixa
     *
     * @param int $box_id ID da caixa
     * @return bool|WP_Error True se sucesso ou erro
     */
    public function delete(int $box_id)
    {
        global $wpdb;
        
        // Verificar se a caixa existe
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
     * Verifica se uma referência já existe
     *
     * @param string $reference Referência a verificar
     * @param int $exclude_id ID a excluir da verificação (para updates)
     * @return bool True se existe
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
     * Sanitiza dados de entrada
     *
     * @param array $data Dados para sanitizar
     * @return array Dados sanitizados
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
     * Calcula as dimensões internas baseado nas externas e espessura
     *
     * @param array $data Dados da caixa
     * @return array Dados com dimensões internas calculadas
     */
    private function calculate_inner_dimensions(array $data): array
    {
        // Obter dimensões externas e espessura
        $outer_width = $data['outer_width'] ?? 0;
        $outer_depth = $data['outer_depth'] ?? 0;
        $outer_length = $data['outer_length'] ?? 0;
        $thickness = $data['thickness'] ?? 2;
        
        // Calcular dimensões internas
        $data['inner_width'] = max(0, $outer_width - ($thickness * 2));
        $data['inner_depth'] = max(0, $outer_depth - ($thickness * 2));
        $data['inner_length'] = max(0, $outer_length - ($thickness * 2));
        
        return $data;
    }
    
    /**
     * Retorna array de formatos para wpdb
     *
     * @param array $data Dados
     * @return array Formatos
     */
    private function get_format_array(array $data): array
    {
        $formats = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['outer_width', 'outer_depth', 'outer_length', 'thickness', 'inner_length', 'inner_width', 'inner_depth'])) {
                $formats[] = '%f';
            } elseif (in_array($key, ['max_weight', 'empty_weight', 'is_available'])) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        return $formats;
    }
    
    /**
     * Busca caixas disponíveis para um produto
     *
     * @param int $width Largura do produto
     * @param int $length Comprimento do produto
     * @param int $depth Profundidade do produto
     * @param int $weight Peso do produto
     * @return array Caixas que cabem o produto
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
