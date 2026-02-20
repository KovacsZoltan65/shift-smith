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
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies', 'id', 'company_work_schedule')
                ->cascadeOnDelete();

            $table->string('name', 150);
            $table->date('date_from');
            $table->date('date_to');

            $table->string('status', 30)->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id'], 'wsched_company_id_idx');
            $table->index(['company_id', 'date_from'], 'wsched_company_date_from_idx');
            $table->index(['company_id', 'status'], 'wsched_company_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
