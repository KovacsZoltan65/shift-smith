<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->after('hired_at');
            $table->unsignedTinyInteger('children_count')->default(0)->after('birth_date');
            $table->unsignedTinyInteger('disabled_children_count')->default(0)->after('children_count');
            $table->boolean('is_disabled')->default(false)->after('disabled_children_count');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn([
                'birth_date',
                'children_count',
                'disabled_children_count',
                'is_disabled',
            ]);
        });
    }
};
