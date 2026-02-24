<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AppSetting;
use App\Models\CompanySetting;
use App\Models\SettingsMeta;
use App\Models\UserSetting;
use Illuminate\Support\Collection;

class SettingsRepository
{
    /**
     * @return Collection<int, SettingsMeta>
     */
    public function meta(?string $search = null): Collection
    {
        return SettingsMeta::query()
            ->where('is_visible', true)
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $term = trim($search);
                $query->where(function ($q) use ($term): void {
                    $q->where('key', 'like', "%{$term}%")
                        ->orWhere('label', 'like', "%{$term}%")
                        ->orWhere('group', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->orderBy('group')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function appValuesByKeys(array $keys): array
    {
        return AppSetting::query()
            ->whereIn('key', $keys)
            ->get(['key', 'value'])
            ->mapWithKeys(static fn (AppSetting $row): array => [(string) $row->key => $row->value])
            ->all();
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function companyValuesByKeys(int $companyId, array $keys): array
    {
        return CompanySetting::query()
            ->where('company_id', $companyId)
            ->whereIn('key', $keys)
            ->get(['key', 'value'])
            ->mapWithKeys(static fn (CompanySetting $row): array => [(string) $row->key => $row->value])
            ->all();
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function userValuesByKeys(int $userId, array $keys): array
    {
        return UserSetting::query()
            ->where('user_id', $userId)
            ->whereIn('key', $keys)
            ->get(['key', 'value'])
            ->mapWithKeys(static fn (UserSetting $row): array => [(string) $row->key => $row->value])
            ->all();
    }

    public function appValue(string $key): mixed
    {
        return AppSetting::query()->where('key', $key)->value('value');
    }

    public function companyValue(int $companyId, string $key): mixed
    {
        return CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->value('value');
    }

    public function userValue(int $userId, string $key): mixed
    {
        return UserSetting::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->value('value');
    }

    public function metaByKey(string $key): ?SettingsMeta
    {
        return SettingsMeta::query()->where('key', $key)->first();
    }

    public function saveAppValue(string $key, mixed $value, int $updatedBy): void
    {
        AppSetting::query()
            ->withTrashed()
            ->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_by' => $updatedBy,
                    'deleted_at' => null,
                ]
            );
    }

    public function saveCompanyValue(int $companyId, string $key, mixed $value, int $updatedBy): void
    {
        CompanySetting::query()
            ->withTrashed()
            ->updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                [
                    'value' => $value,
                    'updated_by' => $updatedBy,
                    'deleted_at' => null,
                ]
            );
    }

    public function saveUserValue(int $userId, string $key, mixed $value, int $updatedBy): void
    {
        UserSetting::query()
            ->withTrashed()
            ->updateOrCreate(
                ['user_id' => $userId, 'key' => $key],
                [
                    'value' => $value,
                    'updated_by' => $updatedBy,
                    'deleted_at' => null,
                ]
            );
    }

    public function deleteAppOverride(string $key): void
    {
        AppSetting::query()->where('key', $key)->delete();
    }

    public function deleteCompanyOverride(int $companyId, string $key): void
    {
        CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->delete();
    }

    public function deleteUserOverride(int $userId, string $key): void
    {
        UserSetting::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->delete();
    }
}

