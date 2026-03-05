<?php

namespace App\Services;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\PositionRepositoryInterface;
use App\Models\Employee;
use App\Services\Org\PositionOrgLevelService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Data\Employee\EmployeeData;

/**
 * Munkavállaló szolgáltatás osztály
 * 
 * Üzleti logikai réteg a munkavállalók kezeléséhez.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class EmployeeService
{
    /**
     * @param EmployeeRepositoryInterface $repo Munkavállaló repository
     */
    public function __construct(
        private readonly EmployeeRepositoryInterface $repo,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly PositionOrgLevelService $positionOrgLevelService,
    ) {}
    
    /**
     * Munkavállalók listázása lapozással és szűréssel
     * 
     * @param Request $request HTTP kérés (search, field, order, per_page paraméterekkel)
     * @return LengthAwarePaginator<int, Employee> Lapozott munkavállaló lista
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }
    
    /**
     * Egy munkavállaló lekérése azonosító alapján
     * 
     * @param int $id Munkavállaló azonosító
     * @return Employee Munkavállaló model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getEmployee(int $id): Employee
    {
        return $this->repo->getEmployee($id);
    }
    
    /**
     * Munkavállaló lekérése név alapján
     * 
     * @param string $name Munkavállaló neve
     * @return Employee Munkavállaló model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Ha a rekord nem található
     */
    public function getEmployeeByName(string $name): Employee
    {
        return $this->repo->getEmployeeByName($name);
    }
    
    /**
     * Új munkavállaló létrehozása.
     *
     * @param EmployeeData $data Validált DTO adatok
     * @return EmployeeData Létrehozott munkavállaló
     */
    public function store(EmployeeData $data): EmployeeData
    {
        $employee = $this->repo->store([
            'company_id' => $data->company_id,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'address' => $data->address,
            'position_id' => $data->position_id,
            'org_level' => $this->resolveOrgLevel($data->company_id, $data->position_id),
            'phone' => $data->phone,
            'birth_date' => $data->birth_date,
            'hired_at' => $data->hired_at,
            'active' => $data->active,
        ]);

        return EmployeeData::fromModel($employee);
    }
    
    /**
     * Munkavállaló adatainak frissítése.
     *
     * @param int $id Munkavállaló azonosító
     * @param EmployeeData $data Frissítendő DTO adatok
     * @return EmployeeData Frissített munkavállaló
     */
    public function update(EmployeeData $data, int $id): EmployeeData
    {
        $employee = $this->repo->update([
            'company_id' => $data->company_id,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'address' => $data->address,
            'position_id' => $data->position_id,
            'org_level' => $this->resolveOrgLevel($data->company_id, $data->position_id),
            'phone' => $data->phone,
            'birth_date' => $data->birth_date,
            'hired_at' => $data->hired_at,
            'active' => $data->active,
        ], $id);

        return EmployeeData::fromModel($employee);
    }
    
    /**
     * Több munkavállaló törlése egyszerre
     * 
     * Automatikusan kiszűri a duplikátumokat.
     * 
     * @param list<int> $ids Munkavállaló azonosítók tömbje
     * @return int A törölt rekordok száma
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    /**
     * Egy munkavállaló törlése
     * 
     * @param int $id Munkavállaló azonosító
     * @return bool Sikeres törlés esetén true
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
    
    /**
     * Munkavállalók lekérése select listához
     * 
     * Egyszerűsített munkavállaló lista (id, name) dropdown/select mezőkhöz.
     * 
     * @param array{
     *   only_active?: bool
     * } $params Szűrési paraméterek
     * @return array<int, array{id:int, name:string}> Munkavállalók tömbje
     */
    public function getToSelect(array $params): array
    {
        return $this->repo->getToSelect($params);
    }

    /**
     * AutoPlan-re jogosult dolgozók lekérése tenanton belül.
     *
     * A repository intervallum-átfedés és napi perc alapú szűrést alkalmaz.
     *
     * @param array{
     *   required_daily_minutes?: int|null,
     *   month?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   search?: string|null,
     *   shift_ids?: list<int>,
     *   eligible_for_autoplan?: bool
     * } $params
     * @return array{
     *   data: array<int, array{id:int, full_name:string, name:string, work_pattern_summary:string}>,
     *   meta: array{
     *     total_employees:int,
     *     eligible_count:int,
     *     excluded_count:int,
     *     excluded_reasons: array{missing_pattern:int, not_matching_minutes:int, inactive:int},
     *     required_daily_minutes:int,
     *     month:string|null
     *   }
     * }
     */
    public function getEligibleForAutoPlan(int $companyId, array $params): array
    {
        return $this->repo->getEligibleForAutoPlan($companyId, $params);
    }

    private function resolveOrgLevel(int $companyId, ?int $positionId): string
    {
        if (! is_int($positionId) || $positionId <= 0) {
            return Employee::ORG_LEVEL_STAFF;
        }

        $position = $this->positionRepository->getPosition($positionId, $companyId);

        return $this->positionOrgLevelService->resolveOrgLevel($companyId, (string) $position->name);
    }
}
