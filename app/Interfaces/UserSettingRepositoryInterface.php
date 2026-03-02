<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\UserSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserSettingRepositoryInterface
{
    /**
     * @param array{
     *   q?: string|null,
     *   group?: string|null,
     *   type?: string|null,
     *   sortBy?: string|null,
     *   sortDir?: string|null,
     *   page?: int|null,
     *   perPage?: int|null
     * } $filters
     *
     * @return LengthAwarePaginator<int, UserSetting>
     */
    public function fetch(int $companyId, int $userId, array $filters): LengthAwarePaginator;

    public function findByIdInScope(int $id, int $companyId, int $userId): UserSetting;

    public function findOneByUserCompanyKey(int $userId, int $companyId, string $key): ?UserSetting;

    public function findLegacyByUserKey(int $userId, string $key): ?UserSetting;

    /**
     * @param list<string> $keys
     * @return Collection<int, UserSetting>
     */
    public function findManyByUserCompanyKeys(int $userId, int $companyId, array $keys): Collection;

    /**
     * @param list<string> $keys
     * @return Collection<int, UserSetting>
     */
    public function findManyLegacyByUserKeys(int $userId, array $keys): Collection;

    /**
     * @param array{
     *   company_id:int,
     *   user_id:int,
     *   key:string,
     *   value?:mixed,
     *   type:string,
     *   group:string,
     *   label?:string|null,
     *   description?:string|null
     * } $attributes
     */
    public function createSetting(array $attributes): UserSetting;

    /**
     * @param array{
     *   value?:mixed,
     *   type?:string,
     *   group?:string,
     *   label?:string|null,
     *   description?:string|null,
     *   updated_by?:int|null
     * } $payload
     */
    public function upsertForUserCompanyKey(int $userId, ?int $companyId, string $key, array $payload): UserSetting;

    /**
     * @param array{
     *   key:string,
     *   value?:mixed,
     *   type:string,
     *   group:string,
     *   label?:string|null,
     *   description?:string|null
     * } $attributes
     */
    public function updateSetting(int $id, int $companyId, int $userId, array $attributes): UserSetting;

    public function deleteSetting(int $id, int $companyId, int $userId): bool;

    public function deleteForUserCompanyKey(int $userId, ?int $companyId, string $key): bool;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(int $companyId, int $userId, array $ids): int;

    /**
     * @return list<string>
     */
    public function groups(int $companyId, int $userId): array;

    /**
     * @return list<string>
     */
    public function types(int $companyId, int $userId): array;

    public function isUserAvailableInCompany(int $companyId, int $userId): bool;
}
