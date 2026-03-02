<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CompanySetting\EffectiveSettingData;
use App\Repositories\SettingsRepository;
use App\Services\Cache\CacheVersionService;

class SettingsResolverService
{
    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly EffectiveSettingsResolverService $effectiveResolver,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService
    ) {}

    /**
     * @param array{company_id?:int|null,user_id?:int|null} $context
     * @return array{
     *   key:string,
     *   value:mixed,
     *   source:'user'|'company'|'app'|'default',
     *   inherited:bool,
     *   overridden:bool
     * }
     */
    public function effectiveValue(string $key, array $context = []): array
    {
        $companyId = isset($context['company_id']) ? (int) $context['company_id'] : null;
        $userId = isset($context['user_id']) ? (int) $context['user_id'] : null;
        $resolved = $companyId !== null
            ? $this->effectiveResolver->getEffectiveValue($key, $companyId, $userId)
            : $this->resolveAppOnly($key);

        return $this->formatResolvedValue($key, $resolved, $companyId, $userId);
    }

    /**
     * @return array<string,mixed>
     */
    public function effectiveMap(?int $companyId = null, ?int $userId = null): array
    {
        $metaVersion = $this->cacheVersionService->get('settings.meta');
        $appVersion = $this->cacheVersionService->get('settings.app');
        $companyVersion = $companyId !== null ? $this->cacheVersionService->get("settings.company.{$companyId}") : 0;
        $userVersion = $userId !== null ? $this->cacheVersionService->get("settings.user.{$userId}") : 0;

        $cacheKey = sprintf(
            'settings_effective_%s_%s_v%s_%s_%s_%s',
            $companyId ?? 0,
            $userId ?? 0,
            $metaVersion,
            $appVersion,
            $companyVersion,
            $userVersion
        );

        $tag = $userId !== null
            ? "settings_user_{$userId}"
            : ($companyId !== null ? "settings_company_{$companyId}" : 'settings_app');

        return $this->cacheService->remember(
            tag: $tag,
            key: $cacheKey,
            callback: function () use ($companyId, $userId): array {
                $metaRows = $this->repository->meta();
                $keys = $metaRows->pluck('key')->map(static fn ($k): string => (string) $k)->values()->all();
                $resolvedRows = $companyId !== null
                    ? $this->effectiveResolver->getEffectiveMany($keys, $companyId, $userId)
                    : array_map(fn (string $key): EffectiveSettingData => $this->resolveAppOnly($key), $keys);

                $out = [];
                $resolvedByKey = [];
                foreach ($resolvedRows as $row) {
                    $resolvedByKey[$row->key] = $row;
                }

                foreach ($metaRows as $meta) {
                    $k = (string) $meta->key;
                    $row = $resolvedByKey[$k] ?? null;

                    if ($row instanceof EffectiveSettingData && $row->source !== 'none') {
                        $out[$k] = $row->effective_value;
                        continue;
                    }

                    $out[$k] = $meta->default_value;
                }

                return $out;
            },
            ttl: (int) config('cache.ttl_fetch', 300)
        );
    }

    private function resolveAppOnly(string $key): EffectiveSettingData
    {
        $appValues = $this->repository->appValuesByKeys([$key]);

        return new EffectiveSettingData(
            key: $key,
            effective_value: $appValues[$key] ?? null,
            source: array_key_exists($key, $appValues) ? 'app' : 'none',
            type: null,
            group: null,
            label: null,
            description: null,
            company_id: 0,
            user_id: null,
        );
    }

    /**
     * @return array{
     *   key:string,
     *   value:mixed,
     *   source:'user'|'user_legacy'|'company'|'app'|'default',
     *   inherited:bool,
     *   overridden:bool
     * }
     */
    private function formatResolvedValue(string $key, EffectiveSettingData $resolved, ?int $companyId, ?int $userId): array
    {
        if ($resolved->source !== 'none') {
            $source = $resolved->source === 'user_legacy' ? 'user' : $resolved->source;
            $inherited = match ($resolved->source) {
                'user', 'user_legacy' => false,
                'company' => $userId !== null,
                'app' => $companyId !== null || $userId !== null,
                default => true,
            };

            return [
                'key' => $key,
                'value' => $resolved->effective_value,
                'source' => $source,
                'inherited' => $inherited,
                'overridden' => true,
            ];
        }

        $meta = $this->repository->metaByKey($key);

        return [
            'key' => $key,
            'value' => $meta?->default_value,
            'source' => 'default',
            'inherited' => $companyId !== null || $userId !== null || $meta !== null,
            'overridden' => false,
        ];
    }
}
