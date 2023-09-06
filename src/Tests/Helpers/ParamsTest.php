<?php

namespace RM_PagBank\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use RM_PagBank\Helpers\Params;
use WC_Helper_Order;

/**
 * Class ParamsTest
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Tests\Helpers
 * @covers \RM_PagBank\Helpers\Params
 */
class ParamsTest extends \WP_UnitTestCase
{

    public function testConvertToCents()
    {
        $this->assertEquals('0', Params::convertToCents(null));
        $this->assertEquals('0', Params::convertToCents(''));
        $this->assertEquals('0', Params::convertToCents(0));
        $this->assertEquals('95', Params::convertToCents(0.95));
        $this->assertEquals('100', Params::convertToCents(1));
        $this->assertEquals('110', Params::convertToCents(1.1));
        $this->assertEquals('115', Params::convertToCents(1.15));
        $this->assertEquals('115013', Params::convertToCents(1150.13));
        $this->assertEquals('1215013', Params::convertToCents(12150.13));
    }

	public function testExtractPhone()
	{
		$order = WC_Helper_Order::create_order();

		$order->set_billing_phone('11 99999-9999');
		$phone = Params::extractPhone($order);
		$this->assertEquals($phone['area'], '11');
		$this->assertEquals($phone['number'], '999999999');
		$this->assertEquals($phone['type'], 'MOBILE');

		$order->set_billing_phone('12  31130011 ');
		$phone = Params::extractPhone($order);
		$this->assertEquals($phone['area'], '12');
		$this->assertEquals($phone['number'], '31130011');
		$this->assertEquals($phone['type'], 'HOME');
	}

	public function testGetMaxInstallments()
	{
		//TODO find a way to mock wp_options or change its value OR convert to non-static method and use normal Mocks
//		global $wpdb;
//		$wpdb->insert($wpdb->options, [
//			'option_name' => 'woocommerce_rm-pagbank_settings',
//			'option_value' => serialize(['cc_installments_options_max_installments' => 15]),
//			'autoload' => 'yes'
//		]);
//
//		$this->assertEquals(15, Params::getMaxInstallments());
	}
}
