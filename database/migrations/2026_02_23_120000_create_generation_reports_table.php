<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_reports', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies', 'id', 'generation_reports_company_fk')
                ->cascadeOnDelete();

            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules', 'id', 'generation_reports_work_schedule_fk')
                ->cascadeOnDelete();

            $table->json('input_json');
            $table->json('result_json');

            $table->foreignId('created_by')
                ->constrained('users', 'id', 'generation_reports_created_by_fk')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'work_schedule_id'], 'generation_reports_company_schedule_idx');
            $table->index(['company_id', 'created_at'], 'generation_reports_company_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_reports');
    }
};
