<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\TenantGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Landlord-only repository szerződés a TenantGroup rekordok kezeléséhez.
 *
 * A TenantGroup a rendszer valódi tenant határa. A company-scoped domain entitások
 * szándékosan kívül esnek ennek a repositorynak a felelősségén.
 */
interface TenantGroupRepositoryInterface
{
    /**
     * @param array{
     *   search?: ?string,
     *   active?: ?bool,
     *   status?: ?string,
     *   sort_field?: ?string,
     *   sort_direction?: ?string,
     *   page?: ?int,
     *   per_page?: ?int
     * } $filters
     * @return LengthAwarePaginator<int, TenantGroup>
     */
    public function fetch(array $filters): LengthAwarePaginator;

    public function findById(int $id): TenantGroup;

    /**
     * @param array{name:string,code:string,status:?string,active:bool,notes:?string,database_name:?string} $data
     */
    public function create(array $data): TenantGroup;

    /**
     * @param array{name:string,code:string,status:?string,active:bool,notes:?string,database_name:?string} $data
     */
    public function update(TenantGroup $tenantGroup, array $data): TenantGroup;

    /**
     * Összegzi a kapcsolódó landlord és company-scoped rekordokat a törlési döntés előtt.
     *
     * @return array{
     *   company_count:int,
     *   active_company_count:int,
     *   user_count:int,
     *   employee_count:int,
     *   work_schedule_count:int,
     *   work_shift_count:int
     * }
     */
    public function deleteImpact(TenantGroup $tenantGroup): array;

    public function delete(TenantGroup $tenantGroup): void;
}
