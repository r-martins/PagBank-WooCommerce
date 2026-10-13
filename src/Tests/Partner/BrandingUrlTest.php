<?php

namespace RM_PagBank\Tests\Partner;

use PHPUnit\Framework\TestCase;
use RM_PagBank\Partner\Branding;
use RM_PagBank\Partner\PartnerDetector;

final class BrandingUrlTest extends TestCase
{
    public function testYapaySaleUrlUsesSandboxHost(): void
    {
        $branding = new Branding(PartnerDetector::fromKey('CONVDSANDBOX30ABCDEF0123456789ABCDEF'));
        self::assertSame(
            'https://intermediador-sandbox.yapay.com.br/orders/288736899/sale',
            $branding->yapaySaleUrl('288736899', true)
        );
    }

    public function testYapaySaleUrlUsesProductionHost(): void
    {
        $branding = new Branding(PartnerDetector::fromKey('CONVD30ABCDEF0123456789ABCDEF0123456789'));
        self::assertSame(
            'https://intermediador.yapay.com.br/orders/288736899/sale',
            $branding->yapaySaleUrl('288736899', false)
        );
    }

    public function testPagBankHasNoYapayUrl(): void
    {
        $branding = new Branding(PartnerDetector::fromKey('CONSANDBOXABCDEF0123456789ABCDEF012345'));
        self::assertNull($branding->yapaySaleUrl('288736899', true));
    }
}
