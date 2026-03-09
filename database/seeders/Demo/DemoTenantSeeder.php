<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Admin\Role;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Employee;
use App\Models\Position;
use App\Models\TenantGroup;
use App\Models\User;
use App\Models\UserEmployee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class DemoTenantSeeder extends Seeder
{
    private const TENANT_NAME = 'Alfa Cégcsoport';
    private const TENANT_SLUG = 'alfa-group';
    private const TENANT_CODE = 'ALFA_GROUP';
    private const COMPANY_NAME = 'Alfa Könyvelés';
    private const COMPANY_EMAIL = 'info@alfa.test';
    private const PASSWORD = 'password';

    /**
     * @var array<int, array{first_name:string,last_name:string,position:string,email:string}>
     */
    private const EMPLOYEES = [
        ['first_name' => 'Kovács', 'last_name' => 'Anna', 'position' => 'Iroda vezető', 'email' => 'anna@alfa.test'],
        ['first_name' => 'Nagy', 'last_name' => 'Béla', 'position' => 'Karbantartó', 'email' => 'bela@alfa.test'],
        ['first_name' => 'Könyvelő', 'last_name' => '1', 'position' => 'Könyvelő', 'email' => 'konyvelo1@alfa.test'],
        ['first_name' => 'Könyvelő', 'last_name' => '2', 'position' => 'Könyvelő', 'email' => 'konyvelo2@alfa.test'],
        ['first_name' => 'Könyvelő', 'last_name' => '3', 'position' => 'Könyvelő', 'email' => 'konyvelo3@alfa.test'],
        ['first_name' => 'Könyvelő', 'last_name' => '4', 'position' => 'Könyvelő', 'email' => 'konyvelo4@alfa.test'],
        ['first_name' => 'Könyvelő', 'last_name' => '5', 'position' => 'Könyvelő', 'email' => 'konyvelo5@alfa.test'],
        ['first_name' => 'Szabó', 'last_name' => 'Dániel', 'position' => 'Rendszergazda', 'email' => 'daniel@alfa.test'],
        ['first_name' => 'Telefonos', 'last_name' => '1', 'position' => 'Telefonos', 'email' => 'telefon1@alfa.test'],
        ['first_name' => 'Telefonos', 'last_name' => '2', 'position' => 'Telefonos', 'email' => 'telefon2@alfa.test'],
    ];

    public function run(): void
    {
        $this->guardSchema();

        $tenant = TenantGroup::query()->firstOrCreate(
            ['slug' => self::TENANT_SLUG],
            [
                'name' => self::TENANT_NAME,
                'code' => self::TENANT_CODE,
                'database_name' => (string) DB::connection()->getDatabaseName(),
                'active' => true,
            ]
        );

        $tenant->forceFill([
            'name' => self::TENANT_NAME,
            'code' => self::TENANT_CODE,
            'database_name' => (string) DB::connection()->getDatabaseName(),
            'active' => true,
        ])->save();

        $tenant->makeCurrent();

        try {
            $company = Company::query()->firstOrCreate(
                [
                    'tenant_group_id' => (int) $tenant->id,
                    'name' => self::COMPANY_NAME,
                ],
                [
                    'email' => self::COMPANY_EMAIL,
                    'active' => true,
                ]
            );

            $company->forceFill([
                'email' => self::COMPANY_EMAIL,
                'active' => true,
            ])->save();

            $positions = $this->seedPositions($company);
            $employees = $this->seedEmployees($company, $positions);
            $users = $this->seedUsers();

            $this->seedCompanyEmployeePivot($company, $employees);
            $this->seedCompanyUserPivot($company, $users['superadmin'], $users['admin'], $users['staff']);
            $this->seedUserEmployeePivot(
                company: $company,
                superadmin: $users['superadmin'],
                admin: $users['admin'],
                staff: $users['staff'],
                anna: $employees['anna@alfa.test'],
                accountant: $employees['konyvelo1@alfa.test'],
            );
            $this->assertNoDuplicateEmployeeAssignments();

            $this->logLocalSummary($tenant, $company, $users);
        } finally {
            TenantGroup::forgetCurrent();
        }
    }

    private function guardSchema(): void
    {
        $hasCompanyId = Schema::hasColumn('user_employee', 'company_id');

        $uniqueRows = DB::connection()->select(
            'SELECT column_name
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND non_unique = 0
               AND index_name = ?
             ORDER BY seq_in_index',
            [
                (string) DB::connection()->getDatabaseName(),
                'user_employee',
                'user_employee_user_company_unique',
            ]
        );

        $uniqueColumns = array_map(
            static fn (object $row): string => (string) $row->column_name,
            $uniqueRows
        );

        $companyEmployeeUniqueRows = DB::connection()->select(
            'SELECT column_name
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND non_unique = 0
               AND index_name = ?
             ORDER BY seq_in_index',
            [
                (string) DB::connection()->getDatabaseName(),
                'user_employee',
                'user_employee_company_employee_unique',
            ]
        );

        $companyEmployeeUniqueColumns = array_map(
            static fn (object $row): string => (string) $row->column_name,
            $companyEmployeeUniqueRows
        );

        if (
            ! $hasCompanyId
            || $uniqueColumns !== ['user_id', 'company_id']
            || $companyEmployeeUniqueColumns !== ['company_id', 'employee_id']
        ) {
            throw new RuntimeException(
                'Missing schema requirement: user_employee must have company_id, UNIQUE(user_id, company_id) and UNIQUE(company_id, employee_id). Run migrations first.'
            );
        }
    }

    /**
     * @return array<string, Position>
     */
    private function seedPositions(Company $company): array
    {
        $names = collect(self::EMPLOYEES)
            ->pluck('position')
            ->unique()
            ->values();

        $positions = [];

        foreach ($names as $name) {
            $position = Position::query()->firstOrCreate(
                [
                    'company_id' => (int) $company->id,
                    'name' => (string) $name,
                ],
                [
                    'description' => (string) $name,
                    'active' => true,
                ]
            );

            $position->forceFill([
                'description' => (string) $name,
                'active' => true,
            ])->save();

            $positions[(string) $name] = $position;
        }

        return $positions;
    }

    /**
     * @param array<string, Position> $positions
     * @return array<string, Employee>
     */
    private function seedEmployees(Company $company, array $positions): array
    {
        $employees = [];

        foreach (self::EMPLOYEES as $employeeData) {
            $position = $positions[$employeeData['position']];

            $employee = Employee::query()->firstOrCreate(
                ['email' => $employeeData['email']],
                [
                    'company_id' => (int) $company->id,
                    'first_name' => $employeeData['first_name'],
                    'last_name' => $employeeData['last_name'],
                    'position_id' => (int) $position->id,
                    'active' => true,
                ]
            );

            $employee->forceFill([
                'company_id' => (int) $company->id,
                'first_name' => $employeeData['first_name'],
                'last_name' => $employeeData['last_name'],
                'position_id' => (int) $position->id,
                'active' => true,
            ])->save();

            $employees[$employeeData['email']] = $employee;
        }

        return $employees;
    }

    /**
     * @return array{superadmin:User,admin:User,staff:User}
     */
    private function seedUsers(): array
    {
        $superadminRole = Role::query()->firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::query()->firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $superadmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@shift-smith.test'],
            [
                'name' => 'ShiftSmith Superadmin',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ]
        );
        $superadmin->forceFill([
            'name' => 'ShiftSmith Superadmin',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ])->save();
        $superadmin->syncRoles([$superadminRole]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@alfa.test'],
            [
                'name' => 'Alfa Admin',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ]
        );
        $admin->forceFill([
            'name' => 'Alfa Admin',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ])->save();
        $admin->syncRoles([$adminRole]);

        $staff = User::query()->firstOrCreate(
            ['email' => 'staff@alfa.test'],
            [
                'name' => 'Alfa Staff',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ]
        );
        $staff->forceFill([
            'name' => 'Alfa Staff',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ])->save();
        $staff->syncRoles([$userRole]);

        return [
            'superadmin' => $superadmin,
            'admin' => $admin,
            'staff' => $staff,
        ];
    }

    /**
     * @param array<string, Employee> $employees
     */
    private function seedCompanyEmployeePivot(Company $company, array $employees): void
    {
        foreach ($employees as $employee) {
            CompanyEmployee::query()->updateOrCreate(
                [
                    'company_id' => (int) $company->id,
                    'employee_id' => (int) $employee->id,
                ],
                [
                    'active' => true,
                ]
            );
        }
    }

    private function seedCompanyUserPivot(Company $company, User $superadmin, User $admin, User $staff): void
    {
        $company->users()->detach((int) $superadmin->id);

        $company->users()->syncWithoutDetaching([
            (int) $admin->id,
            (int) $staff->id,
        ]);
    }

    private function seedUserEmployeePivot(Company $company, User $superadmin, User $admin, User $staff, Employee $anna, Employee $accountant): void
    {
        UserEmployee::query()
            ->where('user_id', (int) $superadmin->id)
            ->where('company_id', (int) $company->id)
            ->delete();

        UserEmployee::query()->updateOrCreate(
            [
                'user_id' => (int) $admin->id,
                'company_id' => (int) $company->id,
            ],
            [
                'employee_id' => (int) $anna->id,
                'active' => true,
            ]
        );

        UserEmployee::query()->updateOrCreate(
            [
                'user_id' => (int) $staff->id,
                'company_id' => (int) $company->id,
            ],
            [
                'employee_id' => (int) $accountant->id,
                'active' => true,
            ]
        );
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

    /**
     * @param array{superadmin:User,admin:User,staff:User} $users
     */
    private function logLocalSummary(TenantGroup $tenant, Company $company, array $users): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Log::info('seed.demo_tenant.summary', [
            'tenant_group_id' => (int) $tenant->id,
            'company_id' => (int) $company->id,
            'company_employee_count' => CompanyEmployee::query()
                ->where('company_id', (int) $company->id)
                ->count(),
            'company_user_count' => $company->users()
                ->whereIn('users.id', [(int) $users['admin']->id, (int) $users['staff']->id])
                ->count(),
            'user_employee_count' => UserEmployee::query()
                ->where('company_id', (int) $company->id)
                ->whereIn('user_id', [(int) $users['admin']->id, (int) $users['staff']->id])
                ->count(),
        ]);
    }
}
