<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface PositionRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Position>
     */
    public function fetch(Request $request): LengthAwarePaginator;

    public function getPosition(int $id, int $companyId): Position;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   description?:string|null,
     *   active?:bool
     * } $data
     */
    public function store(array $data): Position;

    /**
     * @param array{
     *   company_id:int,
     *   name:string,
     *   description?:string|null,
     *   active?:bool
     * } $data
     */
    public function update(array $data, mixed $id): Position;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids, int $companyId): int;

    public function destroy(int $id, int $companyId): bool;

    /**
     * @param int $companyId
     * @param bool $onlyActive
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(int $companyId, bool $onlyActive = true): array;

    public function firstOrCreateInCompany(int $companyId, string $name, ?string $description = null): Position;
}
