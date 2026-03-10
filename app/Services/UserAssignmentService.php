<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Repositories\UserAssignments\UserAssignmentRepositoryInterface;
use App\Services\Selectors\CompanySelectorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserAssignmentService
{
    public function __construct(
        private readonly UserAssignmentRepositoryInterface $repository,
        private readonly CompanySelectorService $companySelectorService,
    ) {}

    /**
     * @return array{title:string}
     */
    public function indexPayload(): array
    {
        return [
            'title' => __('user_assignments.title'),
        ];
    }

    /**
     * @return array{
     *   items: array<int, array{id:int,name:string,email:string,is_superadmin:bool,primary_role_name:string|null}>,
     *   meta: array{current_page:int,per_page:int,total:int,last_page:int}
     * }
     */
    public function fetchUsers(?string $search = null, int $perPage = 15): array
    {
        $users = $this->repository->fetchUsers($search, $perPage);

        return [
            'items' => collect($users->items())
                ->map(static function (User $user): array {
                    $primaryRoleName = $user->roles
                        ->sortBy(fn ($role): string => (string) $role->name)
                        ->first()?->name;

                    return [
                        'id' => (int) $user->id,
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                        'is_superadmin' => $user->hasRole('superadmin'),
                        'primary_role_name' => $primaryRoleName !== null ? (string) $primaryRoleName : null,
                    ];
                })
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ];
    }

    /**
     * @return array{
     *   user_id:int,
     *   user_name:string,
     *   is_superadmin:bool,
     *   read_only:bool,
     *   read_only_reason:string|null,
     *   companies: array<int, array{
     *     id:int,
     *     name:string,
     *     assigned_employee:array{id:int,name:string,email:string|null}|null,
     *     selectable_employees:array<int, array{id:int,name:string,email:string|null}>
     *   }>,
     *   selectable_companies: array<int, array{id:int,name:string}>
     * }
     */
    public function fetchUserAssignments(User $actor, User $target, bool $enforceVisibility = true): array
    {
        if ($enforceVisibility) {
            $this->ensureVisibleUser($actor, $target);
        }

        $isSuperadmin = $target->hasRole('superadmin');
        $userCompanies = $this->repository->getUserCompanies($target, $actor);
        $tenantCompanies = $this->repository->getTenantCompanies($actor);
        $attachedIds = $userCompanies
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return [
            'user_id' => (int) $target->id,
            'user_name' => (string) $target->name,
            'is_superadmin' => $isSuperadmin,
            'read_only' => $isSuperadmin,
            'read_only_reason' => $isSuperadmin ? __('user_assignments.roles.superadmin_read_only') : null,
            'companies' => $userCompanies
                ->map(fn (Company $company): array => $this->mapCompany($target, $company, ! $isSuperadmin))
                ->values()
                ->all(),
            'selectable_companies' => $isSuperadmin
                ? []
                : $tenantCompanies
                    ->reject(fn (Company $company): bool => \in_array((int) $company->id, $attachedIds, true))
                    ->map(static fn (Company $company): array => [
                        'id' => (int) $company->id,
                        'name' => (string) $company->name,
                    ])
                    ->values()
                    ->all(),
        ];
    }

    public function attachCompany(User $actor, User $target, int $companyId): void
    {
        $this->ensureMutableTarget($actor, $target);
        $company = $this->resolveCompany($actor, $companyId);

        DB::transaction(function () use ($target, $company): void {
            $this->repository->attachCompany($target, $company);
            $this->bumpSelectorAfterCommit();
        });
    }

    public function detachCompany(User $actor, User $target, Company $company): void
    {
        $this->ensureMutableTarget($actor, $target);
        $tenantCompany = $this->resolveCompany($actor, (int) $company->id);

        if (! $this->repository->userHasCompany($target, $tenantCompany)) {
            throw ValidationException::withMessages([
                'company_id' => __('user_assignments.messages.user_not_assigned_to_company'),
            ]);
        }

        DB::transaction(function () use ($target, $tenantCompany): void {
            $this->repository->detachCompany($target, $tenantCompany);
            $this->bumpSelectorAfterCommit();
        });
    }

    public function assignEmployee(User $actor, User $target, Company $company, int $employeeId): void
    {
        $this->ensureMutableTarget($actor, $target);
        $tenantCompany = $this->resolveCompany($actor, (int) $company->id);

        if (! $this->repository->userHasCompany($target, $tenantCompany)) {
            throw ValidationException::withMessages([
                'company_id' => __('user_assignments.messages.user_not_assigned_to_company'),
            ]);
        }

        $employee = $this->repository->findCompanyEmployeeById($tenantCompany, $employeeId);
        if (! $employee instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => __('user_assignments.messages.employee_not_in_company'),
            ]);
        }

        if ($this->repository->employeeAssignedToOtherUser($target, $tenantCompany, $employee)) {
            throw ValidationException::withMessages([
                'employee_id' => __('user_assignments.messages.employee_already_assigned'),
            ]);
        }

        DB::transaction(function () use ($target, $tenantCompany, $employee): void {
            $this->repository->assignEmployee($target, $tenantCompany, $employee);
            $this->bumpSelectorAfterCommit();
        });
    }

    public function removeEmployee(User $actor, User $target, Company $company): void
    {
        $this->ensureMutableTarget($actor, $target);
        $tenantCompany = $this->resolveCompany($actor, (int) $company->id);

        if (! $this->repository->userHasCompany($target, $tenantCompany)) {
            throw ValidationException::withMessages([
                'company_id' => __('user_assignments.messages.user_not_assigned_to_company'),
            ]);
        }

        if (! $this->repository->getAssignedEmployee($target, $tenantCompany) instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => __('user_assignments.messages.no_employee_assignment'),
            ]);
        }

        DB::transaction(function () use ($target, $tenantCompany): void {
            $this->repository->removeEmployee($target, $tenantCompany);
            $this->bumpSelectorAfterCommit();
        });
    }

    private function ensureVisibleUser(User $actor, User $target): void
    {
        if ($this->repository->userIsVisibleInTenant($target, $actor)) {
            return;
        }

        throw ValidationException::withMessages([
            'user_id' => __('user_assignments.messages.user_not_visible'),
        ]);
    }

    private function ensureMutableTarget(User $actor, User $target): void
    {
        $this->ensureVisibleUser($actor, $target);

        if ($target->hasRole('superadmin')) {
            throw ValidationException::withMessages([
                'user_id' => __('user_assignments.roles.superadmin_read_only'),
            ]);
        }
    }

    private function resolveCompany(User $actor, int $companyId): Company
    {
        $company = $this->repository->findTenantCompanyById($companyId, $actor);

        if ($company instanceof Company) {
            return $company;
        }

        throw ValidationException::withMessages([
            'company_id' => __('user_assignments.messages.company_not_available'),
        ]);
    }

    /**
     * @return array{
     *   id:int,
     *   name:string,
     *   assigned_employee:array{id:int,name:string,email:string|null}|null,
     *   selectable_employees:array<int, array{id:int,name:string,email:string|null}>
     * }
     */
    private function mapCompany(User $target, Company $company, bool $includeSelectableEmployees): array
    {
        $assignedEmployee = $this->repository->getAssignedEmployee($target, $company);
        $selectableEmployees = $includeSelectableEmployees
            ? $this->repository->getCompanyEmployees($company)
            : collect();

        return [
            'id' => (int) $company->id,
            'name' => (string) $company->name,
            'assigned_employee' => $assignedEmployee instanceof Employee ? $this->mapEmployee($assignedEmployee) : null,
            'selectable_employees' => $selectableEmployees
                ->map(fn (Employee $employee): array => $this->mapEmployee($employee))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id:int,name:string,email:string|null}
     */
    private function mapEmployee(Employee $employee): array
    {
        return [
            'id' => (int) $employee->id,
            'name' => trim((string) $employee->name),
            'email' => $employee->email !== null ? (string) $employee->email : null,
        ];
    }

    private function bumpSelectorAfterCommit(): void
    {
        $tenantGroupId = TenantGroup::current()?->id;

        if (! is_numeric($tenantGroupId)) {
            throw ValidationException::withMessages([
                'tenant_group_id' => __('user_assignments.messages.no_tenant_context'),
            ]);
        }

        DB::afterCommit(function () use ($tenantGroupId): void {
            $this->companySelectorService->bumpSelectorVersionForTenant((int) $tenantGroupId);
        });
    }
}
