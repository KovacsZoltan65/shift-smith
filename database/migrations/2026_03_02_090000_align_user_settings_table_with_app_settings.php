<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_settings', 'type')) {
                $table->string('type')->default('json')->after('value');
            }

            if (! Schema::hasColumn('user_settings', 'group')) {
                $table->string('group')->default('leave')->after('type');
            }

            if (! Schema::hasColumn('user_settings', 'label')) {
                $table->string('label')->nullable()->after('group');
            }

            if (! Schema::hasColumn('user_settings', 'description')) {
                $table->text('description')->nullable()->after('label');
            }
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            $table->index(['company_id', 'user_id'], 'user_settings_company_user_idx');
            $table->index(['company_id', 'group'], 'user_settings_company_group_idx');
            $table->index(['company_id', 'type'], 'user_settings_company_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropIndex('user_settings_company_user_idx');
            $table->dropIndex('user_settings_company_group_idx');
            $table->dropIndex('user_settings_company_type_idx');

            if (Schema::hasColumn('user_settings', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('user_settings', 'label')) {
                $table->dropColumn('label');
            }

            if (Schema::hasColumn('user_settings', 'group')) {
                $table->dropColumn('group');
            }

            if (Schema::hasColumn('user_settings', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
