<?php

namespace App\Services;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Data\Employee\EmployeeData;
use App\Data\Employee\EmployeeIndexData;

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
        private readonly EmployeeRepositoryInterface $repo
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
     * @return Employee Létrehozott munkavállaló
     */
    public function store(EmployeeData $data): Employee
    {
        $employee = $this->repo->store([
            'company_id' => $data->company_id,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'address' => $data->address,
            'position' => $data->position,
            'phone' => $data->phone,
            'hired_at' => $data->hired_at,
            'active' => $data->active,
        ]);

        return EmployeeData::fromModel($employee);
    }
    
    /**
     * Munkavállaló adatainak frissítése.
     *
     * @param int $id Munkavállaló azonosító
     * @param CompanyData $data Frissítendő DTO adatok
     * @return Employee Frissített munkavállaló
     */
    public function update($id, CompanyData $data): Employee
    {
        $employee = $this->repo->update([
            'company_id' => $data->company_id,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'address' => $data->address,
            'position' => $data->position,
            'phone' => $data->phone,
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
}
