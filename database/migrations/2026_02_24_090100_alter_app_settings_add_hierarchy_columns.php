<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            if (!Schema::hasColumn('app_settings', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('value');
            }

            if (!Schema::hasColumn('app_settings', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (!Schema::hasColumn('app_settings', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }

            if (!Schema::hasColumn('app_settings', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('app_settings', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('app_settings', 'updated_at')) {
                $table->dropColumn('updated_at');
            }

            if (Schema::hasColumn('app_settings', 'created_at')) {
                $table->dropColumn('created_at');
            }

            if (Schema::hasColumn('app_settings', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
        });
    }
};

