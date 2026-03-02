<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('category', 50);
            $table->boolean('affects_leave_balance')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code'], 'leave_types_company_code_unique');
            $table->index(['company_id', 'active'], 'leave_types_company_active_idx');
            $table->index(['company_id', 'category'], 'leave_types_company_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
