<?php

namespace RM_PagBank\Tests\Helpers;

use RM_PagBank\Helpers\OrderMeta;
use RM_PagBank\Partner\Partner;

/**
 * @covers \RM_PagBank\Helpers\OrderMeta
 */
class OrderMetaTest extends \WP_UnitTestCase
{
    public function testToLogicalStripsBothBrands(): void
    {
        $this->assertSame('order_id', OrderMeta::toLogical('pagbank_order_id'));
        $this->assertSame('order_id', OrderMeta::toLogical('pb_order_id'));
        $this->assertSame('_card_brand', OrderMeta::toLogical('_pagbank_card_brand'));
        $this->assertSame('_card_brand', OrderMeta::toLogical('_pb_card_brand'));
        $this->assertSame('order_id', OrderMeta::toLogical('order_id'));
    }

    public function testPhysicalKeyByPartner(): void
    {
        $this->assertSame(
            'pagbank_order_id',
            OrderMeta::physicalKey('order_id', Partner::PAGBANK)
        );
        $this->assertSame(
            'pb_order_id',
            OrderMeta::physicalKey('order_id', Partner::VINDI)
        );
        $this->assertSame(
            '_pagbank_card_brand',
            OrderMeta::physicalKey('_card_brand', Partner::PAGBANK)
        );
        $this->assertSame(
            '_pb_card_brand',
            OrderMeta::physicalKey('_card_brand', Partner::VINDI)
        );
    }

    public function testWriteAndReadWithFallback(): void
    {
        $order = \WC_Helper_Order::create_order();

        OrderMeta::set($order, 'partner', Partner::VINDI, true, Partner::VINDI);
        OrderMeta::set($order, 'order_id', 'ORDE_VINDI_1', true, Partner::VINDI);
        $order->save();

        $this->assertTrue($order->meta_exists('pb_order_id'));
        $this->assertFalse($order->meta_exists('pagbank_order_id'));
        $this->assertSame('ORDE_VINDI_1', OrderMeta::get($order, 'order_id'));

        // Legacy PagBank-only meta still readable when partner unknown on a fresh order.
        $legacy = \WC_Helper_Order::create_order();
        $legacy->update_meta_data('pagbank_order_id', 'ORDE_LEGACY');
        $legacy->save();
        $this->assertSame('ORDE_LEGACY', OrderMeta::get($legacy, 'order_id'));
    }

    public function testMetaQueryEqualsCoversBothKeys(): void
    {
        $clause = OrderMeta::metaQueryEquals('payment_method', 'pix');
        $this->assertSame('OR', $clause['relation']);
        $keys = [];
        foreach ($clause as $item) {
            if (is_array($item) && isset($item['key'])) {
                $keys[] = $item['key'];
            }
        }
        $this->assertContains('pb_payment_method', $keys);
        $this->assertContains('pagbank_payment_method', $keys);
    }
}
