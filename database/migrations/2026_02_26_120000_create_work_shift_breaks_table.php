<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shift_breaks', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->foreignId('work_shift_id')
                ->constrained('work_shifts')
                ->cascadeOnDelete();
            $table->time('break_start_time');
            $table->time('break_end_time');
            $table->unsignedInteger('break_minutes');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('work_shift_id');
            $table->index(['company_id', 'work_shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shift_breaks');
    }
};
