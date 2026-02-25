<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (!Schema::hasColumn('companies', 'tenant_group_id')) {
                $table->foreignId('tenant_group_id')->nullable()->index()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'tenant_group_id')) {
                $table->dropColumn('tenant_group_id');
            }
        });
    }
};
