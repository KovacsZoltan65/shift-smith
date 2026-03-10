<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Settings\SettingGroupData;
use App\Data\Settings\SettingItemData;
use App\Data\Settings\SettingSaveValueData;
use App\Repositories\SettingsRepository;
use App\Services\Cache\CacheVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly SettingsResolverService $resolver,
        private readonly CacheVersionService $cacheVersionService,
        private readonly LocaleSettingsService $localeSettings,
    ) {}

    /**
     * @param array{
     *   level:'app'|'company'|'user',
     *   company_id?:int|null,
     *   user_id?:int|null,
     *   search?:string|null,
     *   changed_only?:bool
     * } $filters
     * @return array<int, array{group:string,items:array<int,array<string,mixed>>}>
     */
    public function fetch(array $filters): array
    {
        $level = (string) $filters['level'];
        $companyId = isset($filters['company_id']) ? (int) $filters['company_id'] : null;
        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : null;
        $changedOnly = (bool) ($filters['changed_only'] ?? false);
        $search = isset($filters['search']) ? (string) $filters['search'] : null;

        $metaRows = $this->repository->meta($search);
        $keys = $metaRows->pluck('key')->map(static fn ($k): string => (string) $k)->values()->all();

        $effectiveMap = $this->resolver->effectiveMap($companyId, $userId);
        $appValues = $this->repository->appValuesByKeys($keys);
        $companyValues = $companyId !== null ? $this->repository->companyValuesByKeys($companyId, $keys) : [];
        $userValues = ($userId !== null && $companyId !== null)
            ? $this->repository->userValuesByKeysInCompany($userId, $companyId, $keys)
            : [];

        if ($userId !== null && in_array(LocaleSettingsService::KEY, $keys, true)) {
            $localeValue = $this->repository->userValuesByKeys($userId, [LocaleSettingsService::KEY])[LocaleSettingsService::KEY] ?? null;

            if ($localeValue !== null) {
                $userValues[LocaleSettingsService::KEY] = $localeValue;
            }
        }

        $groups = [];

        foreach ($metaRows as $meta) {
            $key = (string) $meta->key;
            $source = 'default';

            if (array_key_exists($key, $userValues)) {
                $source = 'user';
            } elseif (array_key_exists($key, $companyValues)) {
                $source = 'company';
            } elseif (array_key_exists($key, $appValues)) {
                $source = 'app';
            }

            $overriddenAtLevel = match ($level) {
                'app' => array_key_exists($key, $appValues),
                'company' => array_key_exists($key, $companyValues),
                'user' => array_key_exists($key, $userValues),
            };

            if ($changedOnly && !$overriddenAtLevel) {
                continue;
            }

            $inherited = !$overriddenAtLevel;

            $item = new SettingItemData(
                key: $key,
                label: (string) $meta->label,
                type: (string) $meta->type,
                effective_value: $effectiveMap[$key] ?? $meta->default_value,
                source: $source,
                default_value: $meta->default_value,
                app_value: $appValues[$key] ?? null,
                company_value: $companyValues[$key] ?? null,
                user_value: $userValues[$key] ?? null,
                overridden_at_level: $overriddenAtLevel,
                inherited: $inherited,
                description: $meta->description ? (string) $meta->description : null,
                options: is_array($meta->options) ? $meta->options : null,
                validation: is_array($meta->validation) ? $meta->validation : null,
                order_index: (int) $meta->order_index,
            );

            $groupName = (string) $meta->group;
            $groups[$groupName] ??= [];
            $groups[$groupName][] = $item;
        }

        $out = [];
        foreach ($groups as $group => $items) {
            $out[] = (new SettingGroupData(group: (string) $group, items: $items))->toArray();
        }

        return $out;
    }

    /**
     * @param list<SettingSaveValueData> $values
     * @param array{
     *   level:'app'|'company'|'user',
     *   company_id?:int|null,
     *   user_id?:int|null
     * } $context
     * @return array{changed_keys:list<string>,saved_count:int}
     */
    public function save(int $actorUserId, array $context, array $values): array
    {
        $level = (string) $context['level'];
        $companyId = isset($context['company_id']) ? (int) $context['company_id'] : null;
        $targetUserId = isset($context['user_id']) ? (int) $context['user_id'] : null;
        $changedKeys = [];

        DB::transaction(function () use ($values, $actorUserId, $level, $companyId, $targetUserId, &$changedKeys): void {
            foreach ($values as $row) {
                $meta = $this->repository->metaByKey($row->key);
                if ($meta === null || !$meta->is_editable || !$meta->is_visible) {
                    continue;
                }

                $normalizedValue = $this->normalizeValueByType($row->value, (string) $meta->type);
                $this->validateMetaRules((string) $meta->key, $normalizedValue, is_array($meta->validation) ? $meta->validation : []);

                $parentValue = $this->parentEffectiveValue(
                    level: $level,
                    key: (string) $meta->key,
                    companyId: $companyId,
                    userId: $targetUserId,
                    defaultValue: $meta->default_value
                );

                if ($this->valuesEqual($normalizedValue, $parentValue)) {
                    $deleted = $this->deleteOverride($level, (string) $meta->key, $companyId, $targetUserId);
                    if ($deleted) {
                        $changedKeys[] = (string) $meta->key;
                    }
                    continue;
                }

                $changed = $this->upsertAtLevel(
                    $level,
                    (string) $meta->key,
                    $normalizedValue,
                    $actorUserId,
                    $companyId,
                    $targetUserId
                );

                if ($changed) {
                    $changedKeys[] = (string) $meta->key;
                }
            }
        });

        $changedKeys = array_values(array_unique($changedKeys));
        if ($changedKeys !== []) {
            $this->bumpVersions($level, $companyId, $targetUserId);

            activity('settings')
                ->event('settings.updated')
                ->withProperties([
                    'actor_user_id' => $actorUserId,
                    'level' => $level,
                    'company_id' => $companyId,
                    'user_id' => $targetUserId,
                    'changed_keys' => $changedKeys,
                ])
                ->log('Settings updated.');
        }

        return [
            'changed_keys' => $changedKeys,
            'saved_count' => count($changedKeys),
        ];
    }

    private function normalizeValueByType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => is_numeric($value) ? (int) $value : null,
            'float' => is_numeric($value) ? (float) $value : null,
            'string', 'select' => $value === null ? null : (string) $value,
            'multiselect' => array_values(array_map('strval', is_array($value) ? $value : [])),
            'json' => is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : $value),
            default => $value,
        };
    }

    /**
     * @param array<int,string> $rules
     */
    private function validateMetaRules(string $key, mixed $value, array $rules): void
    {
        if ($rules === []) {
            return;
        }

        $validator = Validator::make(['value' => $value], ['value' => $rules]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                "values.{$key}" => $validator->errors()->first('value'),
            ]);
        }
    }

    private function parentEffectiveValue(string $level, string $key, ?int $companyId, ?int $userId, mixed $defaultValue): mixed
    {
        return match ($level) {
            'app' => $defaultValue,
            'company' => $this->resolver->effectiveValue($key, [])['value'] ?? $defaultValue,
            'user' => $this->resolver->effectiveValue($key, ['company_id' => $companyId, 'user_id' => null])['value'] ?? $defaultValue,
        };
    }

    private function deleteOverride(string $level, string $key, ?int $companyId, ?int $userId): bool
    {
        return match ($level) {
            'app' => tap(true, fn () => $this->repository->deleteAppOverride($key)),
            'company' => $companyId !== null
                ? tap(true, fn () => $this->repository->deleteCompanyOverride($companyId, $key))
                : false,
            'user' => $userId !== null
                ? tap(true, fn () => $this->repository->deleteUserOverride(
                    $userId,
                    $this->usesGlobalUserScope($key) ? null : $companyId,
                    $key
                ))
                : false,
            default => false,
        };
    }

    private function upsertAtLevel(string $level, string $key, mixed $value, int $actorUserId, ?int $companyId, ?int $userId): bool
    {
        return match ($level) {
            'app' => tap(true, fn () => $this->repository->saveAppValue($key, $value, $actorUserId)),
            'company' => $companyId !== null
                ? tap(true, fn () => $this->repository->saveCompanyValue($companyId, $key, $value, $actorUserId))
                : false,
            'user' => $userId !== null
                ? tap(true, fn () => $this->repository->saveUserValue(
                    $userId,
                    $this->usesGlobalUserScope($key) ? null : $companyId,
                    $key,
                    $value,
                    $actorUserId
                ))
                : false,
            default => false,
        };
    }

    private function bumpVersions(string $level, ?int $companyId, ?int $userId): void
    {
        match ($level) {
            'app' => $this->cacheVersionService->bump('settings.app'),
            'company' => $companyId !== null ? $this->cacheVersionService->bump("settings.company.{$companyId}") : null,
            'user' => $userId !== null ? $this->cacheVersionService->bump("settings.user.{$userId}") : null,
        };
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function usesGlobalUserScope(string $key): bool
    {
        return $key === LocaleSettingsService::KEY;
    }
}
