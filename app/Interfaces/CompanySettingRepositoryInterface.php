<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\CompanySetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanySettingRepositoryInterface
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
     * @return LengthAwarePaginator<int, CompanySetting>
     */
    public function fetch(int $companyId, array $filters): LengthAwarePaginator;

    public function findByIdInCompany(int $id, int $companyId): CompanySetting;

    public function findByKeyInCompany(string $key, int $companyId): ?CompanySetting;

    /**
     * @param array{
     *   company_id: int,
     *   key: string,
     *   value?: mixed,
     *   type: string,
     *   group: string,
     *   label?: string|null,
     *   description?: string|null
     * } $attributes
     */
    public function createSetting(array $attributes): CompanySetting;

    /**
     * @param array{
     *   key: string,
     *   value?: mixed,
     *   type: string,
     *   group: string,
     *   label?: string|null,
     *   description?: string|null
     * } $attributes
     */
    public function updateSetting(int $id, int $companyId, array $attributes): CompanySetting;

    public function deleteSetting(int $id, int $companyId): bool;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(int $companyId, array $ids): int;

    /**
     * @return list<string>
     */
    public function groups(int $companyId): array;

    /**
     * @return list<string>
     */
    public function types(int $companyId): array;

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function valuesByKeys(int $companyId, array $keys): array;
}
