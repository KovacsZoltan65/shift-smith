<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserMenuStat;
use Carbon\CarbonImmutable;

final class UserMenuStatRepository
{
    /**
     * @return array<int, string>
     */
    public function getOrderedMenuKeysByUserId(int $userId): array
    {
        return UserMenuStat::query()
            ->where('user_id', $userId)
            ->orderByDesc('hit_count')
            ->orderByDesc('last_used_at')
            ->pluck('menu_key')
            ->values()
            ->all();
    }

    public function incrementUsage(int $userId, string $menuKey): void
    {
        $stat = UserMenuStat::query()->firstOrCreate(
            ['user_id' => $userId, 'menu_key' => $menuKey],
            ['hit_count' => 0, 'last_used_at' => CarbonImmutable::now()]
        );

        $stat->increment('hit_count');
        $stat->forceFill(['last_used_at' => CarbonImmutable::now()])->save();
    }
}
