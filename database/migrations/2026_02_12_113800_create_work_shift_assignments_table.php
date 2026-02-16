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

            $table->foreignId('employee_id')
                ->constrained('employees','id', 'employee_work_shift_assignment')
                ->cascadeOnDelete();

            $table->foreignId('work_shift_id')
                ->constrained('work_shifts', 'id', 'work_shift_work_shift_assignment')
                ->cascadeOnDelete();

            $table->date('day')->index();

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id'], 'ws_ass_company_id_idx');
            $table->index(['employee_id'], 'ws_ass_employee_id_idx');
            $table->index(['work_shift_id'], 'ws_ass_work_shift_id_idx');
            $table->index(['company_id', 'employee_id'], 'ws_ass_company_employee_idx');
            $table->index(['active'], 'ws_ass_active_idx');

            $table->unique(
                ['company_id', 'employee_id', 'day'],
                'wsa_company_employee_day_unique'
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
