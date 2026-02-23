<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shift_assignments', function (Blueprint $table): void {
            $table->index(['company_id', 'date'], 'ws_ass_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_shift_assignments', function (Blueprint $table): void {
            $table->dropIndex('ws_ass_company_date_idx');
        });
    }
};
