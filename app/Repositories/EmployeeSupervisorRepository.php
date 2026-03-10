<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Org\EmployeeSupervisor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class EmployeeSupervisorRepository implements EmployeeSupervisorRepositoryInterface
{
    public function findActiveSupervisor(int $companyId, int $employeeId, CarbonInterface $date): ?EmployeeSupervisor
    {
        $day = CarbonImmutable::instance($date)->toDateString();

        /** @var EmployeeSupervisor|null $row */
        $row = EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->orderByDesc('valid_from')
            ->first();

        return $row;
    }

    public function listDirectSubordinates(int $companyId, int $supervisorEmployeeId, CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->toDateString();

        return EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->where('supervisor_employee_id', $supervisorEmployeeId)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->pluck('employee_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function listSubtreeEmployeeIds(int $companyId, int $supervisorEmployeeId, CarbonInterface $date): array
    {
        $visited = [];
        $queue = [$supervisorEmployeeId];
        $depth = 0;

        while ($queue !== [] && $depth < 200) {
            $currentSupervisorId = array_shift($queue);
            if (! \is_int($currentSupervisorId)) {
                $depth++;
                continue;
            }

            foreach ($this->listDirectSubordinates($companyId, $currentSupervisorId, $date) as $subordinateId) {
                if (isset($visited[$subordinateId])) {
                    continue;
                }

                $visited[$subordinateId] = true;
                $queue[] = $subordinateId;
            }

            $depth++;
        }

        return array_map(static fn (string $id): int => (int) $id, array_keys($visited));
    }

    public function hasOverlappingSupervisorPeriod(
        int $companyId,
        int $employeeId,
        CarbonInterface $from,
        ?CarbonInterface $to = null,
        ?int $ignoreId = null
    ): bool {
        $fromDate = CarbonImmutable::instance($from)->toDateString();
        $toDate = $to !== null ? CarbonImmutable::instance($to)->toDateString() : null;

        return EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($fromDate, $toDate): void {
                $query->whereDate('valid_from', '<=', $toDate ?? '9999-12-31')
                    ->where(function ($nested) use ($fromDate): void {
                        $nested->whereNull('valid_to')
                            ->orWhereDate('valid_to', '>=', $fromDate);
                    });
            })
            ->exists();
    }

    public function wouldCreateCycle(
        int $companyId,
        int $employeeId,
        int $supervisorEmployeeId,
        CarbonInterface $date
    ): bool {
        if ($employeeId === $supervisorEmployeeId) {
            return true;
        }

        $reachable = $this->listSubtreeEmployeeIds($companyId, $employeeId, $date);

        return in_array($supervisorEmployeeId, $reachable, true);
    }

    public function closeActivePeriod(int $companyId, int $employeeId, CarbonInterface $newValidFrom): ?EmployeeSupervisor
    {
        $day = CarbonImmutable::instance($newValidFrom)->toDateString();

        /** @var EmployeeSupervisor|null $active */
        $active = EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->orderByDesc('valid_from')
            ->lockForUpdate()
            ->first();

        if (! $active instanceof EmployeeSupervisor) {
            return null;
        }

        $newTo = CarbonImmutable::instance($newValidFrom)->subDay()->toDateString();
        $active->valid_to = $newTo;
        $active->save();

        return $active->refresh();
    }

    public function createNewRelation(
        int $companyId,
        int $employeeId,
        int $supervisorEmployeeId,
        CarbonInterface $validFrom,
        ?int $createdByUserId = null
    ): EmployeeSupervisor {
        /** @var EmployeeSupervisor $row */
        $row = EmployeeSupervisor::query()->create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'supervisor_employee_id' => $supervisorEmployeeId,
            'valid_from' => CarbonImmutable::instance($validFrom)->toDateString(),
            'valid_to' => null,
            'created_by_user_id' => $createdByUserId,
        ]);

        return $row->refresh();
    }

    public function listActiveRelations(int $companyId, CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->toDateString();

        return EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->whereDate('valid_from', '<=', $day)
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $day);
            })
            ->get(['employee_id', 'supervisor_employee_id'])
            ->map(static fn (EmployeeSupervisor $row): array => [
                'employee_id' => (int) $row->employee_id,
                'supervisor_employee_id' => (int) $row->supervisor_employee_id,
            ])
            ->values()
            ->all();
    }

    public function listActiveSupervisorIdsGroupedByEmployee(int $companyId, CarbonInterface $date): array
    {
        $grouped = [];

        foreach ($this->listActiveRelations($companyId, $date) as $row) {
            $employeeId = (int) $row['employee_id'];
            $supervisorId = (int) $row['supervisor_employee_id'];

            if (! array_key_exists($employeeId, $grouped)) {
                $grouped[$employeeId] = [];
            }

            $grouped[$employeeId][] = $supervisorId;
        }

        return $grouped;
    }

    public function listCompanyHistoryRows(int $companyId): array
    {
        return EmployeeSupervisor::query()
            ->where('company_id', $companyId)
            ->orderBy('employee_id')
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get(['id', 'employee_id', 'supervisor_employee_id', 'valid_from', 'valid_to'])
            ->map(static fn (EmployeeSupervisor $row): array => [
                'id' => (int) $row->id,
                'employee_id' => (int) $row->employee_id,
                'supervisor_employee_id' => (int) $row->supervisor_employee_id,
                'valid_from' => (string) $row->valid_from?->format('Y-m-d'),
                'valid_to' => $row->valid_to?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    public function listSupervisorHistory(int $companyId, int $employeeId): array
    {
        /** @var array<int, EmployeeSupervisor> $rows */
        $rows = EmployeeSupervisor::query()
            ->with(['supervisorEmployee:id,first_name,last_name'])
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->all();

        return $rows;
    }
}
