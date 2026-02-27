<?php

declare(strict_types=1);

namespace Database\Seeders\Pivot;

use App\Models\Company;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Database\Seeder;
use RuntimeException;

final class UserEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        TenantGroup::query()
            ->orderBy('id')
            ->each(function (TenantGroup $tenant): void {
                $tenant->makeCurrent();

                try {
                    Company::query()
                        ->where('tenant_group_id', (int) $tenant->id)
                        ->where('active', true)
                        ->orderBy('id')
                        ->get(['id'])
                        ->each(function (Company $company): void {
                            $this->seedCompanyAssignments($company);
                        });

                    $this->assertNoDuplicateEmployeeAssignments();
                } finally {
                    TenantGroup::forgetCurrent();
                }
            });
    }

    private function seedCompanyAssignments(Company $company): void
    {
        $users = $company->users()
            ->orderBy('users.id')
            ->get(['users.id', 'users.email'])
            ->reject(fn (User $user): bool => $this->isSuperadmin($user))
            ->values();

        $employees = $company->employees()
            ->where('company_employee.active', true)
            ->orderBy('employees.id')
            ->get(['employees.id', 'employees.email'])
            ->values();

        $assignments = [];
        $availableEmployees = $employees->keyBy(fn ($employee): int => (int) $employee->id);
        $remainingUsers = collect();

        foreach ($users as $user) {
            $email = trim((string) $user->email);

            if ($email === '') {
                $remainingUsers->push($user);
                continue;
            }

            $matchedEmployee = $availableEmployees
                ->first(fn ($employee): bool => strcasecmp((string) ($employee->email ?? ''), $email) === 0);

            if ($matchedEmployee === null) {
                $remainingUsers->push($user);
                continue;
            }

            $assignments[(int) $user->id] = (int) $matchedEmployee->id;
            $availableEmployees->forget((int) $matchedEmployee->id);
        }

        $remainingEmployeeIds = $availableEmployees->keys()->map(static fn ($id): int => (int) $id)->values();

        foreach ($remainingUsers->values() as $index => $user) {
            $employeeId = $remainingEmployeeIds->get($index);

            if (! is_numeric($employeeId)) {
                continue;
            }

            $assignments[(int) $user->id] = (int) $employeeId;
        }

        $this->syncCompanyAssignments($company, $assignments);
    }

    /**
     * @param array<int, int> $assignments user_id => employee_id
     */
    private function syncCompanyAssignments(Company $company, array $assignments): void
    {
        $existing = UserEmployee::query()
            ->where('company_id', (int) $company->id)
            ->get();

        foreach ($existing as $userEmployee) {
            $expectedEmployeeId = $assignments[(int) $userEmployee->user_id] ?? null;

            if ($expectedEmployeeId === (int) $userEmployee->employee_id) {
                continue;
            }

            $userEmployee->delete();
        }

        foreach ($assignments as $userId => $employeeId) {
            UserEmployee::query()->updateOrCreate(
                [
                    'user_id' => (int) $userId,
                    'company_id' => (int) $company->id,
                ],
                [
                    'employee_id' => (int) $employeeId,
                    'active' => true,
                ]
            );
        }
    }

    private function assertNoDuplicateEmployeeAssignments(): void
    {
        $duplicates = UserEmployee::query()
            ->selectRaw('company_id, employee_id, COUNT(*) as aggregate')
            ->groupBy(['company_id', 'employee_id'])
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $pairs = $duplicates
            ->map(fn (UserEmployee $row): string => sprintf(
                '[company_id=%d, employee_id=%d, count=%d]',
                (int) $row->company_id,
                (int) $row->employee_id,
                (int) ($row->aggregate ?? 0),
            ))
            ->implode(', ');

        throw new RuntimeException('Seeder produced duplicate employee assignment: '.$pairs);
    }

    private function isSuperadmin(User $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return true;
        }

        return strcasecmp((string) $user->email, (string) config('seeding.superadmin_email', 'superadmin@shift-smith.com')) === 0;
    }
}
