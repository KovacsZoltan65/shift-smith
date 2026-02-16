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
        Schema::create('work_shift_break_time_assignments', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('work_shift_id')
                ->constrained('work_shifts', 'id', 'work_shift_work_shift_break_time_assignment')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('break_time_id')
                ->constrained('break_times', 'id', 'break_time_work_shift_break_time_assignment')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['work_shift_id', 'break_time_id'],
                'ws_bt_ws_id_bt_id_unique'
            );

            $table->index(['work_shift_id'], 'ws_bt_ws_id_idx');
            $table->index(['break_time_id'], 'ws_bt_bt_id_idx');
            $table->index(['active'], 'ws_bt_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_shift_break_time_assignments');
    }
};
