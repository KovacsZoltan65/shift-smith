<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_org_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('position_key', 191);
            $table->string('position_label', 191);
            $table->string('org_level', 32);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['company_id', 'position_key'], 'position_org_levels_company_key_unique');
            $table->index(['company_id', 'org_level'], 'position_org_levels_company_level_idx');
            $table->index(['company_id', 'active'], 'position_org_levels_company_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_org_levels');
    }
};

