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
        Schema::create('user_menu_stats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // menü elem kulcsa: nálunk route name (pl. users.index)
            $table->string('menu_key', 100);

            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'menu_key']);
            $table->index(['user_id', 'hit_count']);
            $table->index(['user_id', 'last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_menu_stats');
    }
};
