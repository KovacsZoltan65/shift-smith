<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_absences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('minutes_per_day');
            $table->unsignedInteger('total_minutes');
            $table->string('note', 500)->nullable();
            $table->string('status', 50)->default('approved');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id'], 'employee_absences_company_employee_idx');
            $table->index(['company_id', 'date_from'], 'employee_absences_company_date_from_idx');
            $table->index(['company_id', 'leave_type_id'], 'employee_absences_company_leave_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_absences');
    }
};
