<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\UserSettingRepositoryInterface;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserSettingRepository implements UserSettingRepositoryInterface
{
    public function fetch(int $companyId, int $userId, array $filters): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['perPage'] ?? 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;
        $sortBy = (string) ($filters['sortBy'] ?? 'key');
        $sortBy = \in_array($sortBy, ['key', 'group', 'type', 'updated_at', 'created_at'], true) ? $sortBy : 'key';
        $sortDir = strtolower((string) ($filters['sortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $search = $this->normalizeString($filters['q'] ?? null);
        $group = $this->normalizeString($filters['group'] ?? null);
        $type = $this->normalizeString($filters['type'] ?? null);

        return UserSetting::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->when($search !== null, function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('key', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($group !== null, fn ($query) => $query->where('group', $group))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->orderBy($sortBy, $sortDir)
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByIdInScope(int $id, int $companyId, int $userId): UserSetting
    {
        /** @var UserSetting */
        return UserSetting::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->findOrFail($id);
    }

    public function findOneByUserCompanyKey(int $userId, int $companyId, string $key): ?UserSetting
    {
        return UserSetting::query()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();
    }

    public function findLegacyByUserKey(int $userId, string $key): ?UserSetting
    {
        return UserSetting::query()
            ->where('user_id', $userId)
            ->whereNull('company_id')
            ->where('key', $key)
            ->first();
    }

    /**
     * @param list<string> $keys
     * @return Collection<int, UserSetting>
     */
    public function findManyByUserCompanyKeys(int $userId, int $companyId, array $keys): Collection
    {
        return UserSetting::query()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->whereIn('key', $keys)
            ->get();
    }

    /**
     * @param list<string> $keys
     * @return Collection<int, UserSetting>
     */
    public function findManyLegacyByUserKeys(int $userId, array $keys): Collection
    {
        return UserSetting::query()
            ->where('user_id', $userId)
            ->whereNull('company_id')
            ->whereIn('key', $keys)
            ->get();
    }

    public function createSetting(array $attributes): UserSetting
    {
        /** @var UserSetting $setting */
        $setting = UserSetting::query()->create($attributes);

        return $setting->refresh();
    }

    public function upsertForUserCompanyKey(int $userId, ?int $companyId, string $key, array $payload): UserSetting
    {
        /** @var UserSetting $setting */
        $setting = UserSetting::query()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                    'key' => $key,
                ],
                [...$payload, 'deleted_at' => null]
            );

        return $setting->refresh();
    }

    public function updateSetting(int $id, int $companyId, int $userId, array $attributes): UserSetting
    {
        $setting = $this->findByIdInScope($id, $companyId, $userId);
        $setting->fill($attributes);
        $setting->save();

        return $setting->refresh();
    }

    public function deleteSetting(int $id, int $companyId, int $userId): bool
    {
        return (bool) UserSetting::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereKey($id)
            ->delete();
    }

    public function deleteForUserCompanyKey(int $userId, ?int $companyId, string $key): bool
    {
        return (bool) UserSetting::query()
            ->where('user_id', $userId)
            ->when($companyId === null, fn ($query) => $query->whereNull('company_id'), fn ($query) => $query->where('company_id', $companyId))
            ->where('key', $key)
            ->delete();
    }

    public function bulkDelete(int $companyId, int $userId, array $ids): int
    {
        return UserSetting::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->delete();
    }

    public function groups(int $companyId, int $userId): array
    {
        return UserSetting::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->map(static fn ($value): string => (string) $value)
            ->values()
            ->all();
    }

    public function types(int $companyId, int $userId): array
    {
        return UserSetting::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->map(static fn ($value): string => (string) $value)
            ->values()
            ->all();
    }

    public function isUserAvailableInCompany(int $companyId, int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $companyId))
            ->exists();
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
