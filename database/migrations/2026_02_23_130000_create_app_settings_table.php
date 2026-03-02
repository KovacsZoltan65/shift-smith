<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {

            $table->id();

            $table->string('key', 190)->unique();
            $table->json('value')->nullable();

            // opcionális, de később jól jön (típus/label/desc UI-hoz):
            $table->string('type', 50)->default('json'); // int|bool|string|json
            $table->string('group', 100)->default('leave');
            $table->string('label', 190)->nullable();
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['group'], 'app_settings_group_idx');
        /*
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            
            $table->increments('id')->comment('Rekord azonosító');
            $table->string('key')->default('')->comment('Kulcs');
            $table->string('value')->default('')->comment('Érték');

            $table->timestamps();
            $table->softDeletes();

            $table->unique('key', 'app_settings_key_unique');
            */
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
