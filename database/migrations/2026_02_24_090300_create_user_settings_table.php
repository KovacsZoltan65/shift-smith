<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('key', 191);
            $table->json('value')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'key'], 'user_settings_user_key_unique');
            $table->index(['user_id', 'key', 'deleted_at'], 'user_settings_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};

