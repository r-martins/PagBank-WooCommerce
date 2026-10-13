<?php

declare(strict_types=1);

namespace RM_PagBank\Helpers;

use RM_PagBank\Partner\Partner;
use WC_Order;

/**
 * Centralizes order meta keys for dual partners.
 *
 * Write:
 * - PagBank → pagbank_* / _pagbank_*
 * - Vindi (or any non-PagBank) → pb_* / _pb_*
 *
 * Read: preferred partner prefix first, then the other (legacy / migration).
 *
 * Logical keys omit the brand, e.g. order_id, _card_brand, partner.
 * Full legacy keys (pagbank_order_id) are also accepted and normalized.
 */
final class OrderMeta
{
    public const BRAND_PAGBANK = 'pagbank';
    public const BRAND_PB = 'pb';

    /**
     * Normalize a logical or physical key to logical form.
     */
    public static function toLogical(string $key): string
    {
        if (strpos($key, '_pagbank_') === 0) {
            return '_' . substr($key, strlen('_pagbank_'));
        }
        if (strpos($key, 'pagbank_') === 0) {
            return substr($key, strlen('pagbank_'));
        }
        if (strpos($key, '_pb_') === 0) {
            return '_' . substr($key, strlen('_pb_'));
        }
        if (strpos($key, 'pb_') === 0) {
            return substr($key, strlen('pb_'));
        }

        return $key;
    }

    public static function brandForPartner(string $partner): string
    {
        return $partner === Partner::PAGBANK ? self::BRAND_PAGBANK : self::BRAND_PB;
    }

    /**
     * Physical meta key for a partner.
     */
    public static function physicalKey(string $logicalOrPhysical, string $partner): string
    {
        $logical = self::toLogical($logicalOrPhysical);
        $brand = self::brandForPartner($partner);
        $private = isset($logical[0]) && $logical[0] === '_';
        $suffix = $private ? substr($logical, 1) : $logical;

        return ($private ? '_' : '') . $brand . '_' . $suffix;
    }

    /**
     * Candidate physical keys (preferred partner first).
     *
     * @return list<string>
     */
    public static function candidateKeys(string $logicalOrPhysical, ?string $preferredPartner = null): array
    {
        $logical = self::toLogical($logicalOrPhysical);
        $pagbank = self::physicalKey($logical, Partner::PAGBANK);
        $pb = self::physicalKey($logical, Partner::VINDI);

        if ($preferredPartner === Partner::PAGBANK) {
            return [$pagbank, $pb];
        }
        if ($preferredPartner !== null && $preferredPartner !== '') {
            return [$pb, $pagbank];
        }

        // Unknown partner: prefer modern pb_ then legacy pagbank_.
        return [$pb, $pagbank];
    }

    /**
     * Partner stored on the order (pb_partner or pagbank_partner). Empty if none.
     */
    public static function readPartner(WC_Order $order): string
    {
        foreach (['pb_partner', 'pagbank_partner'] as $key) {
            if (method_exists($order, 'meta_exists') && !$order->meta_exists($key)) {
                continue;
            }
            $value = $order->get_meta($key);
            if (!is_string($value) || $value === '') {
                continue;
            }
            return $value === Partner::VINDI ? Partner::VINDI : Partner::PAGBANK;
        }

        return '';
    }

    /**
     * Partner used for new writes.
     */
    public static function resolveWritePartner(WC_Order $order, ?string $explicit = null): string
    {
        if (is_string($explicit) && ($explicit === Partner::VINDI || $explicit === Partner::PAGBANK)) {
            return $explicit;
        }

        $fromOrder = self::readPartner($order);
        if ($fromOrder !== '') {
            return $fromOrder;
        }

        try {
            return (new Api())->partner()->getPartner();
        } catch (\Throwable $e) {
            return Partner::PAGBANK;
        }
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(WC_Order $order, string $logicalOrPhysical, $default = '')
    {
        $logical = self::toLogical($logicalOrPhysical);
        $partner = self::readPartner($order);
        $preferred = $partner !== '' ? $partner : null;

        foreach (self::candidateKeys($logical, $preferred) as $key) {
            if (method_exists($order, 'meta_exists')) {
                if ($order->meta_exists($key)) {
                    return $order->get_meta($key);
                }
                continue;
            }
            $value = $order->get_meta($key);
            if ($value !== '' && $value !== null) {
                return $value;
            }
        }

        return $default;
    }

    public static function has(WC_Order $order, string $logicalOrPhysical): bool
    {
        $logical = self::toLogical($logicalOrPhysical);
        $partner = self::readPartner($order);
        $preferred = $partner !== '' ? $partner : null;

        foreach (self::candidateKeys($logical, $preferred) as $key) {
            if (method_exists($order, 'meta_exists')) {
                if ($order->meta_exists($key)) {
                    return true;
                }
                continue;
            }
            $value = $order->get_meta($key);
            if ($value !== '' && $value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     */
    public static function set(
        WC_Order $order,
        string $logicalOrPhysical,
        $value,
        bool $unique = true,
        ?string $partner = null
    ): void {
        $writePartner = self::resolveWritePartner($order, $partner);
        $key = self::physicalKey($logicalOrPhysical, $writePartner);

        if ($unique) {
            $order->update_meta_data($key, $value);
            return;
        }

        $order->add_meta_data($key, $value, false);
    }

    /**
     * Deletes both brand variants so leftovers do not linger after partner switches.
     */
    public static function delete(WC_Order $order, string $logicalOrPhysical): void
    {
        foreach (self::candidateKeys($logicalOrPhysical, null) as $key) {
            $order->delete_meta_data($key);
        }
    }

    /**
     * meta_query clause matching either brand prefix (pagbank_* or pb_*).
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    public static function metaQueryEquals(string $logicalOrPhysical, $value, string $compare = '='): array
    {
        $clause = [
            'relation' => 'OR',
        ];
        foreach (self::candidateKeys($logicalOrPhysical, null) as $key) {
            $clause[] = [
                'key'     => $key,
                'value'   => $value,
                'compare' => $compare,
            ];
        }

        return $clause;
    }

    /**
     * meta_query clause that only checks key existence on either brand.
     *
     * EXISTS → OR (either brand has the key).
     * NOT EXISTS → AND (neither brand has the key).
     *
     * @return array<string, mixed>
     */
    public static function metaQueryExists(string $logicalOrPhysical, bool $exists = true): array
    {
        $keys = self::candidateKeys($logicalOrPhysical, null);
        if ($exists) {
            $clause = ['relation' => 'OR'];
            foreach ($keys as $key) {
                $clause[] = [
                    'key'     => $key,
                    'compare' => 'EXISTS',
                ];
            }

            return $clause;
        }

        $clause = ['relation' => 'AND'];
        foreach ($keys as $key) {
            $clause[] = [
                'key'     => $key,
                'compare' => 'NOT EXISTS',
            ];
        }

        return $clause;
    }
}
