<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_groups', 'code')) {
                $table->string('code', 50)->nullable()->after('name');
            }

            if (! Schema::hasColumn('tenant_groups', 'status')) {
                $table->string('status', 50)->nullable()->after('database_name');
            }

            if (! Schema::hasColumn('tenant_groups', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }

            if (! Schema::hasColumn('tenant_groups', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        $rows = DB::table('tenant_groups')->select(['id', 'name', 'slug'])->get();
        $usedCodes = [];

        foreach ($rows as $row) {
            $base = (string) ($row->slug ?: $row->name ?: 'tenant-group');
            $candidate = strtoupper(Str::of($base)->replace('-', '_')->replace(' ', '_')->slug('_')->value());
            $candidate = trim($candidate, '_');
            $candidate = $candidate !== '' ? Str::limit($candidate, 50, '') : 'TENANT_GROUP';

            $final = $candidate;
            $counter = 1;

            while (\in_array($final, $usedCodes, true) || DB::table('tenant_groups')->where('code', $final)->where('id', '!=', $row->id)->exists()) {
                $suffix = '_'.$counter;
                $final = Str::limit($candidate, 50 - strlen($suffix), '').$suffix;
                $counter++;
            }

            DB::table('tenant_groups')
                ->where('id', $row->id)
                ->update(['code' => $final]);

            $usedCodes[] = $final;
        }

        Schema::table('tenant_groups', function (Blueprint $table): void {
            $table->string('code', 50)->nullable(false)->change();
            $table->unique('code', 'tenant_groups_code_unique');
            $table->index('status', 'tenant_groups_status_idx');
            $table->index(['active', 'status'], 'tenant_groups_active_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_groups', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('tenant_groups', 'notes')) {
                $table->dropColumn('notes');
            }

            if (Schema::hasColumn('tenant_groups', 'status')) {
                $table->dropIndex('tenant_groups_status_idx');
                $table->dropIndex('tenant_groups_active_status_idx');
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('tenant_groups', 'code')) {
                $table->dropUnique('tenant_groups_code_unique');
                $table->dropColumn('code');
            }
        });
    }
};
