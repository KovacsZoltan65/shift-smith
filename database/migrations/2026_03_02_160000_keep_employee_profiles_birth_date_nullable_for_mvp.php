<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Historical no-op: birth_date is stored on employees.
    }

    public function down(): void
    {
        // Historical no-op.
    }
};
