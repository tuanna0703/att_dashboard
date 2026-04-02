<?php

namespace App\Traits;

/**
 * Auto-generate a sequential code (e.g. CR-2026-0001) on creating.
 *
 * Usage:
 *   use GeneratesCode;
 *   const CODE_PREFIX = 'CR';
 */
trait GeneratesCode
{
    public static function generateCode(string $prefix): string
    {
        $year = now()->format('Y');
        $lastCode = static::withTrashed()
            ->where('code', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('id')
            ->value('code');

        $next = $lastCode
            ? (int) substr($lastCode, -4) + 1
            : 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $next);
    }

    protected static function bootGeneratesCode(): void
    {
        static::creating(function ($model) {
            $codeField = defined(static::class . '::CODE_FIELD')
                ? static::CODE_FIELD
                : 'code';

            if (empty($model->{$codeField})) {
                $model->{$codeField} = static::generateCode(static::CODE_PREFIX);
            }
        });
    }
}
