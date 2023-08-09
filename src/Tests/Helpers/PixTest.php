<?php

namespace RM_PagBank\Tests\Helpers;

use OrderMock;
use RM_PagBank\Helpers\Pix;
use PHPUnit\Framework\TestCase;

class PixTest extends TestCase
{

    public function testExtractPixRequestParams()
    {
        $orderData = include dirname(__FILE__) .  '/../Mocks/SampleData/OrderMock1.php';
        $order = new OrderMock($orderData);
        $this->assertTrue(true);
    }
}
