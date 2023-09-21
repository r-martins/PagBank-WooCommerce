<?php

namespace RM_PagBank\Tests\Connect\Payments;

use RM_PagBank\Connect\Payments\Pix;
use WC_Helper_Order;

class PixTest extends \WP_UnitTestCase
{

    /**
     * @covers \RM_PagBank\Connect\Payments\Pix::prepare
     * @return void
     */
    public function testPrepare()
    {
        $order = WC_Helper_Order::create_order();
        $pix = new Pix($order);
        $params = $pix->prepare();

		$this->assertArrayHasKey('qr_codes', $params);
        $this->assertTrue(true);
    }
}
