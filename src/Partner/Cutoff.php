<?php

namespace RM_PagBank\Partner;

/**
 * Migration cutoff — display/CTA only. Server enforces the real cutover.
 * Mirror of pbintegracoes.com.novo/lib/cutoff.ts
 */
final class Cutoff
{
    const ISO = '2026-11-05T00:00:00-03:00';
    const LABEL = '5 de novembro de 2026, 00:00 (horário de Brasília)';
    const SHORT = '5/nov/2026';
    const MIGRATE_URL = 'https://pbintegracoes.com/migrar';

    public static function timestamp(): int
    {
        $t = strtotime(self::ISO);
        return $t ? (int) $t : 0;
    }

    public static function isPast(): bool
    {
        $t = self::timestamp();
        return $t > 0 && time() >= $t;
    }

    /** Seconds until cutoff (0 if past). */
    public static function secondsRemaining(): int
    {
        $t = self::timestamp();
        if ($t <= 0) {
            return 0;
        }
        return max(0, $t - time());
    }
}
