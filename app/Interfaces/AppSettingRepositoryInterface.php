<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\AppSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AppSettingRepositoryInterface
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
     * @return LengthAwarePaginator<int, AppSetting>
     */
    public function fetch(array $filters): LengthAwarePaginator;

    public function findById(int $id): AppSetting;

    public function findByKey(string $key): ?AppSetting;

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
    public function createSetting(array $attributes): AppSetting;

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
    public function updateSetting(int $id, array $attributes): AppSetting;

    public function deleteSetting(int $id): bool;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids): int;

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function valuesByKeys(array $keys): array;

    /**
     * @return list<string>
     */
    public function groups(): array;

    /**
     * @return list<string>
     */
    public function types(): array;
}
