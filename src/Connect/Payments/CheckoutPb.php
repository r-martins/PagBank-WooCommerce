<?php

namespace RM_PagBank\Connect\Payments;

use RM_PagBank\Helpers\Functions;
use RM_PagBank\Helpers\Params;
use RM_PagBank\Helpers\Recurring as RecurringHelper;
use RM_PagBank\Object\Item;
use RM_PagBank\Object\PaymentMethod;
use RM_PagBank\Object\PaymentMethodConfigOptions;
use RM_PagBank\Object\PaymentMethodsConfigs;
use RM_PagBank\Object\Shipping;
use WC_Data_Exception;
use WC_Order;
use RM_PagBank\Helpers\OrderMeta;

/**
 * Checkout PB (hosted checkout via API v1 /checkouts).
 */
class CheckoutPb extends Common
{
    public string $code = 'checkout_pb';

    /** When true, prepare uses CALCULATE shipping (one-click buy). */
    protected bool $oneClick = false;

    public function setOneClick(bool $oneClick): void
    {
        $this->oneClick = $oneClick;
    }

    /**
     * @return array
     * @throws WC_Data_Exception
     * @throws \Exception
     */
    public function prepare(): array
    {
        $return = $this->getDefaultParameters();

        if (isset($return['customer']->getPhone()[0])) {
            $return['customer']->setPhone($return['customer']->getPhone()[0]);
        }

        unset($return['shipping']);

        $return['items'] = $this->enrichItemsWithMediaAndDims(
            is_array($return['items'] ?? null) ? $return['items'] : $this->getItemsData()
        );

        if ($this->oneClick) {
            $return['shipping'] = $this->buildCalculateShipping();
        } elseif (Params::getCheckoutPbMode() === 'default') {
            // Checkout padrão: Woo redireciona sem frete; usa config do gateway (free/fixed/calculate).
            $return['shipping'] = $this->buildShippingFromGatewaySettings();
        } else {
            // Meio de pagamento: frete já calculado no checkout Woo.
            $fixedShipping = $this->buildFixedShippingFromOrder();
            if ($fixedShipping !== null) {
                $return['shipping'] = $fixedShipping;
            }
        }

        $orderTotal = (float) $this->order->get_total();
        $discountExcludesShipping = Params::getCheckoutPbConfig('checkout_pb_discount_excludes_shipping', 'no') === 'yes';

        $discountAmount = [];
        if (($discountConfig = Params::getCheckoutPbConfig('checkout_pb_discount', 0)) && !is_wc_endpoint_url('order-pay')) {
            $discount = floatval(Params::getDiscountValue($discountConfig, $this->order, $discountExcludesShipping));
            if ($discount > 0) {
                $orderTotal -= $discount;

                $fee = new \WC_Order_Item_Fee();
                $fee->set_name(__('Desconto para pagamento com Checkout PB', 'pagbank-connect'));
                $fee->set_amount(-$discount);
                $fee->set_total(-$discount);
                $fee->set_tax_class('');
                $fee->set_tax_status('none');
                $this->order->add_item($fee);
                $this->order->calculate_totals();

                $discountAmount = ['discount_amount' => (int) round($discount * 100)];
            }
        }

        if ($this->order->get_total_discount() > 0) {
            $discountToAdd = (int) round($this->order->get_total_discount() * 100);
            if (isset($discountAmount['discount_amount'])) {
                $discountToAdd += $discountAmount['discount_amount'];
            }
            $discountAmount = ['discount_amount' => $discountToAdd];
        }

        $recurringHelper = new RecurringHelper();
        if ($recurringHelper->orderHasUnsupportedCheckoutPbRecurrence($this->order)) {
            throw new \RM_PagBank\Connect\Exception([[
                'code' => 'CHECKOUT_PB_UNSUPPORTED_RECURRENCE',
                'description' => $recurringHelper->unsupportedCheckoutPbRecurrenceMessage(),
            ]], 40000);
        }
        $recurrence = $recurringHelper->buildCheckoutPbRecurrenceFromOrder($this->order);

        $paymentMethodCfg = Params::getCheckoutPbConfig('checkout_pb_payment_methods', ['CREDIT_CARD', 'PIX']);
        if (!is_array($paymentMethodCfg)) {
            $paymentMethodCfg = ['CREDIT_CARD', 'PIX'];
        }
        // Hosted recurrence is card-only (API contract).
        if ($recurrence !== null) {
            $paymentMethodCfg = ['CREDIT_CARD'];
        }
        $return['payment_methods'] = [];
        foreach ($paymentMethodCfg as $paymentMethod) {
            $paymentMethodObj = new PaymentMethod();
            $paymentMethodObj->setType($paymentMethod);
            $return['payment_methods'][] = $paymentMethodObj;
        }

        if (in_array('CREDIT_CARD', $paymentMethodCfg, true)) {
            $pmCfg = new PaymentMethodsConfigs();
            $pmCfg->setType('CREDIT_CARD');
            $installmentsLimit = Params::getCheckoutPbMaxInstallments();
            $interestFreeInstallments = Params::getCheckoutPbMaxInstallmentsNoInterest($orderTotal);
            $configOptions = [];
            if ($installmentsLimit) {
                $configOption = new PaymentMethodConfigOptions();
                $configOption->setOption(PaymentMethodConfigOptions::OPTION_INSTALLMENTS_LIMIT);
                $configOption->setValue(max($installmentsLimit, 1));
                $configOptions[] = $configOption;
            }
            if ($interestFreeInstallments > 1) {
                $configOption = new PaymentMethodConfigOptions();
                $configOption->setOption(PaymentMethodConfigOptions::OPTION_INTEREST_FREE_INSTALLMENTS);
                $configOption->setValue(max(1, $interestFreeInstallments));
                $configOptions[] = $configOption;
            }
            if ($configOptions) {
                $pmCfg->setConfigOptions($configOptions);
                $return['payment_methods_configs'] = [$pmCfg];
            }
        }

        $expireInMinutes = Params::getCheckoutPbConfig('checkout_pb_expiry_minutes', '120');
        $extra = [
            'customer_modifiable' => true,
            'reusable' => false,
            'return_url' => $this->order->get_checkout_order_received_url(),
            'redirect_url' => $this->order->get_checkout_order_received_url(),
            'expiration_date' => date('c', strtotime('+' . (int) $expireInMinutes . ' minutes')),
        ];

        $branding = $this->buildBranding();
        if ($branding) {
            $extra['branding'] = $branding;
        }
        $bg = trim((string) Params::getCheckoutPbConfig('checkout_pb_summary_bg_image', ''));
        if ($bg !== '' && preg_match('#^https://#i', $bg)) {
            $extra['extras'] = ['summary_background_image' => $bg];
        }
        $ga4 = $this->resolveGa4MeasurementId();
        if ($ga4) {
            $extra['analytics'] = ['ga4_measurement_id' => $ga4];
        }

        if ($recurrence !== null) {
            $extra['recurrence'] = $recurrence;
            $this->persistRecurringOrderMetaFromItems();
        }

        return array_merge($return, $extra, $discountAmount);
    }

