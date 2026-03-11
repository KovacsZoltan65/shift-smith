<?php

declare(strict_types=1);

use App\Models\UserEmployee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('user_employee', 'user_employee_company_employee_unique')) {
            return;
        }

        $duplicates = UserEmployee::query()
            ->selectRaw('company_id, employee_id, COUNT(*) as aggregate')
            ->groupBy(['company_id', 'employee_id'])
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $pairs = $duplicates
                ->map(fn (UserEmployee $row): string => sprintf(
                    '[company_id=%d, employee_id=%d, count=%d]',
                    (int) $row->company_id,
                    (int) $row->employee_id,
                    (int) ($row->aggregate ?? 0),
                ))
                ->implode(', ');

            throw new RuntimeException(
                'Cannot add UNIQUE(company_id, employee_id) on user_employee because duplicate assignments exist: '.$pairs
            );
        }

        Schema::table('user_employee', function (Blueprint $table): void {
            $table->unique(['company_id', 'employee_id'], 'user_employee_company_employee_unique');
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('user_employee', 'user_employee_company_employee_unique')) {
            return;
        }

        Schema::table('user_employee', function (Blueprint $table): void {
            $table->dropUnique('user_employee_company_employee_unique');
        });
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
};
