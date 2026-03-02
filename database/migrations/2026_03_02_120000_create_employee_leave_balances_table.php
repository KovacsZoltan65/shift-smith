<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_balances', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('leave_type', 64);
            $table->date('employment_start_date')->nullable();
            $table->integer('total_minutes')->default(0);
            $table->integer('used_minutes')->default(0);
            $table->integer('remaining_minutes')->default(0);
            $table->integer('carried_over_minutes')->default(0);
            $table->date('carryover_valid_until')->nullable();
            $table->string('rule_applied', 64)->nullable();
            $table->boolean('has_employer_exception')->default(false);
            $table->json('employee_blocked_periods')->nullable();
            $table->boolean('agreement_age_bonus_transfer')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'company_id', 'year', 'leave_type'], 'employee_leave_balances_unique_scope');
            $table->index(['company_id', 'year'], 'employee_leave_balances_company_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_balances');
    }
};
