<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Data\Tenant\TenantGroupData;
use App\Models\TenantGroup;
use App\Repositories\Tenant\TenantGroupRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Application service a landlord oldali TenantGroup kezeléshez.
 *
 * Ez a réteg a repository hívásokat hangolja össze, normalizálja a DTO payloadot,
 * és a repository mutációja előtt érvényesíti a törlési invariánsokat.
 */
final class TenantGroupService
{
    public function __construct(
        private readonly TenantGroupRepositoryInterface $repository,
    ) {}

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
    public function fetch(array $filters): LengthAwarePaginator
    {
        return $this->repository->fetch($filters);
    }

    public function find(int $id): TenantGroup
    {
        return $this->repository->findById($id);
    }

    /**
     * Létrehozza a tenant definíciót a validált landlord DTO alapján.
     */
    public function store(TenantGroupData $data): TenantGroup
    {
        return $this->repository->create($this->payload($data));
    }

    /**
     * Frissíti a landlord metaadatokat úgy, hogy közben a company-scoped domain határ változatlan marad.
     */
    public function update(TenantGroup $tenantGroup, TenantGroupData $data): TenantGroup
    {
        return $this->repository->update($tenantGroup, $this->payload($data));
    }

    /**
     * A törlés szándékosan konzervatív: a tenant group nem archiválható, amíg cégek tartoznak hozzá,
     * még akkor sem, ha később kifinomultabb archiválási folyamat készül.
     */
    public function destroy(TenantGroup $tenantGroup): void
    {
        $impact = $this->repository->deleteImpact($tenantGroup);

        if (($impact['company_count'] ?? 0) > 0 || ($impact['active_company_count'] ?? 0) > 0) {
            throw new TenantGroupDeletionBlockedException(
                impact: $impact,
                message: 'Tenant group deletion is blocked while companies still belong to it.',
            );
        }

        $this->repository->delete($tenantGroup);
    }

    /**
     * @return array{name:string,code:string,status:?string,active:bool,notes:?string,database_name:?string}
     */
    private function payload(TenantGroupData $data): array
    {
        return [
            'name' => trim($data->name),
            'code' => trim($data->code),
            'status' => $data->status !== null && trim($data->status) !== '' ? trim($data->status) : null,
            'active' => $data->active,
            'notes' => $data->notes !== null && trim($data->notes) !== '' ? trim($data->notes) : null,
            'database_name' => $data->databaseName !== null && trim($data->databaseName) !== '' ? trim($data->databaseName) : null,
        ];
    }
}
