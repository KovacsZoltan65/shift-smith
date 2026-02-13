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
        Schema::create('work_shift_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('work_shift_id')
                ->constrained('work_shifts')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            // melyik napra szól az assignment
            $table->date('day')->index();

            // opcionális: ha a műszakon belül is van időintervallum (ha kell, vedd ki a kommentet)
            // $table->time('start_time')->nullable();
            // $table->time('end_time')->nullable();

            // opcionális: extra meta (pl. megjegyzés, override flag, szín, stb.)
            // $table->json('meta')->nullable();

            $table->timestamps();

            // Gyors szűrés multi-tenant / napi nézetekhez
            $table->index(['company_id', 'day']);
            $table->index(['company_id', 'work_shift_id']);
            $table->index(['company_id', 'employee_id']);

            // Opcionális, de erősen ajánlott:
            // 1 dolgozó 1 napra 1 beosztást kaphasson cégen belül
            $table->unique(['company_id', 'employee_id', 'day'], 'wsa_company_employee_day_unique');

            // Ha engednéd, hogy egy dolgozó ugyanarra a napra több műszakot is kapjon,
            // akkor ezt használd a fenti unique helyett:
            // $table->unique(['company_id', 'employee_id', 'work_shift_id', 'day'], 'wsa_company_employee_shift_day_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_shift_assignments');
    }
};
