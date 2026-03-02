<?php

declare(strict_types=1);

namespace App\Facades;

use App\Data\Settings\EffectiveSettingDTO;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static int getInt(string $key, int $default = 0)
 * @method static bool getBool(string $key, bool $default = false)
 * @method static string getString(string $key, string $default = '')
 * @method static EffectiveSettingDTO getEffective(string $key, mixed $default = null)
 * @method static array<string, mixed> getMany(array $keys)
 * @method static void flushCache(?int $companyId = null, ?int $userId = null)
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'settings.manager';
    }
}
