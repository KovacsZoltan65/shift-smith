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
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies', 'id', 'company_work_shift')
                ->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('name_lc', 100)->storedAs('lower(`name`)');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('work_time_minutes')->nullable();
            $table->boolean('is_flexible')->default(false);
            $table->integer('break_minutes')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id'], 'ws_company_id_idx');
            $table->index(['company_id', 'active'], 'ws_company_active_idx');
            $table->index(['company_id', 'name_lc'], 'ws_company_name_lc_idx');
            $table->index(['start_time'], 'ws_start_time_idx');
            $table->index(['end_time'], 'ws_end_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
