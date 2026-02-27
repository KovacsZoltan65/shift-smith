<?php

declare(strict_types=1);

namespace App\Repositories\UserAssignments;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserAssignmentRepositoryInterface
{
    public function fetchUsers(?string $q = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * @return Collection<int, Company>
     */
    public function getTenantCompanies(?User $actor = null): Collection;

    /**
     * @return Collection<int, Company>
     */
    public function getUserCompanies(User $user, ?User $actor = null): Collection;

    public function attachCompany(User $user, Company $company): void;

    public function detachCompany(User $user, Company $company): void;

    /**
     * @return Collection<int, Employee>
     */
    public function getCompanyEmployees(Company $company): Collection;

    public function getAssignedEmployee(User $user, Company $company): ?Employee;

    public function assignEmployee(User $user, Company $company, Employee $employee): void;

    public function removeEmployee(User $user, Company $company): void;

    public function employeeAssignedToOtherUser(User $user, Company $company, Employee $employee): bool;

    public function findTenantCompanyById(int $companyId, ?User $actor = null): ?Company;

    public function findCompanyEmployeeById(Company $company, int $employeeId): ?Employee;

    public function userHasCompany(User $user, Company $company): bool;

    public function userIsVisibleInTenant(User $user, ?User $actor = null): bool;
}
