<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\EmployeeAbsence;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\SickLeaveCategory;
use App\Services\Cache\CacheVersionService;
use App\Services\CacheService;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeAbsenceRepository implements EmployeeAbsenceRepositoryInterface
{
    public function __construct(
        private readonly CacheService $cacheService,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function fetchCalendarEvents(int $companyId, array $filters): Collection
    {
        $normalized = [
            'companyId' => $companyId,
            'date_from' => (string) ($filters['date_from'] ?? ''),
            'date_to' => (string) ($filters['date_to'] ?? ''),
            'employee_ids' => array_values(array_map('intval', $filters['employee_ids'] ?? [])),
        ];
        $version = $this->cacheVersionService->get("absences:{$companyId}:calendar");
        $key = 'v'.$version.':'.hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        /** @var Collection<int, EmployeeAbsence> */
        return $this->cacheService->remember(
            tag: "absences:{$companyId}",
            key: $key,
            callback: function () use ($companyId, $filters): Collection {
                return EmployeeAbsence::query()
                    ->with([
                        'employee:id,company_id,first_name,last_name',
                        'leaveType:id,company_id,code,name,category,affects_leave_balance,requires_approval,active',
                        'sickLeaveCategory:id,company_id,name,code,active',
                    ])
                    ->inCompany($companyId)
                    ->whereDate('date_from', '<=', (string) $filters['date_to'])
                    ->whereDate('date_to', '>=', (string) $filters['date_from'])
                    ->when(
                        ! empty($filters['employee_ids']),
                        fn ($query) => $query->whereIn('employee_id', $filters['employee_ids'])
                    )
                    ->orderBy('date_from')
                    ->orderBy('employee_id')
                    ->get();
            },
            ttl: (int) config('cache.ttl_fetch', 60),
        );
    }

    public function findByIdInCompany(int $id, int $companyId): ?EmployeeAbsence
    {
        $version = $this->cacheVersionService->get("absences:{$companyId}:show");

        /** @var EmployeeAbsence|null $absence */
        $absence = $this->cacheService->remember(
            tag: "absences:{$companyId}",
            key: 'v'.$version.':'.$id,
            callback: static fn (): ?EmployeeAbsence => EmployeeAbsence::query()
                ->with(['employee', 'leaveType', 'sickLeaveCategory', 'creator'])
                ->inCompany($companyId)
                ->find($id),
            ttl: (int) config('cache.ttl_fetch', 60),
        );

        return $absence;
    }

    public function createForCompany(int $companyId, array $data): EmployeeAbsence
    {
        /** @var EmployeeAbsence $absence */
        $absence = EmployeeAbsence::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);

        return $absence->refresh()->load(['employee', 'leaveType', 'sickLeaveCategory', 'creator']);
    }

    public function updateInCompany(int $id, int $companyId, array $data): EmployeeAbsence
    {
        $absence = $this->findRequired($id, $companyId);
        $absence->fill($data);
        $absence->save();

        return $absence->refresh()->load(['employee', 'leaveType', 'sickLeaveCategory', 'creator']);
    }

    public function deleteInCompany(int $id, int $companyId): void
    {
        $absence = $this->findRequired($id, $companyId);
        $absence->delete();
    }

    public function findEmployeeForCompany(int $employeeId, int $companyId): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->findOrFail($employeeId);

        return $employee;
    }

    public function findLeaveTypeForCompany(int $leaveTypeId, int $companyId): LeaveType
    {
        /** @var LeaveType $leaveType */
        $leaveType = LeaveType::query()
            ->inCompany($companyId)
            ->findOrFail($leaveTypeId);

        return $leaveType;
    }

    public function findSickLeaveCategoryForCompany(int $categoryId, int $companyId): SickLeaveCategory
    {
        /** @var SickLeaveCategory $category */
        $category = SickLeaveCategory::query()
            ->inCompany($companyId)
            ->findOrFail($categoryId);

        return $category;
    }

    private function findRequired(int $id, int $companyId): EmployeeAbsence
    {
        $absence = $this->findByIdInCompany($id, $companyId);

        if ($absence instanceof EmployeeAbsence) {
            return $absence;
        }

        throw new NotFoundHttpException('A tavollet rekord nem talalhato a kivalasztott company scope-ban.');
    }
}
