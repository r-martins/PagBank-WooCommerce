<?php

namespace RM_PagBank\Helpers;

/**
 * CPF (numeric) and CNPJ (numeric or alphanumeric) normalization for PagBank tax_id.
 *
 * CNPJ alfanumérico (RFB): 14 characters without mask — 12 alphanumerics + 2 numeric check digits.
 * Display mask remains XX.XXX.XXX/XXXX-DV for both legacy numeric and new alphanumeric CNPJs.
 */
class TaxId
{
    public const CPF_LENGTH = 11;

    public const CNPJ_LENGTH = 14;

    /** CNPJ without mask: 12 alphanumerics (A–Z, 0–9) + 2 numeric DVs. */
    public const CNPJ_PATTERN = '/^[A-Z0-9]{12}[0-9]{2}$/';

    /** Weights for alphanumeric CNPJ check digits (RFB / IN 2.229/2024). */
    private const CNPJ_DV_WEIGHTS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Removes mask punctuation; keeps A–Z and 0–9 (uppercase). Safe for API customer.tax_id.
     */
    public static function sanitizeForApi(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = mb_strtoupper(trim((string) $value), 'UTF-8');
        $sanitized = preg_replace('/[^A-Z0-9]/', '', $value);

        if (! is_string($sanitized)) {
            return '';
        }

        /** @since 4.54.2 */
        return (string) apply_filters('pagbank_connect_sanitize_tax_id', $sanitized, $value);
    }

    /**
     * @return 'cpf'|'cnpj'|null
     */
    public static function detectType(string $sanitized): ?string
    {
        $sanitized = self::sanitizeForApi($sanitized);
        if ($sanitized === '') {
            return null;
        }

        if (preg_match('/[A-Z]/', $sanitized)) {
            return 'cnpj';
        }

        if (strlen($sanitized) <= self::CPF_LENGTH && preg_match('/^\d+$/', $sanitized)) {
            return 'cpf';
        }

        if (strlen($sanitized) >= self::CPF_LENGTH + 1) {
            return 'cnpj';
        }

        return null;
    }

    public static function isCpf(string $sanitized): bool
    {
        $sanitized = self::sanitizeForApi($sanitized);

        return strlen($sanitized) === self::CPF_LENGTH && ctype_digit($sanitized);
    }

    public static function isCnpj(string $sanitized): bool
    {
        $sanitized = self::sanitizeForApi($sanitized);

        return strlen($sanitized) === self::CNPJ_LENGTH && (bool) preg_match(self::CNPJ_PATTERN, $sanitized);
    }

    /**
     * Validates CNPJ check digits (numeric and alphanumeric), per RFB modulo-11 (ASCII value - 48).
     */
    public static function isValidCnpj(string $cnpj): bool
    {
        $cnpj = self::sanitizeForApi($cnpj);

        if (! self::isCnpj($cnpj)) {
            return false;
        }

        if ($cnpj === '00000000000000') {
            return false;
        }

        if (preg_match('/^\d{14}$/', $cnpj) && preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $sum1 = 0;
        $sum2 = 0;

        for ($i = 0; $i < 12; $i++) {
            $value = ord($cnpj[$i]) - 48;
            $sum1 += $value * self::CNPJ_DV_WEIGHTS[$i + 1];
            $sum2 += $value * self::CNPJ_DV_WEIGHTS[$i];
        }

        $dv1 = ($sum1 % 11 < 2) ? 0 : 11 - ($sum1 % 11);
        $sum2 += $dv1 * self::CNPJ_DV_WEIGHTS[12];
        $dv2 = ($sum2 % 11 < 2) ? 0 : 11 - ($sum2 % 11);

        return $cnpj[12] === (string) $dv1 && $cnpj[13] === (string) $dv2;
    }

    /**
     * Accepts CPF (11 digits) or CNPJ matching format (no DV check).
     */
    public static function isValidFormat(string $sanitized): bool
    {
        $sanitized = self::sanitizeForApi($sanitized);

        return self::isCpf($sanitized) || self::isCnpj($sanitized);
    }

    /**
     * Applies CPF or CNPJ display mask when enough characters are present.
     */
    public static function formatForDisplay(string $value): string
    {
        $sanitized = self::sanitizeForApi($value);
        if ($sanitized === '') {
            return '';
        }

        if (self::isCpf($sanitized) || (strlen($sanitized) <= self::CPF_LENGTH && preg_match('/^\d+$/', $sanitized) && ! preg_match('/[A-Z]/', $sanitized))) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', str_pad($sanitized, self::CPF_LENGTH, '0', STR_PAD_LEFT));
        }

        if (strlen($sanitized) > self::CNPJ_LENGTH) {
            $sanitized = substr($sanitized, 0, self::CNPJ_LENGTH);
        }

        $root = substr($sanitized, 0, 2);
        $part2 = substr($sanitized, 2, 3);
        $part3 = substr($sanitized, 5, 3);
        $part4 = substr($sanitized, 8, 4);
        $dv = substr($sanitized, 12, 2);

        $formatted = $root;
        if ($part2 !== '') {
            $formatted .= '.' . $part2;
        }
        if ($part3 !== '') {
            $formatted .= '.' . $part3;
        }
        if ($part4 !== '') {
            $formatted .= '/' . $part4;
        }
        if ($dv !== '') {
            $formatted .= '-' . $dv;
        }

        return $formatted;
    }
}
