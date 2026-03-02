<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_employee', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'employee_id'], 'user_employee_unique');
            $table->index('user_id', 'user_employee_user_idx');
            $table->index('employee_id', 'user_employee_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_employee');
    }
};
