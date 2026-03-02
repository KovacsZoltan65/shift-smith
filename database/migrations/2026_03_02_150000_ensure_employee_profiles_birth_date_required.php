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
        if (! Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('birth_date')->nullable();
                $table->unsignedTinyInteger('children_count')->default(0);
                $table->unsignedTinyInteger('disabled_children_count')->default(0);
                $table->boolean('is_disabled')->default(false);
                $table->timestamps();

                $table->unique(['company_id', 'employee_id'], 'employee_profiles_company_employee_unique');
            });
        }

        $hasNullBirthDates = DB::table('employee_profiles')->whereNull('birth_date')->exists();
        if ($hasNullBirthDates) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->date('birth_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->change();
        });
    }
};
