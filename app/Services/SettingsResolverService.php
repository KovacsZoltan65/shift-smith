<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use App\Services\Cache\CacheVersionService;

class SettingsResolverService
{
    public function __construct(
        private readonly SettingsRepository $repository,
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

        if ($userId !== null) {
            $userValue = $this->repository->userValue($userId, $key);
            if ($userValue !== null) {
                return [
                    'key' => $key,
                    'value' => $userValue,
                    'source' => 'user',
                    'inherited' => false,
                    'overridden' => true,
                ];
            }
        }

        if ($companyId !== null) {
            $companyValue = $this->repository->companyValue($companyId, $key);
            if ($companyValue !== null) {
                return [
                    'key' => $key,
                    'value' => $companyValue,
                    'source' => 'company',
                    'inherited' => $userId !== null,
                    'overridden' => true,
                ];
            }
        }

        $appValue = $this->repository->appValue($key);
        if ($appValue !== null) {
            return [
                'key' => $key,
                'value' => $appValue,
                'source' => 'app',
                'inherited' => $companyId !== null || $userId !== null,
                'overridden' => true,
            ];
        }

        $meta = $this->repository->metaByKey($key);

        return [
            'key' => $key,
            'value' => $meta?->default_value,
            'source' => 'default',
            'inherited' => true,
            'overridden' => false,
        ];
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

                $app = $this->repository->appValuesByKeys($keys);
                $company = $companyId !== null ? $this->repository->companyValuesByKeys($companyId, $keys) : [];
                $user = $userId !== null ? $this->repository->userValuesByKeys($userId, $keys) : [];

                $out = [];
                foreach ($metaRows as $meta) {
                    $k = (string) $meta->key;
                    if (array_key_exists($k, $user)) {
                        $out[$k] = $user[$k];
                        continue;
                    }
                    if (array_key_exists($k, $company)) {
                        $out[$k] = $company[$k];
                        continue;
                    }
                    if (array_key_exists($k, $app)) {
                        $out[$k] = $app[$k];
                        continue;
                    }
                    $out[$k] = $meta->default_value;
                }

                return $out;
            },
            ttl: (int) config('cache.ttl_fetch', 300)
        );
    }
}

