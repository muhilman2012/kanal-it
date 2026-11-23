<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pinned_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('url')->nullable();
            $table->string('filename')->nullable();
            $table->string('mime')->nullable();
            $table->longText('attachment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinned_posts');
    }
};