    /**
     * Copy product recurring meta onto the order (cart path usually did this; one-click may not).
     * Sets _recurring_initial so processInitialResponse creates PB > Assinaturas on PAID.
     */
    protected function persistRecurringOrderMetaFromItems(): void
    {
        $recurringHelper = new RecurringHelper();
        foreach ($this->order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$recurringHelper->isProductRecurring($product)) {
                continue;
            }
            $recurringProduct = $recurringHelper->getRecurringProduct($product);
            if (!$recurringProduct) {
                continue;
            }
            $this->order->update_meta_data('_recurring_frequency', $recurringProduct->get_meta('_frequency'));
            $this->order->update_meta_data('_recurring_cycle', $recurringProduct->get_meta('_frequency_cycle'));
            $this->order->update_meta_data('_recurring_initial_fee', $recurringProduct->get_meta('_initial_fee'));
            $this->order->update_meta_data(
                '_recurring_discount_amount',
                $recurringProduct->get_meta('_recurring_discount_amount')
            );
            $this->order->update_meta_data(
                '_recurring_discount_cycles',
                $recurringProduct->get_meta('_recurring_discount_cycles')
            );
            $this->order->update_meta_data(
                '_recurring_max_cycles',
                $recurringProduct->get_meta('_recurring_max_cycles')
            );
            $trial = (int) $recurringHelper->getRecurringMeta($product, '_recurring_trial_length');
            if ($trial > 0) {
                $this->order->update_meta_data('_recurring_trial_length', $trial);
            }
            // Same as CC: Assinaturas row is created when charge becomes PAID.
            OrderMeta::set($this->order, '_recurring_initial', true);
            $this->order->save();
            break;
        }
    }

    /**
     * Shipping from Checkout PB gateway settings (default-checkout mode).
     *
     * @return Shipping|array<string, mixed>
     * @throws \Exception
     */
    protected function buildShippingFromGatewaySettings()
    {
        $mode = (string) Params::getCheckoutPbConfig('checkout_pb_shipping_mode', 'free');
        if ($mode === 'calculate') {
            return $this->buildCalculateShipping();
        }

        $shipping = new Shipping();
        $shipping->setAddressModifiable(true);

        if ($mode === 'fixed') {
            $raw = (string) Params::getCheckoutPbConfig('checkout_pb_shipping_fixed_amount', '0');
            $raw = str_replace(',', '.', trim($raw));
            $reais = is_numeric($raw) ? (float) $raw : 0.0;
            if ($reais > 0) {
                $shipping->setType(Shipping::TYPE_FIXED);
                $shipping->setAmount((int) round($reais * 100));
                return $shipping;
            }
        }

        $shipping->setType(Shipping::TYPE_FREE);
        $shipping->setAmount(0);

        return $shipping;
    }

    /**
     * FIXED/FREE from Woo order (cart checkout). Address locked.
     *
     * @return Shipping|null
     */
    protected function buildFixedShippingFromOrder()
    {
        if (!$this->order->needs_shipping_address() && !(float) $this->order->get_shipping_total()) {
            return null;
        }
        if (!$this->order->has_shipping_address() && !$this->order->get_shipping_method()) {
            return null;
        }

        $shipping = new Shipping();
        $shipping->setType(Shipping::TYPE_FREE);
        $shippingTotal = (float) $this->order->get_shipping_total();
        if ($shippingTotal > 0) {
            $shipping->setAmount((int) round($shippingTotal * 100));
            $shipping->setType(Shipping::TYPE_FIXED);
            $method = (string) $this->order->get_shipping_method();
            if (stripos($method, 'sedex') !== false) {
                $shipping->setServiceType(Shipping::SERVICE_TYPE_SEDEX);
            } elseif (stripos($method, 'pac') !== false) {
                $shipping->setServiceType(Shipping::SERVICE_TYPE_PAC);
            }
        } else {
            $shipping->setAmount(0);
        }

        if ($this->order->has_shipping_address()) {
            $shipping->setAddress($this->getShippingAddress());
        }
        $shipping->setAddressModifiable(false);

        return $shipping;
    }

    /**
     * CALCULATE shipping for one-click (Envio Fácil boxes + origin CEP).
     *
     * @return array<string, mixed>
     * @throws \Exception
     */
    protected function buildCalculateShipping(): array
    {
        if (!$this->orderNeedsPhysicalShipping()) {
            return ['type' => Shipping::TYPE_FREE, 'amount' => 0, 'address_modifiable' => true];
        }

        $origin = $this->getOriginPostalCode();
        if (strlen($origin) !== 8) {
            throw new \Exception(
                __('Configure o CEP de origem do Envio Fácil ou o CEP da loja para Compra com um clique.', 'pagbank-connect')
            );
        }

        $boxes = $this->getEnvioFacilBoxesPayload();
        if ($boxes === []) {
            throw new \Exception(
                __('Cadastre ao menos uma embalagem no Envio Fácil para Compra com um clique com frete.', 'pagbank-connect')
            );
        }

        return [
            'type' => Shipping::TYPE_CALCULATE,
            'origin_postal_code' => $origin,
            'boxes' => $boxes,
            'address_modifiable' => true,
        ];
    }

    protected function orderNeedsPhysicalShipping(): bool
    {
        foreach ($this->order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->needs_shipping()) {
                return true;
            }
        }
        return false;
    }

    protected function getOriginPostalCode(): string
    {
        $ef = get_option('woocommerce_rm_enviofacil_settings', []);
        $cep = '';
        if (is_array($ef) && !empty($ef['origin_postcode'])) {
            $cep = (string) $ef['origin_postcode'];
        }
        if ($cep === '') {
            $cep = (string) get_option('woocommerce_store_postcode', '');
        }
        return preg_replace('/\D+/', '', $cep) ?? '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getEnvioFacilBoxesPayload(): array
    {
        if (!class_exists('\\RM_PagBank\\Connect\\EnvioFacil\\Box')) {
            return [];
        }
        $boxManager = new \RM_PagBank\Connect\EnvioFacil\Box();
        $available = $boxManager->get_all_available();
        $boxes = [];
        foreach ($available as $b) {
            $boxes[] = [
                'reference' => $b->reference,
                'outerWidth' => (int) $b->outer_width,
                'outerLength' => (int) $b->outer_length,
                'outerDepth' => (int) $b->outer_depth,
                'emptyWeight' => (int) $b->empty_weight,
                'innerWidth' => (int) $b->inner_width,
                'innerLength' => (int) $b->inner_length,
                'innerDepth' => (int) $b->inner_depth,
                'maxWeight' => (int) $b->max_weight,
            ];
        }
        return $boxes;
    }

    /**
     * @param Item[]|array $items
     * @return Item[]
     */
    protected function enrichItemsWithMediaAndDims(array $items): array
    {
        $orderItems = [];
        foreach ($this->order->get_items() as $line) {
            $orderItems[(string) $line->get_product_id()] = $line;
            if ($line->get_variation_id()) {
                $orderItems[(string) $line->get_variation_id()] = $line;
            }
        }

        foreach ($items as $itemObj) {
            if (!$itemObj instanceof Item) {
                continue;
            }
            $ref = (string) $itemObj->getReferenceId();
            $line = $orderItems[$ref] ?? null;
            if (!$line) {
                continue;
            }
            $product = $line->get_product();
            if (!$product) {
                continue;
            }

            $imageUrl = $this->resolveProductImageUrl($product);
            if ($imageUrl) {
                $itemObj->setImageUrl($imageUrl);
            }

            $dims = $this->productDimsMmGrams($product);
            if ($dims) {
                $itemObj->setWidth($dims['width']);
                $itemObj->setLength($dims['length']);
                $itemObj->setDepth($dims['depth']);
                $itemObj->setWeight($dims['weight']);
            }
        }

        return $items;
    }

    /**
     * @param \WC_Product $product
     */
    protected function resolveProductImageUrl($product): ?string
    {
        $id = $product->get_image_id();
        if (!$id && $product->get_parent_id()) {
            $parent = wc_get_product($product->get_parent_id());
            $id = $parent ? $parent->get_image_id() : 0;
        }
        if (!$id) {
            return null;
        }
        $url = wp_get_attachment_image_url((int) $id, 'woocommerce_thumbnail');
        if (!$url || !is_string($url)) {
            return null;
        }
        // Prefer https
        if (strpos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }
        if (!Params::isAllowedCheckoutImageUrl($url)) {
            return null;
        }
        return $url;
    }

    /**
     * @param \WC_Product $product
     * @return array{width:int,length:int,depth:int,weight:int}|null
     */
    protected function productDimsMmGrams($product): ?array
    {
        $dims = $product->get_dimensions(false);
        $w = (float) ($dims['width'] ?? 0);
        $l = (float) ($dims['length'] ?? 0);
        $h = (float) ($dims['height'] ?? 0);
        $weight = (float) $product->get_weight();
        if ($w <= 0 || $l <= 0 || $h <= 0 || $weight <= 0) {
            return null;
        }

        $dimUnit = get_option('woocommerce_dimension_unit', 'cm');
        $toMm = static function (float $v) use ($dimUnit): int {
            switch ($dimUnit) {
                case 'm':
                    return (int) round($v * 1000);
                case 'mm':
                    return (int) round($v);
                case 'in':
                    return (int) round($v * 25.4);
                case 'yd':
                    return (int) round($v * 914.4);
                case 'cm':
                default:
                    return (int) round($v * 10);
            }
        };

        $kg = Functions::convertToKg($weight);

        return [
            'width' => $toMm($w),
            'length' => $toMm($l),
            'depth' => $toMm($h),
            'weight' => max(1, (int) round($kg * 1000)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildBranding(): ?array
    {
        $name = trim((string) Params::getCheckoutPbConfig('checkout_pb_brand_name', ''));
        if ($name === '') {
            $name = get_bloginfo('name');
        }
        $theme = (string) Params::getCheckoutPbConfig('checkout_pb_theme', 'painel');
        if (!in_array($theme, ['painel', 'coluna', 'central'], true)) {
            $theme = 'painel';
        }
        $colors = array_values(array_filter([
            (string) Params::getCheckoutPbConfig('checkout_pb_color_button', '#171717'),
            (string) Params::getCheckoutPbConfig('checkout_pb_color_summary', '#171717'),
            (string) Params::getCheckoutPbConfig('checkout_pb_color_background', '#f5f5f4'),
            (string) Params::getCheckoutPbConfig('checkout_pb_color_text', '#171717'),
        ]));
        if ($theme === 'coluna') {
            $colors = array_slice($colors, 0, 2);
        } elseif ($theme === 'central') {
            $colors = [
                $colors[0] ?? '#171717',
                $colors[2] ?? '#f5f5f4',
                $colors[3] ?? '#171717',
            ];
        }

        $branding = [
            'name' => mb_substr($name, 0, 80),
            'theme' => $theme,
            'colors' => $colors,
        ];
        $logo = trim((string) Params::getCheckoutPbConfig('checkout_pb_logo_url', ''));
        if ($logo !== '' && Params::isAllowedCheckoutImageUrl($logo)) {
            $branding['logo_url'] = $logo;
        }
        return $branding;
    }

    protected function resolveGa4MeasurementId(): ?string
    {
        $configured = trim((string) Params::getCheckoutPbConfig('checkout_pb_ga4', ''));
        if ($configured !== '' && preg_match('/^G-[A-Z0-9]+$/i', $configured)) {
            return strtoupper($configured);
        }
        // Google for WooCommerce / Site Kit common options
        $candidates = [
            get_option('woocommerce_google_analytics_settings'),
            get_option('google_analytics_settings'),
        ];
        foreach ($candidates as $opt) {
            if (!is_array($opt)) {
                continue;
            }
            foreach (['ga_id', 'google_analytics_id', 'measurement_id', 'tracking_id'] as $key) {
                $v = trim((string) ($opt[$key] ?? ''));
                if (preg_match('/^G-[A-Z0-9]+$/i', $v)) {
                    return strtoupper($v);
                }
            }
        }
        return null;
    }

    public function getThankyouInstructions($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        // Paid / completed: do not show the hosted-checkout pay CTA.
        if ($order->is_paid() || !in_array($order->get_status(), ['pending', 'on-hold'], true)) {
            return;
        }

        $redirect_link = OrderMeta::get($order, 'redirect_url');
        if (empty($redirect_link)) {
            return;
        }

        $checkout_title = (string) Params::getCheckoutPbConfig(
            'title',
            __('Checkout externo', 'pagbank-connect')
        );
        if ($checkout_title === '') {
            $checkout_title = __('Checkout externo', 'pagbank-connect');
        }

        require_once dirname(__FILE__) . '/../../templates/checkout-pb-instructions.php';
    }
}
