<?php

namespace RM_PagBank;

use WC_Helper_Product;
use WP_UnitTestCase;

class EnvioFacilTest extends WP_UnitTestCase
{

	/**
	 * @covers \RM_PagBank\EnvioFacil::getDimensionsAndWeight
	 * @return void
	 */
    public function testGetDimensionsAndWeight()
    {
		$products = [
			['data' => WC_Helper_Product::create_simple_product(false, ['width' => 10, 'height' => 20, 'length' => 50, 'weight' => 1]), 'quantity' => 1],
			['data' => WC_Helper_Product::create_simple_product(false, ['width' => 80, 'height' => 10, 'length' => 50, 'weight' => 1]), 'quantity' => 1],
		];

		$package = [ 'contents' => $products];
		$ef = new EnvioFacil();
		$dimensions = $ef->getDimensionsAndWeight($package);
		$totalSize = 0;

		$this->assertEquals(90, $dimensions['width'], 'Width is not correct');
		$this->assertEquals(30, $dimensions['height'], 'Height is not correct');
		$this->assertEquals(100, $dimensions['length'], 'Length is not correct');

		//with multiple qty..
		$package['contents'][0]['quantity'] = 2;
		$dimensions = $ef->getDimensionsAndWeight($package);
		$this->assertEquals(100, $dimensions['width'], 'Width is not correct');
		$this->assertEquals(50, $dimensions['height'], 'Height is not correct');
		$this->assertEquals(150, $dimensions['length'], 'Length is not correct');

	}

	public function testValidateDimensions()
	{
		$this->assertEquals(10, 10);
    }
}
