<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Carbon\Carbon;

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\WorkShiftAssignment;

class WorkShiftAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        // Csak azok a company-k, ahol van legalább 1 employee és 1 work_shift
        $companyIds = Company::query()
            ->whereIn('id', Employee::query()->select('company_id')->distinct())
            ->whereIn('id', WorkShift::query()->select('company_id')->distinct())
            ->pluck('id');

        if ($companyIds->isEmpty()) {
            $this->command?->warn('⚠️ Nincs olyan Company, ahol egyszerre lenne Employee és WorkShift.');
            return;
        }

        foreach ($companyIds as $companyId) {
            $employeeIds = Employee::query()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->values();

            $shiftIds = WorkShift::query()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->values();

            if ($employeeIds->isEmpty() || $shiftIds->isEmpty()) {
                // elvileg ide már nem jutunk, de maradjon biztos
                $this->command?->warn("⚠️ Company #{$companyId}: nincs elég Employee/WorkShift, kihagyva.");
                continue;
            }

            // Naplista: utolsó 60 nap (mindig lesz legalább 1)
            $days = $this->generateDays(Carbon::now()->subDays(60), Carbon::now()->subDay());

            if ($days->isEmpty()) {
                $this->command?->warn("⚠️ Company #{$companyId}: üres naplista, kihagyva.");
                continue;
            }

            $capacity = $employeeIds->count() * $days->count();
            $target = min(random_int(20, 80), $capacity);

            $pairs = $this->uniqueEmployeeDayPairs($employeeIds, $days, $target);

            $rows = $pairs->map(function (array $pair) use ($companyId, $shiftIds) {
                return [
                    'company_id' => (int) $companyId,
                    'employee_id' => (int) $pair['employee_id'],
                    'work_shift_id' => (int) $shiftIds->random(),
                    'day' => $pair['day'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            WorkShiftAssignment::query()->insert($rows->all());
        }
    }

    /**
     * @return Collection<int, string>  Y-m-d
     */
    private function generateDays(Carbon $from, Carbon $to): Collection
    {
        if ($from->greaterThan($to)) {
            return collect();
        }

        $days = collect();
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days->push($cursor->toDateString());
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param Collection<int, int> $employeeIds
     * @param Collection<int, string> $days
     * @return Collection<int, array{employee_id:int, day:string}>
     */
    private function uniqueEmployeeDayPairs(Collection $employeeIds, Collection $days, int $target): Collection
    {
        $employees = $employeeIds->shuffle()->values();
        $dayList = $days->shuffle()->values();

        $pairs = collect();
        $eCount = $employees->count();
        $i = 0;

        foreach ($dayList as $day) {
            for ($k = 0; $k < $eCount; $k++) {
                if ($pairs->count() >= $target) {
                    return $pairs->values();
                }

                $pairs->push([
                    'employee_id' => (int) $employees[$i % $eCount],
                    'day' => $day,
                ]);

                $i++;
            }
        }

        return $pairs->take($target)->values();
    }
}
