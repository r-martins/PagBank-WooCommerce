<?php

namespace RM_PagBank\Compat;

use WC_Order;

/**
 * Show Brazilian number + neighborhood on formatted addresses when Extra Checkout Fields
 * for Brazil is not active (Checkout Blocks / Checkout PB default stores often omit it).
 *
 * PagBank / Checkout PB sync already stores `_billing_number`, `_shipping_number`,
 * `_billing_neighborhood`, `_shipping_neighborhood` — Woo core only prints address_1/2.
 */
class BrazilianAddressDisplay
{
    public static function init(): void
    {
        if (BrazilianCheckoutFields::isPluginActive()) {
            return;
        }

        add_filter('woocommerce_localisation_address_formats', [self::class, 'localisationFormats'], 20);
        add_filter('woocommerce_formatted_address_replacements', [self::class, 'formattedReplacements'], 20, 2);
        add_filter('woocommerce_order_formatted_billing_address', [self::class, 'orderBillingAddress'], 20, 2);
        add_filter('woocommerce_order_formatted_shipping_address', [self::class, 'orderShippingAddress'], 20, 2);
        add_filter('woocommerce_my_account_my_address_formatted_address', [self::class, 'myAccountAddress'], 20, 3);
    }

    /**
     * @param array<string, string> $formats
     * @return array<string, string>
     */
    public static function localisationFormats(array $formats): array
    {
        // {number} replacement includes leading ", " when set (see formattedReplacements).
        $formats['BR'] = "{name}\n{company}\n{address_1}{number}\n{address_2}\n{neighborhood}\n{city}\n{state}\n{postcode}\n{country}";

        return $formats;
    }

    /**
     * @param array<string, string> $replacements
     * @param array<string, mixed>  $args
     * @return array<string, string>
     */
    public static function formattedReplacements(array $replacements, array $args): array
    {
        $number = trim((string) ($args['number'] ?? ''));
        $neighborhood = trim((string) ($args['neighborhood'] ?? ''));

        $replacements['{number}'] = $number !== '' ? ', ' . $number : '';
        $replacements['{neighborhood}'] = $neighborhood;

        return $replacements;
    }

    /**
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    public static function orderBillingAddress(array $address, $order): array
    {
        if (!$order instanceof WC_Order) {
            return $address;
        }

        $address['number'] = (string) $order->get_meta('_billing_number');
        $address['neighborhood'] = (string) $order->get_meta('_billing_neighborhood');

        return $address;
    }

    /**
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    public static function orderShippingAddress(array $address, $order): array
    {
        if (!$order instanceof WC_Order) {
            return $address;
        }

        $address['number'] = (string) $order->get_meta('_shipping_number');
        $address['neighborhood'] = (string) $order->get_meta('_shipping_neighborhood');

        return $address;
    }

    /**
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    public static function myAccountAddress(array $address, int $customerId, string $addressType): array
    {
        $prefix = $addressType === 'shipping' ? 'shipping' : 'billing';
        $address['number'] = (string) get_user_meta($customerId, $prefix . '_number', true);
        $address['neighborhood'] = (string) get_user_meta($customerId, $prefix . '_neighborhood', true);

        return $address;
    }
}
