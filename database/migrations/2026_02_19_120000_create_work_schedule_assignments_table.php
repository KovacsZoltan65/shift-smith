<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * V2 schedule-alapú kiosztások tábla létrehozása.
     */
    public function up(): void
    {
        Schema::create('work_schedule_assignments', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies', 'id', 'wsa2_company_fk')
                ->cascadeOnDelete();

            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules', 'id', 'wsa2_schedule_fk')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees', 'id', 'wsa2_employee_fk')
                ->cascadeOnDelete();

            $table->foreignId('work_shift_id')
                ->constrained('work_shifts', 'id', 'wsa2_shift_fk')
                ->cascadeOnDelete();

            $table->date('day');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'work_schedule_id', 'employee_id', 'day'],
                'wsa2_company_schedule_employee_day_unique'
            );

            $table->index(['company_id', 'day'], 'wsa2_company_day_idx');
            $table->index(['work_schedule_id', 'day'], 'wsa2_schedule_day_idx');
            $table->index(['employee_id', 'day'], 'wsa2_employee_day_idx');
        });
    }

    /**
     * Tábla visszagörgetése.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_assignments');
    }
};
