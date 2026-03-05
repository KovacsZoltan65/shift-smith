<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_supervisors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->foreignId('supervisor_employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'valid_from'], 'emp_sup_company_employee_from_idx');
            $table->index(['company_id', 'supervisor_employee_id', 'valid_from'], 'emp_sup_company_supervisor_from_idx');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE employee_supervisors ADD CONSTRAINT employee_supervisors_valid_range_chk CHECK (valid_to IS NULL OR valid_to >= valid_from)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employee_supervisors DROP CONSTRAINT IF EXISTS employee_supervisors_valid_range_chk');
        }

        Schema::dropIfExists('employee_supervisors');
    }
};

