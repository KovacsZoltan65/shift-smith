<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('company_id')->index();
            $table->string('key', 191);
            $table->json('value')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'key'], 'company_settings_company_key_unique');
            $table->index(['company_id', 'key', 'deleted_at'], 'company_settings_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};

