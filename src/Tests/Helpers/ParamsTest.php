<?php

namespace RM_PagSeguro\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use RM_PagSeguro\Helpers\Params;

/**
 * Class ParamsTest
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagSeguro\Tests\Helpers
 * @covers \RM_PagSeguro\Helpers\Params
 */
class ParamsTest extends TestCase
{

    public function testConvert_to_cents()
    {
        $this->assertEquals('0', Params::convert_to_cents(null));
        $this->assertEquals('0', Params::convert_to_cents(''));
        $this->assertEquals('0', Params::convert_to_cents(0));
        $this->assertEquals('95', Params::convert_to_cents(0.95));
        $this->assertEquals('100', Params::convert_to_cents(1));
        $this->assertEquals('110', Params::convert_to_cents(1.1));
        $this->assertEquals('115', Params::convert_to_cents(1.15));
        $this->assertEquals('115013', Params::convert_to_cents(1150.13));
        $this->assertEquals('1215013', Params::convert_to_cents(12150.13));
    }

    public function testExtract_phone()
    {

    }
}
