<?php

namespace RM_PagBank\Tests\Partner;

use PHPUnit\Framework\TestCase;
use RM_PagBank\Partner\Partner;
use RM_PagBank\Partner\PartnerDetector;
use RM_PagBank\Helpers\Api;

final class PartnerDetectorTest extends TestCase
{
    public function testDetectsVindiProduction(): void
    {
        $d = PartnerDetector::fromKey('CONVD30ABCDEF0123456789ABCDEF0123456789');
        self::assertTrue($d->isVindi());
        self::assertFalse($d->isSandbox());
        self::assertSame('30dias', $d->getTemplateCode());
        self::assertSame(Partner::VINDI, $d->getPartner());
    }

    public function testDetectsVindiSandbox(): void
    {
        $d = PartnerDetector::fromKey('CONVDSANDBOX14ABCDEF0123456789ABCDEF01');
        self::assertTrue($d->isVindi());
        self::assertTrue($d->isSandbox());
        self::assertSame('14dias', $d->getTemplateCode());
    }

    public function testDetectsPagBankSandbox(): void
    {
        $d = PartnerDetector::fromKey('CONSANDBOXABCDEF0123456789ABCDEF012345');
        self::assertTrue($d->isPagBank());
        self::assertTrue($d->isSandbox());
    }

    public function testMapEndpointForVindi(): void
    {
        $ref = new \ReflectionClass(Api::class);
        /** @var Api $api */
        $api = $ref->newInstanceWithoutConstructor();
        $api->setConnectKey('CONVD30ABCDEF0123456789ABCDEF0123456789');
        self::assertSame('orders', $api->mapEndpoint('ws/orders'));
        self::assertSame('charges/fees/calculate', $api->mapEndpoint('ws/charges/fees/calculate'));
        self::assertSame('charges/abc/cancel', $api->mapEndpoint('ws/charges/abc/cancel'));
        self::assertSame('orders/pb_ord_1', $api->mapEndpoint('ws/orders/pb_ord_1'));
    }

    public function testMapEndpointKeepsPagBankPaths(): void
    {
        $ref = new \ReflectionClass(Api::class);
        /** @var Api $api */
        $api = $ref->newInstanceWithoutConstructor();
        $api->setConnectKey('CONABCDEF0123456789ABCDEF0123456789AB');
        self::assertSame('ws/orders', $api->mapEndpoint('ws/orders'));
    }
}
