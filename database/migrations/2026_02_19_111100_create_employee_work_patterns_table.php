<?php

declare(strict_types=1);

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
        Schema::create('employee_work_patterns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('work_pattern_id')->constrained('work_patterns');
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('employee_id');
            $table->index('work_pattern_id');
            $table->index(['employee_id', 'date_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_work_patterns');
    }
};
