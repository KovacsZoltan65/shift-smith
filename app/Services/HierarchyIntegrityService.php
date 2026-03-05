<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class HierarchyIntegrityService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly EmployeeSupervisorRepositoryInterface $employeeSupervisorRepository,
    ) {
    }

    public function validateNewSupervisorRelationOrFail(
        int $companyId,
        int $employeeId,
        ?int $supervisorEmployeeId,
        CarbonInterface $validFrom,
        ?CarbonInterface $validTo = null,
        ?int $ignoreId = null,
        bool $enforceOverlap = true
    ): void {
        $employee = $this->requireEmployeeInCompany($employeeId, $companyId, 'employee_id');

        if ($employee->org_level === Employee::ORG_LEVEL_CEO) {
            if ($supervisorEmployeeId !== null) {
                throw ValidationException::withMessages([
                    'supervisor_employee_id' => 'CEO szinthez nem rendelhető felettes.',
                ]);
            }

            return;
        }

        if ($supervisorEmployeeId === null) {
            throw ValidationException::withMessages([
                'supervisor_employee_id' => 'Nem CEO szintű dolgozóhoz kötelező felettest megadni.',
            ]);
        }

        $supervisor = $this->requireEmployeeInCompany($supervisorEmployeeId, $companyId, 'supervisor_employee_id');

        if ($employee->id === $supervisor->id) {
            throw ValidationException::withMessages([
                'supervisor_employee_id' => 'A dolgozó nem lehet saját maga felettese.',
            ]);
        }

        if ($validTo !== null && $validTo->lt($validFrom)) {
            throw ValidationException::withMessages([
                'valid_to' => 'A záró dátum nem lehet korábbi, mint a kezdő dátum.',
            ]);
        }

        if ($enforceOverlap) {
            if ($this->employeeSupervisorRepository->hasOverlappingSupervisorPeriod(
                companyId: $companyId,
                employeeId: $employeeId,
                from: CarbonImmutable::instance($validFrom),
                to: $validTo !== null ? CarbonImmutable::instance($validTo) : null,
                ignoreId: $ignoreId
            )) {
                throw ValidationException::withMessages([
                    'valid_from' => 'A megadott időszak átfed meglévő felettes kapcsolattal.',
                ]);
            }
        }

        if ($this->employeeSupervisorRepository->wouldCreateCycle(
            companyId: $companyId,
            employeeId: $employeeId,
            supervisorEmployeeId: $supervisorEmployeeId,
            date: CarbonImmutable::instance($validFrom)
        )) {
            throw ValidationException::withMessages([
                'supervisor_employee_id' => 'A kapcsolat ciklust hozna létre a szervezeti hierarchiában.',
            ]);
        }
    }

    private function requireEmployeeInCompany(int $employeeId, int $companyId, string $field): Employee
    {
        $employee = $this->employeeRepository->findByIdInCompany($employeeId, $companyId);

        if (! $employee instanceof Employee) {
            throw ValidationException::withMessages([
                $field => 'A dolgozó nem a kiválasztott company scope-ban található.',
            ]);
        }

        return $employee;
    }
}
