<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_leave_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('order_index')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code'], 'slc_company_code_unique');
            $table->index(['company_id', 'active'], 'slc_company_active_idx');
            $table->index(['company_id', 'order_index'], 'slc_company_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_leave_categories');
    }
};
