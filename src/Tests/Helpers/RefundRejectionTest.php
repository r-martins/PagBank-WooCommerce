<?php

namespace RM_PagBank\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use RM_PagBank\Helpers\Api;

final class RefundRejectionTest extends TestCase
{
    public function testRejectedVindiRefundIsNotSuccess(): void
    {
        $detail = Api::refundRejectionDetail([
            'id' => '560621759',
            'status' => 'CANCELED',
            'partner' => 'vindi',
            'refund' => [
                'charge' => [
                    'id' => 560621759,
                    'status' => 'paid',
                    'last_transaction' => [
                        'transaction_type' => 'refund',
                        'status' => 'rejected',
                        'gateway_message' => 'Não foi possível gerar o token de acesso.',
                        'gateway_response_code' => '060002',
                    ],
                ],
            ],
        ]);

        self::assertSame('Não foi possível gerar o token de acesso. (060002)', $detail);
    }

    public function testSuccessfulVindiRefundIsNotRejected(): void
    {
        self::assertNull(Api::refundRejectionDetail([
            'status' => 'CANCELED',
            'partner' => 'vindi',
            'refund' => [
                'charge' => [
                    'status' => 'canceled',
                    'last_transaction' => [
                        'transaction_type' => 'refund',
                        'status' => 'success',
                    ],
                ],
            ],
        ]));
    }

    public function testPagBankCancelWithoutRefundNodeIsNotRejected(): void
    {
        self::assertNull(Api::refundRejectionDetail([
            'id' => 'CHAR_123',
            'status' => 'CANCELED',
            'amount' => ['summary' => ['refunded' => 600]],
        ]));
    }
}
