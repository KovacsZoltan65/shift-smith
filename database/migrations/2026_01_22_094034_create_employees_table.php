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
                ->constrained()
                ->onDelete('cascade');

            $table->string('first_name')->comment('Keresztnév');
            $table->string('last_name')->comment('Vezetéknév');
            $table->string('email')->unique()->comment('E-mail cím');
            $table->string('address')->nullable()->comment('Lakcím');
            $table->string('position')->nullable()->comment('Beosztás');
            $table->string('phone')->nullable()->comment('Telefonszám');
            $table->date('hired_at')->nullable()->comment('Felvétel dátuma');
            $table->boolean('active')->default(1)->index()->comment('Aktív');
            $table->timestamps();
            $table->softDeletes();
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
