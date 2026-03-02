<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_employee', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'employee_id'], 'company_employee_unique');
            $table->index('company_id', 'company_employee_company_idx');
            $table->index('employee_id', 'company_employee_employee_idx');
            $table->index(['company_id', 'active'], 'company_employee_company_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_employee');
    }
};
