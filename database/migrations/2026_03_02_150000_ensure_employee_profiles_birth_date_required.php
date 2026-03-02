<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::create('employee_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('children_count')->default(0);
            $table->unsignedTinyInteger('disabled_children_count')->default(0);
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'employee_id'], 'employee_profiles_company_employee_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::dropIfExists('employee_profiles');
    }
};
