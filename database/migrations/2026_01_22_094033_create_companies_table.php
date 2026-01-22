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
        Schema::create('companies', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id()->comment('Rekord azonosító');

            $table->string('name')->comment('Név');

            // Generált oszlop az indexbarát case-insensitive kereséshez
            $table->string('name_lc')->storedAs('LOWER(name)');

            $table->string('email')->nullable()->comment('Email');

            $table->string('address')->nullable()->comment('Cím');
            $table->string('phone')->nullable()->comment('Telefon');
            $table->boolean('active')->default(1)->comment('Aktív');

            $table->timestamps();
            $table->softDeletes();

            // Indexek külön, jól áttekinthetően
            $table->index('name', 'companies_name_idx');
            $table->index('name_lc', 'companies_name_lc_idx');
            $table->index('email', 'companies_email_idx');
            $table->index('active', 'companies_active_idx');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
