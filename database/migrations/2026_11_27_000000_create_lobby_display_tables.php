<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lobby_screens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('location', 120)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lobby_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lobby_screen_id')->constrained('lobby_screens')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('kind', 12);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('website_url')->nullable();
            $table->string('fit', 10)->default('contain');
            $table->unsignedInteger('duration_seconds')->default(15);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('mode', 12)->default('always');
            $table->json('weekdays')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('starts_at', 5)->nullable();
            $table->string('ends_at', 5)->nullable();
            $table->boolean('exclusive')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['lobby_screen_id', 'active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lobby_items');
        Schema::dropIfExists('lobby_screens');
    }
};
