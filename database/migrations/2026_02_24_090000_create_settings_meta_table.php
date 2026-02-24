<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_meta', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 191)->unique();
            $table->string('group', 120)->index();
            $table->string('label', 191);
            $table->string('type', 32);
            $table->json('default_value')->nullable();
            $table->text('description')->nullable();
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->unsignedInteger('order_index')->default(0)->index();
            $table->boolean('is_editable')->default(true)->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_meta');
    }
};

