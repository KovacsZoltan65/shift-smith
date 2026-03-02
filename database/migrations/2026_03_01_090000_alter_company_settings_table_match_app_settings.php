<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            if (!Schema::hasColumn('company_settings', 'type')) {
                $table->string('type', 50)->default('json')->after('value');
            }

            if (!Schema::hasColumn('company_settings', 'group')) {
                $table->string('group', 100)->default('leave')->after('type');
            }

            if (!Schema::hasColumn('company_settings', 'label')) {
                $table->string('label', 190)->nullable()->after('group');
            }

            if (!Schema::hasColumn('company_settings', 'description')) {
                $table->text('description')->nullable()->after('label');
            }
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->index(['company_id', 'group'], 'company_settings_company_group_idx');
            $table->index(['company_id', 'type'], 'company_settings_company_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropIndex('company_settings_company_group_idx');
            $table->dropIndex('company_settings_company_type_idx');
        });
    }
};
