<?php

declare(strict_types=1);

namespace App\Services\Org;

use Illuminate\Support\Str;

final class PositionNormalizer
{
    public static function key(string $position): string
    {
        $normalized = trim($position);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = Str::ascii($normalized);
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = preg_replace('/[^a-z0-9 ]+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}

