<?php

declare(strict_types=1);

namespace App\Services\Menu;

use App\Repositories\UserMenuStatRepository;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Support\Facades\DB;

final class MenuContextService
{
    private const NS_MENU_CONTEXT = 'menu.context';

    public function __construct(
        private readonly UserMenuStatRepository $repository,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {}

    /**
     * @return array<int, string>
     */
    public function getMenuOrderForUser(int $userId): array
    {
        $enabled = (bool) config('cache.enable_menu', false);
        if (! $enabled) {
            return $this->repository->getOrderedMenuKeysByUserId($userId);
        }

        $version = $this->cacheVersionService->get(self::NS_MENU_CONTEXT);
        $key = "v{$version}:menu_order:user:{$userId}";
        $ttl = (int) config('cache.menu_refresh_second', 60);

        /** @var array<int, string> $menuOrder */
        $menuOrder = $this->cacheService->remember(
            tag: self::NS_MENU_CONTEXT,
            key: $key,
            callback: fn (): array => $this->repository->getOrderedMenuKeysByUserId($userId),
            ttl: $ttl
        );

        return $menuOrder;
    }

    public function trackMenuUsage(int $userId, string $menuKey): void
    {
        DB::transaction(function () use ($userId, $menuKey): void {
            $this->repository->incrementUsage($userId, $menuKey);

            DB::afterCommit(function (): void {
                $this->cacheVersionService->bump(self::NS_MENU_CONTEXT);
            });
        });
    }
}
