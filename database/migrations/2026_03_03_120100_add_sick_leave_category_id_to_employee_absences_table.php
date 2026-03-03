<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_absences', function (Blueprint $table): void {
            $table->foreignId('sick_leave_category_id')
                ->nullable()
                ->after('leave_type_id')
                ->constrained('sick_leave_categories')
                ->nullOnDelete();

            $table->index(['company_id', 'sick_leave_category_id'], 'employee_absences_company_sick_leave_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employee_absences', function (Blueprint $table): void {
            $table->dropIndex('employee_absences_company_sick_leave_category_idx');
            $table->dropConstrainedForeignId('sick_leave_category_id');
        });
    }
};
