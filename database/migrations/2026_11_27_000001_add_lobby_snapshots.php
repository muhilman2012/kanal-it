<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lobby_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('snapshot_interval_minutes')->default(15);
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('snapshot_next_at')->nullable();
            $table->text('snapshot_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lobby_items', fn (Blueprint $table) => $table->dropColumn([
            'snapshot_interval_minutes', 'snapshot_at', 'snapshot_next_at', 'snapshot_error',
        ]));
    }
};
