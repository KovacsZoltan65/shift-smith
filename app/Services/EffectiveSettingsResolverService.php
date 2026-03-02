<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CompanySetting\EffectiveSettingData;
use App\Interfaces\AppSettingRepositoryInterface;
use App\Interfaces\CompanySettingRepositoryInterface;
use App\Interfaces\UserSettingRepositoryInterface;
use App\Services\Cache\CacheVersionService;

class EffectiveSettingsResolverService
{
    public function __construct(
        private readonly AppSettingRepositoryInterface $appSettings,
        private readonly CompanySettingRepositoryInterface $companySettings,
        private readonly UserSettingRepositoryInterface $userSettings,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function getEffectiveValue(string $key, int $companyId, ?int $userId): EffectiveSettingData
    {
        return $this->getEffectiveMany([$key], $companyId, $userId)[0];
    }

    /**
     * @param list<string> $keys
     * @return list<EffectiveSettingData>
     */
    public function getEffectiveMany(array $keys, int $companyId, ?int $userId): array
    {
        $keys = array_values(array_unique(array_filter(array_map(
            static fn ($key): string => trim((string) $key),
            $keys
        ))));

        if ($keys === []) {
            return [];
        }

        $cacheVersion = $this->cacheVersionService->get("effective_settings:{$companyId}:all");
        $legacyVersion = $this->cacheVersionService->get('landlord:app_settings.show');
        $cacheKey = 'v'.$cacheVersion.':'.$legacyVersion.':'.hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR));

        /** @var list<EffectiveSettingData> */
        return $this->cacheService->remember(
            tag: "effective_settings:{$companyId}:".($userId ?? 0),
            key: $cacheKey,
            callback: fn (): array => $this->resolveMany($keys, $companyId, $userId),
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    /**
     * @param list<string> $keys
     * @return list<EffectiveSettingData>
     */
    private function resolveMany(array $keys, int $companyId, ?int $userId): array
    {
        $allowLegacy = $this->legacyFallbackEnabled();
        $appRows = [];
        foreach ($keys as $key) {
            $appRows[$key] = $this->appSettings->findByKey($key);
        }

        $companyRows = [];
        foreach ($keys as $key) {
            $companyRows[$key] = $this->companySettings->findByKeyInCompany($key, $companyId);
        }

        $userScopedRows = $userId !== null
            ? $this->userSettings->findManyByUserCompanyKeys($userId, $companyId, $keys)->keyBy('key')
            : collect();
        $userLegacyRows = $userId !== null && $allowLegacy
            ? $this->userSettings->findManyLegacyByUserKeys($userId, $keys)->keyBy('key')
            : collect();

        $resolved = [];
        foreach ($keys as $key) {
            $userScoped = $userScopedRows->get($key);
            $userLegacy = $userLegacyRows->get($key);
            $company = $companyRows[$key];
            $app = $appRows[$key];

            $source = 'none';
            $value = null;

            if ($userScoped !== null) {
                $source = 'user';
                $value = $userScoped->value;
            } elseif ($userLegacy !== null) {
                $source = 'user_legacy';
                $value = $userLegacy->value;
            } elseif ($company !== null) {
                $source = 'company';
                $value = $company->value;
            } elseif ($app !== null) {
                $source = 'app';
                $value = $app->value;
            }

            $meta = $company ?? $app;

            $resolved[] = new EffectiveSettingData(
                key: $key,
                effective_value: $value,
                source: $source,
                type: $meta?->type,
                group: $meta?->group,
                label: $meta?->label,
                description: $meta?->description,
                company_id: $companyId,
                user_id: $userId,
            );
        }

        return $resolved;
    }

    private function legacyFallbackEnabled(): bool
    {
        $flag = $this->appSettings->findByKey('settings.user_legacy_global_override_enabled');

        return (bool) ($flag?->value ?? false);
    }
}
