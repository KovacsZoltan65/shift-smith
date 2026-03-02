<?php

declare(strict_types=1);

use App\Models\CompanyEmployee;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_employee') || ! Schema::hasTable('employees')) {
            return;
        }

        Employee::query()
            ->select(['id', 'company_id'])
            ->whereNotNull('company_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                CompanyEmployee::withoutEvents(function () use ($rows): void {
                    foreach ($rows as $row) {
                        $companyId = (int) $row->company_id;
                        if ($companyId <= 0) {
                            continue;
                        }

                        CompanyEmployee::query()->updateOrCreate(
                            [
                                'company_id' => $companyId,
                                'employee_id' => (int) $row->id,
                            ],
                            [
                                'active' => true,
                            ]
                        );
                    }
                });
            });
    }

    public function down(): void
    {
        // Intentionally no-op: backfill rollback would be destructive.
    }
};
