<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

interface UserEmployeeRepositoryInterface
{
    /**
     * @return Collection<int, User>
     */
    public function listUsersForTenant(User $actor): Collection;

    /**
     * @return Collection<int, Employee>
     */
    public function getUserEmployees(User $user): Collection;

    /**
     * @return Collection<int, Employee>
     */
    public function getSelectableEmployeesForUser(User $actor, User $target): Collection;

    public function employeeIsAssignableToUser(User $actor, User $target, Employee $employee): bool;

    public function employeeIsManageableByActor(User $actor, Employee $employee): bool;

    public function userHasEmployee(User $target, Employee $employee): bool;

    public function findEmployeeIdForUserInCompany(User $user, int $companyId): ?int;

    public function attach(User $target, Employee $employee): void;

    public function detach(User $target, Employee $employee): void;
}
