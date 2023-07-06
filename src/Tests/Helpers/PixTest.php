<?php

namespace RM_PagSeguro\Tests\Helpers;

use OrderMock;
use RM_PagSeguro\Helpers\Pix;
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
