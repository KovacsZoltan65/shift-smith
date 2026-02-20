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
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('position_id')
                ->nullable()
                ->after('address')
                ->constrained('positions')
                ->nullOnDelete();
        });

        $positions = DB::table('employees')
            ->select('company_id', 'position')
            ->whereNotNull('position')
            ->where('position', '<>', '')
            ->distinct()
            ->get();

        foreach ($positions as $row) {
            DB::table('positions')->updateOrInsert(
                [
                    'company_id' => (int) $row->company_id,
                    'name' => (string) $row->position,
                ],
                [
                    'active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::statement('
            UPDATE employees e
            JOIN positions p ON p.name = e.position AND p.company_id = e.company_id
            SET e.position_id = p.id
            WHERE e.position IS NOT NULL AND e.position <> ""
        ');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('position')->nullable()->after('address');
        });

        DB::statement('
            UPDATE employees e
            LEFT JOIN positions p ON p.id = e.position_id AND p.company_id = e.company_id
            SET e.position = p.name
            WHERE e.position_id IS NOT NULL
        ');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('position_id');
        });
    }
};
