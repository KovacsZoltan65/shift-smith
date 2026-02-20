<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id()->comment('Rekord azonosító');

            $table->foreignId('company_id')
                ->constrained('companies', 'id', 'company_employee')
                ->onDelete('cascade');

            $table->string('first_name')->comment('Keresztnév');
            $table->string('last_name')->comment('Vezetéknév');
            $table->string('email')->unique()->comment('E-mail cím');
            $table->string('address')->nullable()->comment('Lakcím');
            $table->foreignId('position_id')->nullable()->comment('Pozíció azonosító');
            $table->string('phone')->nullable()->comment('Telefonszám');
            $table->date('hired_at')->nullable()->comment('Felvétel dátuma');
            $table->boolean('active')->default(1)->index()->comment('Aktív');

            $table->timestamps();
            $table->softDeletes();

            $table->index('first_name', 'employees_first_name_idx');
            $table->index('last_name', 'employees_last_name_idx');
            $table->index('email', 'employees_email_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
