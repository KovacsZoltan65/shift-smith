<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface CompanyRepositoryInterface
{
    /**
     * Cégek lekérése tenant/HQ szabályok szerinti listázáshoz.
     *
     * @return LengthAwarePaginator<int, \App\Models\Company>
     */
    public function fetch(Request $request): LengthAwarePaginator;

    /**
     * HQ globális (landlord) cég lista, tenant scope nélkül.
     *
     * @return LengthAwarePaginator<int, \App\Models\Company>
     */
    public function fetchHq(Request $request): LengthAwarePaginator;

    /**
     * Cég lekérése azonosító alapján.
     */
    public function getCompany(int $id): Company;

    /**
     * Landlord/HQ cég lekérése tenant scope nélkül.
     */
    public function getCompanyForHq(int $id): Company;

    /**
     * Cég lekérése név alapján.
     */
    public function getCompanyByName(string $name): Company;

    /**
     * Új cég létrehozása.
     *
     * @param array{
     *   tenant_group_id: int,
     *   name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   active?: bool
     * } $data
     */
    public function store(array $data): Company;

    /**
     * @param array{
     *   name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   active?: bool
     * } $data
     */
    public function createInExistingTenantGroup(array $data): Company;

    /**
     * @param array{
     *   name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   active?: bool
     * } $data
     */
    public function provisionTenantGroupWithInitialCompany(array $data): Company;

    /**
     * Cég frissítése azonosító alapján.
     *
     * @param array{
     *    name: string,
     *    email?: string|null,
     *    address?: string|null,
     *    phone?: string|null,
     *    active?: bool
     * } $data
     * @param int $id
     */
    public function update(array $data, int $id): Company;

    /**
     * HQ frissítés reassignment nélkül.
     *
     * @param array{
     *    tenant_group_id: int,
     *    name: string,
     *    email?: string|null,
     *    address?: string|null,
     *    phone?: string|null,
     *    active?: bool
     * } $data
     */
    public function updateForHq(array $data, int $id): Company;

    /**
     * @param list<int> $ids
     */
    public function bulkDelete(array $ids): int;

    /**
     * Cég törlése azonosító alapján.
     */
    public function destroy(int $id): bool;

    /**
     * @param array{
     *   only_with_employees?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array;

    public function companyBelongsToActiveTenantGroup(int $companyId, int $tenantGroupId): bool;
}
