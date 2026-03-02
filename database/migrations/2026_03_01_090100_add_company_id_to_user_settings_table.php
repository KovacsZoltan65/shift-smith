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
            if (!Schema::hasColumn('user_settings', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('user_id')->index();
            }
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropUnique('user_settings_user_key_unique');
            $table->unique(['user_id', 'company_id', 'key'], 'user_settings_user_company_key_unique');
            $table->index(['user_id', 'company_id', 'key', 'deleted_at'], 'user_settings_company_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            $table->dropUnique('user_settings_user_company_key_unique');
            $table->dropIndex('user_settings_company_lookup_idx');
            $table->unique(['user_id', 'key'], 'user_settings_user_key_unique');

            if (Schema::hasColumn('user_settings', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};
