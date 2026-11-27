<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcer_settings', function (Blueprint $table) {
            $table->id();
            $table->string('player_code', 12)->unique();
            $table->json('closing_times');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
        DB::table('announcer_settings')->insert([
            'id' => 1,
            'player_code' => strtoupper(bin2hex(random_bytes(6))),
            'closing_times' => json_encode(['1'=>'16:00','2'=>'16:00','3'=>'16:00','4'=>'16:00','5'=>'16:30']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('announcer_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('audio_path');
            $table->string('audio_name');
            $table->string('time_type', 20);
            $table->string('play_time', 5)->nullable();
            $table->unsignedSmallInteger('minutes_before_close')->nullable();
            $table->json('weekdays');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('bell')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('announcer_blasts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('text');
            $table->dateTime('scheduled_at');
            $table->boolean('bell')->default(true);
            $table->string('status', 20)->default('queued');
            $table->timestamp('played_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });
        Schema::create('announcer_plays', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->string('title', 150);
            $table->string('kind', 20);
            $table->timestamp('claimed_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('playing');
            $table->string('error', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcer_plays');
        Schema::dropIfExists('announcer_blasts');
        Schema::dropIfExists('announcer_schedules');
        Schema::dropIfExists('announcer_settings');
    }
};
