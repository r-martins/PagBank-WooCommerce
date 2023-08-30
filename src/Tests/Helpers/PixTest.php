<?php

namespace RM_PagBank\Tests\Helpers;

use RM_PagBank\Connect\Payments\Pix;
use PHPUnit\Framework\TestCase;
use WC_Helper_Order;
use WC_Order;
use WP_UnitTestCase;
use WP_UnitTestCase_Base;


class PixTest extends \WP_UnitTestCase
{

    /**
     * @covers \RM_PagBank\Helpers\Pix::extractPixRequestParams
     * @return void
     */
    public function testExtractPixRequestParams()
    {
        $order = WC_Helper_Order::create_order();
        $pix = new Pix($order);
        $params = $pix->prepare();

		$this->assertArrayHasKey('qr_codes', $params);
        $this->assertTrue(true);
    }
}
