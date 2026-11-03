<?php

declare(strict_types=1);

namespace RM_PagBank\Helpers;

/**
 * Consulta pública de CNPJ via ReceitaWS (mesmo provedor do onboarding pbintegracoes.com).
 */
final class ReceitaWs
{
    private const ENDPOINT = 'https://receitaws.com.br/v1/cnpj/';
    private const TIMEOUT_SECONDS = 8;
    private const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public static function lookupCnpj(string $cnpj): array
    {
        $digits = TaxId::sanitizeForApi($cnpj);
        if (!TaxId::isCnpj($digits)) {
            throw new \RuntimeException(__('CNPJ inválido.', 'pagbank-connect'));
        }

        $cacheKey = 'pagbank_receitaws_cnpj_' . $digits;
        $cached = get_transient($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $response = wp_remote_get(self::ENDPOINT . $digits, [
            'timeout' => self::TIMEOUT_SECONDS,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WooCommerce / PagBank Integracoes',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException(
                __('Timeout ou falha ao consultar o CNPJ.', 'pagbank-connect')
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 429) {
            throw new \RuntimeException(
                __('Consulta de CNPJ temporariamente limitada. Tente de novo em instantes.', 'pagbank-connect')
            );
        }
        if ($code === 404) {
            throw new \RuntimeException(__('CNPJ não encontrado.', 'pagbank-connect'));
        }
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException(__('Não foi possível consultar o CNPJ.', 'pagbank-connect'));
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            throw new \RuntimeException(__('Resposta inválida da ReceitaWS.', 'pagbank-connect'));
        }

        $status = strtoupper((string) ($body['status'] ?? ''));
        if ($status !== '' && $status !== 'OK') {
            $message = trim((string) ($body['message'] ?? ''));
            throw new \RuntimeException(
                $message !== '' ? $message : __('CNPJ não encontrado.', 'pagbank-connect')
            );
        }

        $normalized = self::normalizeForDisplay($body, $digits);
        set_transient($cacheKey, $normalized, self::CACHE_TTL);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function normalizeForDisplay(array $raw, string $cnpjDigits): array
    {
        $atividade = [];
        if (!empty($raw['atividade_principal'][0]) && is_array($raw['atividade_principal'][0])) {
            $atividade = $raw['atividade_principal'][0];
        }

        $secundarias = [];
        if (!empty($raw['atividades_secundarias']) && is_array($raw['atividades_secundarias'])) {
            foreach ($raw['atividades_secundarias'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = trim((string) ($row['code'] ?? ''));
                $text = trim((string) ($row['text'] ?? ''));
                if ($code === '' && $text === '') {
                    continue;
                }
                $secundarias[] = [
                    'code' => $code,
                    'text' => $text,
                ];
            }
        }

        $qsa = [];
        if (!empty($raw['qsa']) && is_array($raw['qsa'])) {
            foreach ($raw['qsa'] as $socio) {
                if (!is_array($socio)) {
                    continue;
                }
                $nome = trim((string) ($socio['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $qsa[] = [
                    'nome' => $nome,
                    'qual' => trim((string) ($socio['qual'] ?? '')),
                ];
            }
        }

        return [
            'cnpj' => TaxId::formatForDisplay($cnpjDigits),
            'cnpj_raw' => $cnpjDigits,
            'nome' => trim((string) ($raw['nome'] ?? '')),
            'fantasia' => trim((string) ($raw['fantasia'] ?? '')),
            'situacao' => trim((string) ($raw['situacao'] ?? '')),
            'abertura' => trim((string) ($raw['abertura'] ?? '')),
            'tipo' => trim((string) ($raw['tipo'] ?? '')),
            'porte' => trim((string) ($raw['porte'] ?? '')),
            'natureza_juridica' => trim((string) ($raw['natureza_juridica'] ?? '')),
            'email' => trim((string) ($raw['email'] ?? '')),
            'telefone' => trim((string) ($raw['telefone'] ?? '')),
            'capital_social' => trim((string) ($raw['capital_social'] ?? '')),
            'logradouro' => trim((string) ($raw['logradouro'] ?? '')),
            'numero' => trim((string) ($raw['numero'] ?? '')),
            'complemento' => trim((string) ($raw['complemento'] ?? '')),
            'bairro' => trim((string) ($raw['bairro'] ?? '')),
            'municipio' => trim((string) ($raw['municipio'] ?? '')),
            'uf' => trim((string) ($raw['uf'] ?? '')),
            'cep' => trim((string) ($raw['cep'] ?? '')),
            'atividade_principal' => [
                'code' => trim((string) ($atividade['code'] ?? '')),
                'text' => trim((string) ($atividade['text'] ?? '')),
            ],
            'atividades_secundarias' => $secundarias,
            'qsa' => $qsa,
            'source' => 'Receitaws',
        ];
    }
}
