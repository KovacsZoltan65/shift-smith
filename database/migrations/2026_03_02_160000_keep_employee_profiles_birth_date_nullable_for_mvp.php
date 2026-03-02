<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table): void {
            // MVP-safe: a kötelezőséget validáció/UI biztosítja, DB-szinten marad nullable
            // amíg az összes meglévő rekord nincs garantáltan kitöltve.
            $table->date('birth_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Szándékosan no-op. A későbbi NOT NULL migráció csak teljes backfill után biztonságos.
    }
};
