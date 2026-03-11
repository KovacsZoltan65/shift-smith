<?php

declare(strict_types=1);

use App\Models\UserEmployee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_employee')) {
            return;
        }

        if (! Schema::hasColumn('user_employee', 'company_id')) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->index('company_id', 'user_employee_company_idx');
            });
        }

        $this->backfillCompanyIds();
        $this->nullOutConflictingCompanyIds();

        if ($this->indexExists('user_employee', 'user_employee_unique')) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->dropUnique('user_employee_unique');
            });
        }

        if (! $this->indexExists('user_employee', 'user_employee_user_company_unique')) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->unique(['user_id', 'company_id'], 'user_employee_user_company_unique');
            });
        }

        if (! UserEmployee::query()->whereNull('company_id')->exists()) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_employee')) {
            return;
        }

        if ($this->indexExists('user_employee', 'user_employee_user_company_unique')) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->dropUnique('user_employee_user_company_unique');
            });
        }

        if ($this->indexExists('user_employee', 'user_employee_company_idx')) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->dropIndex('user_employee_company_idx');
            });
        }

        if ($this->foreignKeyExists('user_employee', 'user_employee_company_id_foreign')) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->dropForeign('user_employee_company_id_foreign');
            });
        }

        if (! $this->indexExists('user_employee', 'user_employee_unique')) {
            Schema::table('user_employee', function (Blueprint $table): void {
                $table->unique(['user_id', 'employee_id'], 'user_employee_unique');
            });
        }

        Schema::table('user_employee', function (Blueprint $table): void {
            $table->dropColumn('company_id');
        });
    }

    private function backfillCompanyIds(): void
    {
        UserEmployee::query()
            ->with([
                'employee:id,company_id',
                'employee.company:id',
                'employee.companies:id',
            ])
            ->orderBy('id')
            ->lazyById()
            ->each(function (UserEmployee $userEmployee): void {
                if ($userEmployee->company_id !== null) {
                    return;
                }

                $employee = $userEmployee->employee;
                if ($employee === null) {
                    $this->logLocal('user_employee.employee_missing', $userEmployee, null);

                    return;
                }

                $companyId = null;

                if ($employee->company !== null && is_numeric($employee->company->id)) {
                    $companyId = (int) $employee->company->id;
                }

                if ($companyId === null) {
                    $firstPivotCompanyId = $employee->companies()
                        ->orderBy('companies.id')
                        ->value('companies.id');

                    if (is_numeric($firstPivotCompanyId)) {
                        $companyId = (int) $firstPivotCompanyId;
                    }
                }

                if ($companyId === null) {
                    $this->logLocal('user_employee.company_unresolved', $userEmployee, null);

                    return;
                }

                $userEmployee->forceFill(['company_id' => $companyId])->saveQuietly();
            });
    }

    private function nullOutConflictingCompanyIds(): void
    {
        /** @var \Illuminate\Support\Collection<int, object{user_id:int,company_id:int}> $conflicts */
        $conflicts = UserEmployee::query()
            ->select(['user_id', 'company_id'])
            ->whereNotNull('company_id')
            ->groupBy(['user_id', 'company_id'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($conflicts as $conflict) {
            $rows = UserEmployee::query()
                ->where('user_id', (int) $conflict->user_id)
                ->where('company_id', (int) $conflict->company_id)
                ->orderBy('id')
                ->get();

            $rows
                ->skip(1)
                ->each(function (UserEmployee $userEmployee): void {
                    $userEmployee->forceFill(['company_id' => null])->saveQuietly();
                    $this->logLocal('user_employee.duplicate_user_company_preserved_as_null', $userEmployee, null);
                });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            /** @var array<int, object{name:string}> $indexes */
            $indexes = DB::select(sprintf('PRAGMA index_list(%s)', $table));

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = (string) DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return $result !== null;
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            /** @var array<int, object{table:string,from:string}> $foreignKeys */
            $foreignKeys = DB::select(sprintf('PRAGMA foreign_key_list(%s)', $table));

            foreach ($foreignKeys as $foreignKey) {
                if (($foreignKey->table ?? null) === 'companies' && ($foreignKey->from ?? null) === 'company_id') {
                    return true;
                }
            }

            return false;
        }

        $database = (string) DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT 1
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE table_schema = ?
               AND table_name = ?
               AND constraint_name = ?
             LIMIT 1',
            [$database, $table, $foreignKeyName]
        );

        return $result !== null;
    }

    private function logLocal(string $message, UserEmployee $userEmployee, mixed $extra): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Log::warning($message, [
            'user_employee_id' => (int) $userEmployee->id,
            'user_id' => (int) $userEmployee->user_id,
            'employee_id' => (int) $userEmployee->employee_id,
            'company_id' => $userEmployee->company_id !== null ? (int) $userEmployee->company_id : null,
            'extra' => $extra,
        ]);
    }
};
