<?php

namespace RM_PagBank\Helpers;

use WC_Order;

/**
 * PIX QR image helpers.
 *
 * Vindi sandbox/prod often serves qrcode_path as CloudFront SVG without Content-Type.
 * Browsers refuse that in <img>; we proxy through wc-api with a proper MIME type.
 */
class PixQr
{
    public const API_ACTION = 'rm_pagbank_pix_qr';

    public static function register(): void
    {
        add_action('woocommerce_api_' . self::API_ACTION, [self::class, 'serve']);
    }

    /**
     * Whether the remote QR URL needs to be proxied (missing/unsafe MIME for <img>).
     */
    public static function needsProxy(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        if (preg_match('/\.svgz?$/i', $path)) {
            return true;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        // Vindi / Yapay CDN used for PIX QR SVGs without Content-Type.
        if ($host !== '' && (
            strpos($host, 'cloudfront.net') !== false
            || strpos($host, 'vindi.com.br') !== false
            || strpos($host, 'yapay.com.br') !== false
        )) {
            return true;
        }

        return false;
    }

    /**
     * URL safe for <img src> / email (proxied when needed).
     */
    public static function getDisplayUrl(WC_Order $order): string
    {
        $remote = (string) OrderMeta::get($order, 'pix_qrcode');
        if ($remote === '') {
            return '';
        }

        if (!self::needsProxy($remote)) {
            return $remote;
        }

        return add_query_arg(
            [
                'wc-api'   => self::API_ACTION,
                'order_id' => $order->get_id(),
                'key'      => $order->get_order_key(),
            ],
            home_url('/')
        );
    }

    /**
     * wc-api handler: stream remote QR with a correct Content-Type.
     */
    public static function serve(): void
    {
        $orderId = absint($_GET['order_id'] ?? 0);
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash((string) $_GET['key'])) : '';

        $order = $orderId ? wc_get_order($orderId) : false;
        if (!$order instanceof WC_Order || $key === '' || !hash_equals($order->get_order_key(), $key)) {
            status_header(403);
            exit;
        }

        $url = (string) OrderMeta::get($order, 'pix_qrcode');
        if ($url === '' || !wp_http_validate_url($url)) {
            status_header(404);
            exit;
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout'     => 30,
                'redirection' => 3,
                'user-agent'  => 'WooCommerce / PagBank Integracoes PIX QR',
            ]
        );

        if (is_wp_error($response)) {
            status_header(502);
            exit;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300 || $body === '') {
            status_header(502);
            exit;
        }

        $contentType = self::detectContentType($url, $body, wp_remote_retrieve_header($response, 'content-type'));

        status_header(200);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary image body
        echo $body;
        exit;
    }

    /**
     * @param string|array<int, string>|null $remoteContentType
     */
    private static function detectContentType(string $url, string $body, $remoteContentType): string
    {
        $trimmed = ltrim($body);
        if (strpos($trimmed, '<?xml') === 0 || strpos($trimmed, '<svg') === 0) {
            return 'image/svg+xml';
        }
        if (strpos($body, "\x89PNG") === 0) {
            return 'image/png';
        }
        if (strpos($body, "\xFF\xD8\xFF") === 0) {
            return 'image/jpeg';
        }

        if (is_array($remoteContentType)) {
            $remoteContentType = $remoteContentType[0] ?? '';
        }
        $remote = strtolower(trim((string) $remoteContentType));
        if ($remote !== '' && strpos($remote, 'image/') === 0) {
            $mime = strtok($remote, ';');
            return is_string($mime) && $mime !== '' ? $mime : 'image/png';
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        if (preg_match('/\.svgz?$/i', $path)) {
            return 'image/svg+xml';
        }
        if (preg_match('/\.jpe?g$/i', $path)) {
            return 'image/jpeg';
        }

        return 'image/png';
    }
}
