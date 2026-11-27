<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lobby_screens', function (Blueprint $table) {
            $table->string('short_code', 8)->nullable()->unique();
        });
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        foreach (DB::table('lobby_screens')->pluck('id') as $id) {
            do {
                $code = '';
                for ($i = 0; $i < 8; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            } while (DB::table('lobby_screens')->where('short_code', $code)->exists());
            DB::table('lobby_screens')->where('id', $id)->update(['short_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('lobby_screens', fn (Blueprint $table) => $table->dropColumn('short_code'));
    }
};
