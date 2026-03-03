<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\Settings;
use App\Models\EmployeeAbsence;
use App\Models\LeaveType;
use App\Repositories\EmployeeAbsenceRepositoryInterface;
use App\Services\Cache\CacheVersionService;
use Carbon\CarbonImmutable;

class AbsenceService
{
    public function __construct(
        private readonly EmployeeAbsenceRepositoryInterface $repository,
        private readonly CacheVersionService $cacheVersionService,
    ) {
    }

    public function fetchCalendarEvents(int $companyId, array $filters): array
    {
        $rows = $this->repository->fetchCalendarEvents($companyId, $filters);

        return $rows
            ->flatMap(fn (EmployeeAbsence $absence): array => $this->toCalendarEvents($absence))
            ->values()
            ->all();
    }

    public function store(int $companyId, int $userId, array $data): array
    {
        $this->repository->findEmployeeForCompany((int) $data['employee_id'], $companyId);
        $leaveType = $this->repository->findLeaveTypeForCompany((int) $data['leave_type_id'], $companyId);
        $absence = $this->repository->createForCompany($companyId, $this->normalizePayload($data, $userId, true, $companyId, $leaveType));
        $this->invalidateCache($companyId);

        return $this->toArray($absence);
    }

    public function update(int $companyId, int $id, int $userId, array $data): array
    {
        $this->repository->findEmployeeForCompany((int) $data['employee_id'], $companyId);
        $leaveType = $this->repository->findLeaveTypeForCompany((int) $data['leave_type_id'], $companyId);
        $absence = $this->repository->updateInCompany($id, $companyId, $this->normalizePayload($data, $userId, false, $companyId, $leaveType));
        $this->invalidateCache($companyId);

        return $this->toArray($absence);
    }

    public function destroy(int $companyId, int $id): void
    {
        $this->repository->deleteInCompany($id, $companyId);
        $this->invalidateCache($companyId);
    }

    public function show(int $companyId, int $id): array
    {
        $absence = $this->repository->findByIdInCompany($id, $companyId);

        if (! $absence instanceof EmployeeAbsence) {
            abort(404, 'A tavollet rekord nem talalhato.');
        }

        return $this->toArray($absence);
    }

    /**
     * @param array{employee_id:int,leave_type_id:int,date_from:string,date_to:string,note?:?string,status?:?string,sick_leave_category_id?:int|null} $data
     */
    private function normalizePayload(array $data, int $userId, bool $withCreator, int $companyId, LeaveType $leaveType): array
    {
        $dateFrom = CarbonImmutable::parse((string) $data['date_from'])->startOfDay();
        $dateTo = CarbonImmutable::parse((string) $data['date_to'])->startOfDay();
        $minutesPerDay = Settings::getInt('leave.minutes_per_day', 480);
        $dayCount = $dateFrom->diffInDays($dateTo) + 1;
        $sickLeaveCategoryId = $this->resolveSickLeaveCategoryId($companyId, $leaveType, $data['sick_leave_category_id'] ?? null);

        $payload = [
            'employee_id' => (int) $data['employee_id'],
            'leave_type_id' => (int) $data['leave_type_id'],
            'sick_leave_category_id' => $sickLeaveCategoryId,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'minutes_per_day' => $minutesPerDay,
            'total_minutes' => $minutesPerDay * $dayCount,
            'note' => isset($data['note']) && is_string($data['note']) && trim($data['note']) !== ''
                ? trim($data['note'])
                : null,
            'status' => isset($data['status']) && is_string($data['status']) && trim($data['status']) !== ''
                ? trim($data['status'])
                : 'approved',
        ];

        if ($withCreator) {
            $payload['created_by'] = $userId;
        }

        return $payload;
    }

    private function toArray(EmployeeAbsence $absence): array
    {
        $employeeName = trim((string) (($absence->employee?->last_name ?? '').' '.($absence->employee?->first_name ?? '')));

        return [
            'id' => (int) $absence->id,
            'company_id' => (int) $absence->company_id,
            'employee_id' => (int) $absence->employee_id,
            'employee_name' => $employeeName,
            'leave_type_id' => (int) $absence->leave_type_id,
            'leave_type_name' => (string) ($absence->leaveType?->name ?? ''),
            'leave_type_category' => (string) ($absence->leaveType?->category ?? ''),
            'sick_leave_category_id' => $absence->sick_leave_category_id !== null ? (int) $absence->sick_leave_category_id : null,
            'sick_leave_category_name' => $absence->sickLeaveCategory?->name,
            'date_from' => $absence->date_from?->format('Y-m-d'),
            'date_to' => $absence->date_to?->format('Y-m-d'),
            'minutes_per_day' => (int) $absence->minutes_per_day,
            'total_minutes' => (int) $absence->total_minutes,
            'note' => $absence->note,
            'status' => (string) $absence->status,
            'created_by' => (int) $absence->created_by,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toCalendarEvents(EmployeeAbsence $absence): array
    {
        $employeeName = trim((string) (($absence->employee?->last_name ?? '').' '.($absence->employee?->first_name ?? '')));
        $category = (string) ($absence->leaveType?->category ?? '');
        $typeName = (string) ($absence->leaveType?->name ?? '');
        $shortPrefix = $category === 'sick_leave' ? 'Betegszabi' : $typeName;
        $start = $absence->date_from?->startOfDay();
        $end = $absence->date_to?->startOfDay();

        if ($start === null || $end === null) {
            return [];
        }

        $events = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->format('Y-m-d');
            $events[] = [
                'id' => 'absence-'.(int) $absence->id.'-'.$date,
                'title' => trim(sprintf('%s: %s', $shortPrefix, $employeeName)),
                'start' => $date,
                'end' => $cursor->copy()->addDay()->format('Y-m-d'),
                'allDay' => true,
                'editable' => true,
                'className' => ['absence', 'absence-'.$category],
                'extendedProps' => [
                    'entity_type' => 'absence',
                    'absence_id' => (int) $absence->id,
                    'employee_id' => (int) $absence->employee_id,
                    'employee_name' => $employeeName,
                    'leave_type_id' => (int) $absence->leave_type_id,
                    'leave_type_name' => $typeName,
                    'category' => $category,
                    'sick_leave_category_id' => $absence->sick_leave_category_id !== null ? (int) $absence->sick_leave_category_id : null,
                    'sick_leave_category_name' => $absence->sickLeaveCategory?->name,
                    'minutes_per_day' => (int) $absence->minutes_per_day,
                    'total_minutes' => (int) $absence->total_minutes,
                    'note' => $absence->note,
                    'status' => (string) $absence->status,
                    'editable' => true,
                ],
            ];

            $cursor = $cursor->addDay();
        }

        return $events;
    }

    private function invalidateCache(int $companyId): void
    {
        $this->cacheVersionService->bump("absences:{$companyId}:calendar");
        $this->cacheVersionService->bump("absences:{$companyId}:show");
    }

    private function resolveSickLeaveCategoryId(int $companyId, LeaveType $leaveType, mixed $rawCategoryId): ?int
    {
        if ($leaveType->category !== LeaveType::CATEGORY_SICK_LEAVE) {
            return null;
        }

        if ($rawCategoryId === null || $rawCategoryId === '') {
            return null;
        }

        $category = $this->repository->findSickLeaveCategoryForCompany((int) $rawCategoryId, $companyId);

        return (int) $category->id;
    }
}
