<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\CompanySetting\EffectiveSettingData;
use App\Data\Settings\EffectiveSettingDTO;
use App\Interfaces\AppSettingRepositoryInterface;
use App\Repositories\SettingsRepository;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use App\Services\Company\CurrentCompanyResolver;
use App\Services\EffectiveSettingsResolverService;
use Illuminate\Support\Facades\Auth;

class SettingsManager
{
    public function __construct(
        private readonly EffectiveSettingsResolverService $resolver,
        private readonly AppSettingRepositoryInterface $appSettings,
        private readonly SettingsRepository $settingsRepository,
        private readonly CurrentCompanyResolver $currentCompanyResolver,
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $hasExplicitDefault = func_num_args() > 1;

        return $this->resolveOne($key, $default, $hasExplicitDefault)->value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return $this->toInt($this->get($key, $default), $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return $this->toBool($this->get($key, $default), $default);
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        if ($value === null) {
            return $default;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    public function getEffective(string $key, mixed $default = null): EffectiveSettingDTO
    {
        $hasExplicitDefault = func_num_args() > 1;

        return $this->resolveOne($key, $default, $hasExplicitDefault);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        $normalizedKeys = $this->normalizeKeys($keys);

        if ($normalizedKeys === []) {
            return [];
        }

        return $this->rememberValues($normalizedKeys);
    }

    public function flushCache(?int $companyId = null, ?int $userId = null): void
    {
        $resolvedCompanyId = $companyId ?? $this->currentCompanyResolver->resolveCompanyId();
        $resolvedUserId = $userId ?? $this->resolveUserId();

        $this->cacheService->forgetAll(
            $this->cacheTag($resolvedCompanyId, $resolvedUserId)
        );
    }

    private function resolveOne(string $key, mixed $default, bool $hasExplicitDefault): EffectiveSettingDTO
    {
        $resolved = $this->rememberEffective([$key]);
        $meta = $this->settingsRepository->metaByKey($key);
        $row = $resolved[$key] ?? null;

        return $this->toDto(
            key: $key,
            resolved: $row,
            metaDefault: $meta?->default_value,
            type: $meta?->type !== null ? (string) $meta->type : null,
            group: $meta?->group !== null ? (string) $meta->group : null,
            label: $meta?->label !== null ? (string) $meta->label : null,
            description: $meta?->description !== null ? (string) $meta->description : null,
            fallbackDefault: $default,
            hasExplicitDefault: $hasExplicitDefault,
        );
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function rememberValues(array $keys): array
    {
        $companyId = $this->currentCompanyResolver->resolveCompanyId();
        $userId = $this->resolveUserId();
        $metaRows = $this->settingsRepository->metaByKeys($keys);

        return $this->cacheService->remember(
            tag: $this->cacheTag($companyId, $userId),
            key: $this->cacheKey('many', $keys, $companyId, $userId),
            callback: function () use ($keys, $companyId, $userId, $metaRows): array {
                $resolved = $this->resolveEffectiveRows($keys, $companyId, $userId);
                $values = [];

                foreach ($keys as $key) {
                    $row = $resolved[$key] ?? null;
                    $meta = $metaRows->get($key);

                    if ($row instanceof EffectiveSettingData && $row->source !== 'none') {
                        $values[$key] = $row->effective_value;
                        continue;
                    }

                    if ($meta !== null) {
                        $values[$key] = $meta->default_value;
                        continue;
                    }

                    $values[$key] = null;
                }

                return $values;
            },
            ttl: $this->ttl(),
        );
    }

    /**
     * @param list<string> $keys
     * @return array<string, EffectiveSettingData>
     */
    private function rememberEffective(array $keys): array
    {
        $companyId = $this->currentCompanyResolver->resolveCompanyId();
        $userId = $this->resolveUserId();

        return $this->cacheService->remember(
            tag: $this->cacheTag($companyId, $userId),
            key: $this->cacheKey('effective', $keys, $companyId, $userId),
            callback: fn (): array => $this->resolveEffectiveRows($keys, $companyId, $userId),
            ttl: $this->ttl(),
        );
    }

    /**
     * @param list<string> $keys
     * @return array<string, EffectiveSettingData>
     */
    private function resolveEffectiveRows(array $keys, ?int $companyId, ?int $userId): array
    {
        $rows = $companyId === null
            ? $this->resolveAppOnlyRows($keys)
            : $this->resolver->getEffectiveMany($keys, $companyId, $userId);

        $resolved = [];

        foreach ($rows as $row) {
            $resolved[$row->key] = $row;
        }

        return $resolved;
    }

    /**
     * @param list<string> $keys
     * @return list<EffectiveSettingData>
     */
    private function resolveAppOnlyRows(array $keys): array
    {
        $appValues = $this->appSettings->valuesByKeys($keys);

        return array_map(
            static fn (string $key): EffectiveSettingData => new EffectiveSettingData(
                key: $key,
                effective_value: $appValues[$key] ?? null,
                source: array_key_exists($key, $appValues) ? 'app' : 'none',
                type: null,
                group: null,
                label: null,
                description: null,
                company_id: 0,
                user_id: null,
            ),
            $keys
        );
    }

    private function toDto(
        string $key,
        ?EffectiveSettingData $resolved,
        mixed $metaDefault,
        ?string $type,
        ?string $group,
        ?string $label,
        ?string $description,
        mixed $fallbackDefault,
        bool $hasExplicitDefault,
    ): EffectiveSettingDTO {
        $source = $resolved?->source ?? 'none';
        $value = $resolved?->effective_value;

        if ($source === 'none') {
            if ($metaDefault !== null) {
                $value = $metaDefault;
                $source = 'default';
            } elseif ($hasExplicitDefault) {
                $value = $fallbackDefault;
                $source = 'default';
            }
        }

        return new EffectiveSettingDTO(
            key: $key,
            value: $value,
            source: $source,
            default_value: $metaDefault,
            type: $resolved?->type ?? $type,
            group: $resolved?->group ?? $group,
            label: $resolved?->label ?? $label,
            description: $resolved?->description ?? $description,
            company_id: $this->normalizeId($resolved?->company_id),
            user_id: $this->normalizeId($resolved?->user_id),
        );
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     */
    private function normalizeKeys(array $keys): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => trim((string) $key),
            $keys
        ))));
    }

