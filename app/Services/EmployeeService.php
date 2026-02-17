<?php

namespace App\Services;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Munkavállaló szolgáltatás osztály
 * 
 * Üzleti logikai réteg a munkavállalók kezeléséhez.
 * Repository pattern-t használ az adatbázis műveletekhez.
 */
class EmployeeService
{
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
     * Új munkavállaló létrehozása
     * 
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   hired_at: string,
     *   company_id?: int|null,
     *   active?: bool
     * } $data Munkavállaló adatok
     * @return Employee Létrehozott munkavállaló
     */
    public function store(array $data): Employee
    {
        /** @var Employee $employee */
        $employee = $this->repo->store($data);

        return $employee;
    }
    
    /**
     * Munkavállaló adatainak frissítése
     * 
     * @param array{
     *    first_name: string,
     *    last_name: string,
     *    email: string,
     *    address: string,
     *    phone: string,
     *    hired_at: string,
     *    active: boolean
     * } $data Frissítendő adatok
     * @param int $id Munkavállaló azonosító
     * @return Employee Frissített munkavállaló
     */
    public function update(array $data, $id): Employee
    {
        return $this->repo->update($data, $id);
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