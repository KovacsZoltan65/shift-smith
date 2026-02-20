<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_patterns', function (Blueprint $table): void {
            $table->dropColumn(['type', 'cycle_length_days', 'weekly_minutes']);

            $table->unsignedInteger('daily_work_minutes')->after('name');
            $table->unsignedInteger('break_minutes')->default(0)->after('daily_work_minutes');
            $table->time('core_start_time')->nullable()->after('break_minutes');
            $table->time('core_end_time')->nullable()->after('core_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('work_patterns', function (Blueprint $table): void {
            $table->dropColumn([
                'daily_work_minutes',
                'break_minutes',
                'core_start_time',
                'core_end_time',
            ]);

            $table->string('type', 32)->after('name');
            $table->unsignedInteger('cycle_length_days')->nullable()->after('type');
            $table->unsignedInteger('weekly_minutes')->nullable()->after('cycle_length_days');
        });
    }
};
