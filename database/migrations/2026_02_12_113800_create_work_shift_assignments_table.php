<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_shift_assignments', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies', 'id', 'company_work_shift_assignment')
                ->cascadeOnDelete();

            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules', 'id', 'work_schedule_work_shift_assignment')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees','id', 'employee_work_shift_assignment')
                ->cascadeOnDelete();

            $table->foreignId('work_shift_id')
                ->constrained('work_shifts', 'id', 'work_shift_work_shift_assignment')
                ->cascadeOnDelete();

            $table->date('date');

            $table->timestamps();

            $table->index(['company_id'], 'ws_ass_company_id_idx');
            $table->index(['work_schedule_id'], 'ws_ass_work_schedule_id_idx');
            $table->index(['employee_id'], 'ws_ass_employee_id_idx');
            $table->index(['work_shift_id'], 'ws_ass_work_shift_id_idx');
            $table->index(['date'], 'ws_ass_date_idx');

            $table->unique(
                ['company_id', 'employee_id', 'date'],
                'wsa_company_employee_date_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_shift_assignments');
    }
};
