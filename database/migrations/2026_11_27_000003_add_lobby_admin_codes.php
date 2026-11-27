<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lobby_screens', function (Blueprint $table) {
            $table->string('admin_code', 24)->nullable()->unique();
        });
        foreach (DB::table('lobby_screens')->pluck('id') as $id) {
            do {
                $code = bin2hex(random_bytes(12));
            } while (DB::table('lobby_screens')->where('admin_code', $code)->exists());
            DB::table('lobby_screens')->where('id', $id)->update(['admin_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('lobby_screens', fn (Blueprint $table) => $table->dropColumn('admin_code'));
    }
};
