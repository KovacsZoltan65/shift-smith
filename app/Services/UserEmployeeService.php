<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Repositories\UserEmployeeRepositoryInterface;
use App\Services\Selectors\CompanySelectorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserEmployeeService
{
    public function __construct(
        private readonly UserEmployeeRepositoryInterface $repository,
        private readonly CompanySelectorService $companySelectorService,
    ) {}

    /**
     * @return array{
     *   users: array<int, array{id:int,name:string,email:string}>,
     *   selected_user_id: int|null,
     *   current_mapping: array<int, array{id:int,name:string,email:string|null,companies:array<int, array{id:int,name:string}>>>,
     *   selectable_employees: array<int, array{id:int,name:string,email:string|null,companies:array<int, array{id:int,name:string}>>>
     * }
     */
    public function indexPayload(User $actor): array
    {
        $users = $this->repository->listUsersForTenant($actor);

        /** @var User|null $selectedUser */
        $selectedUser = $users->first();

        if (! $selectedUser instanceof User) {
            return [
                'users' => [],
                'selected_user_id' => null,
                'current_mapping' => [],
                'selectable_employees' => [],
            ];
        }

        return [
            'users' => $this->mapUsers($users),
            'selected_user_id' => (int) $selectedUser->id,
            'current_mapping' => $this->mapEmployees($this->repository->getUserEmployees($selectedUser)),
            'selectable_employees' => $this->mapEmployees($this->repository->getSelectableEmployeesForUser($actor, $selectedUser)),
        ];
    }

    /**
     * @return array{
     *   user_id: int,
     *   employees: array<int, array{id:int,name:string,email:string|null,companies:array<int, array{id:int,name:string}>>>,
     *   selectable_employees: array<int, array{id:int,name:string,email:string|null,companies:array<int, array{id:int,name:string}>>>
     * }
     */
    public function fetchPayload(User $actor, User $target): array
    {
        return [
            'user_id' => (int) $target->id,
            'employees' => $this->mapEmployees($this->repository->getUserEmployees($target)),
            'selectable_employees' => $this->mapEmployees($this->repository->getSelectableEmployeesForUser($actor, $target)),
        ];
    }

    public function attach(User $actor, User $target, Employee $employee): void
    {
        if (! $this->repository->employeeIsAssignableToUser($actor, $target, $employee)) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozó nem rendelhető hozzá a kiválasztott felhasználóhoz.',
            ]);
        }

        DB::transaction(function () use ($target, $employee): void {
            $this->repository->attach($target, $employee);

            $tenantGroupId = $this->currentTenantGroupId();
            if ($tenantGroupId !== null) {
                DB::afterCommit(function () use ($tenantGroupId): void {
                    $this->companySelectorService->bumpSelectorVersionForTenant($tenantGroupId);
                });
            }
        });
    }

    public function detach(User $actor, User $target, Employee $employee): void
    {
        if (! $this->repository->userHasEmployee($target, $employee)) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozó nincs a felhasználóhoz rendelve.',
            ]);
        }

        if (! $this->repository->employeeIsManageableByActor($actor, $employee)) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozó nem kezelhető a jelenlegi tenant/scope alapján.',
            ]);
        }

        DB::transaction(function () use ($target, $employee): void {
            $this->repository->detach($target, $employee);

            $tenantGroupId = $this->currentTenantGroupId();
            if ($tenantGroupId !== null) {
                DB::afterCommit(function () use ($tenantGroupId): void {
                    $this->companySelectorService->bumpSelectorVersionForTenant($tenantGroupId);
                });
            }
        });
    }

    private function currentTenantGroupId(): ?int
    {
        $tenantGroupId = TenantGroup::current()?->id;
        if (! is_numeric($tenantGroupId)) {
            return null;
        }

        $value = (int) $tenantGroupId;

        return $value > 0 ? $value : null;
    }

    /**
     * @param \Illuminate\Support\Collection<int, User> $users
     * @return array<int, array{id:int,name:string,email:string}>
     */
    private function mapUsers(\Illuminate\Support\Collection $users): array
    {
        return $users
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int, Employee> $employees
     * @return array<int, array{id:int,name:string,email:string|null,companies:array<int, array{id:int,name:string}>>>
     */
    private function mapEmployees(\Illuminate\Support\Collection $employees): array
    {
        return $employees
            ->map(static function (Employee $employee): array {
                $companies = $employee->companies
                    ->map(static fn ($company): array => [
                        'id' => (int) $company->id,
                        'name' => (string) $company->name,
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => (int) $employee->id,
                    'name' => trim((string) ($employee->name ?? "{$employee->first_name} {$employee->last_name}")),
                    'email' => $employee->email !== null ? (string) $employee->email : null,
                    'companies' => $companies,
                ];
            })
            ->values()
            ->all();
    }
}