    /**
     * @param list<string> $keys
     */
    private function cacheKey(string $prefix, array $keys, ?int $companyId, ?int $userId): string
    {
        $companyVersion = $companyId !== null
            ? $this->cacheVersionService->get("effective_settings:{$companyId}:all")
            : 1;
        $appVersion = $this->cacheVersionService->get('landlord:app_settings.show');

        return implode(':', [
            $prefix,
            'company', (string) ($companyId ?? 0),
            'user', (string) ($userId ?? 0),
            'effective', (string) $companyVersion,
            'app', (string) $appVersion,
            hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR)),
        ]);
    }

    private function cacheTag(?int $companyId, ?int $userId): string
    {
        $tenantGroupId = $this->currentCompanyResolver->resolveTenantGroupId();

        if ($tenantGroupId !== null) {
            return sprintf(
                'tenant:%d:effective_settings:%d:%d',
                $tenantGroupId,
                $companyId ?? 0,
                $userId ?? 0
            );
        }

        return sprintf(
            'landlord:effective_settings:%d:%d',
            $companyId ?? 0,
            $userId ?? 0
        );
    }

    private function resolveUserId(): ?int
    {
        $userId = Auth::id();

        if (! is_numeric($userId)) {
            return null;
        }

        $value = (int) $userId;

        return $value > 0 ? $value : null;
    }

    private function ttl(): int
    {
        $ttl = (int) config('cache.ttl_fetch', 180);

        return min(300, max(60, $ttl));
    }

    private function normalizeId(?int $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function toInt(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $normalized ?? $default;
        }

        return $default;
    }
}
