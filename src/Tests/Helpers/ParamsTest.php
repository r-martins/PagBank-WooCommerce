<?php

namespace RM_PagBank\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use RM_PagBank\Helpers\Params;

/**
 * Class ParamsTest
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Tests\Helpers
 * @covers \RM_PagBank\Helpers\Params
 */
class ParamsTest extends TestCase
{

    public function testConvert_to_cents()
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

}
