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
        Schema::create('break_times', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id'], 'bt_company_id_idx');
            $table->index(['name'], 'bt_name_idx');
            $table->index(['start_time'], 'bt_start_idx');
            $table->index(['end_time'], 'bt_end_idx');
            $table->index(['start_time', 'end_time'], 'bt_start_end_idx');
            $table->index(['company_id', 'active'], 'bt_company_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_time');
    }
};
