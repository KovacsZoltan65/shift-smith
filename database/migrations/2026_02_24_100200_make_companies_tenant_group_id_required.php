<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('companies', 'tenant_group_id')) {
            throw new \RuntimeException('Missing companies.tenant_group_id column. Run previous tenancy migrations first.');
        }

        $nullCount = DB::table('companies')
            ->whereNull('tenant_group_id')
            ->count();

        if ($nullCount > 0) {
            throw new \RuntimeException('Backfill is required before enforcing NOT NULL. Run: php artisan tenancy:backfill-tenant-groups');
        }

        DB::statement('ALTER TABLE companies MODIFY tenant_group_id BIGINT UNSIGNED NOT NULL');

        if (! $this->hasTenantGroupForeignKey()) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->foreign('tenant_group_id')
                    ->references('id')
                    ->on('tenant_groups')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $foreignKeyName = $this->getTenantGroupForeignKeyName();

        if ($foreignKeyName !== null) {
            DB::statement(sprintf('ALTER TABLE companies DROP FOREIGN KEY `%s`', $foreignKeyName));
        }

        if (Schema::hasColumn('companies', 'tenant_group_id')) {
            DB::statement('ALTER TABLE companies MODIFY tenant_group_id BIGINT UNSIGNED NULL');
        }
    }

    private function hasTenantGroupForeignKey(): bool
    {
        return $this->getTenantGroupForeignKeyName() !== null;
    }

    private function getTenantGroupForeignKeyName(): ?string
    {
        /** @var object{CONSTRAINT_NAME: string}|null $row */
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            ['companies', 'tenant_group_id']
        );

        return $row?->CONSTRAINT_NAME;
    }
};
