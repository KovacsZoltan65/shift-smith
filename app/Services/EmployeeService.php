<?php

namespace App\Services;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $repo
    ) {}
    
    /**
     * @param Request $request
     * @return LengthAwarePaginator<int, Employee>
     */
    public function fetch(Request $request): LengthAwarePaginator
    {
        return $this->repo->fetch($request);
    }
    
    /**
     * Summary of getEmployee
     * @param int $id
     * @return Employee
     */
    public function getEmployee(int $id): Employee
    {
        return $this->repo->getEmployee($id);
    }
    
    public function getEmployeeByName(string $name): Employee
    {
        return $this->repo->getEmployeeByName($name);
    }
    
    /**
     * @param array{
     *   first_name: string,
     *   last_name: string,
     *   address?: string|null,
     *   phone?: string|null,
     *   email?: string|null,
     *   hired_at: string,
     *   company_id?: int|null,
     *   active?: bool
     * } $data
     */
    public function store(array $data): Employee
    {
        /** @var Employee $employee */
        $employee = $this->repo->store($data);

        return $employee;
    }
    
    /**
     * Summary of update
     * @param array{
     *    first_name: string,
     *    last_name: string,
     *    email: string,
     *    address: string,
     *    phone: string,
     *    hired_at: string,
     *    active: boolean
     * } $data
     * @param int $id
     * @return Employee
     */
    public function update(array $data, $id): Employee
    {
        return $this->repo->update($data, $id);
    }
    
    /**
     * @param list<int> $ids
     * @return int
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_unique($ids));
        
        return (int) $this->repo->bulkDelete($ids);
    }
    
    /**
     * Summary of destroy
     * @param int $id
     * @return bool
     */
    public function destroy(int $id): bool
    {
        return $this->repo->destroy($id);
    }
    
    /**
     * @param array{
     *   only_active?: bool
     * } $params
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function getToSelect(array $params): array
    {
        return $this->repo->getToSelect($params);
    }
}