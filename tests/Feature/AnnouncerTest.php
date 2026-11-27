<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\User;
use App\Services\AnnouncerSchedule;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AnnouncerTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaPath = storage_path('framework/testing/announcer-media-'.getmypid());
        config(['announcer.media_path' => $this->mediaPath]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->mediaPath);
        parent::tearDown();
    }

    public function test_dedicated_player_has_separate_access_and_admin_permission(): void
    {
        $code = DB::table('announcer_settings')->value('player_code');
        $this->assertSame(12, strlen($code));
        $this->get('/pengumuman')->assertRedirect('/login');
        $this->get('/speaker/'.$code)->assertOk()->assertSee('announcer-player.js');
        $this->get('/speaker/'.str_repeat('A', 12))->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/pengumuman')->assertOk()->assertSee('Player speaker');
        $role = AccessRole::where('name', 'Pegawai')->firstOrFail();
        $this->actingAs(User::factory()->create(['access_role_id' => $role->id]))
            ->get('/pengumuman')->assertForbidden();
    }

    public function test_morning_audio_plays_once_and_afternoon_uses_closing_time(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-30 10:00:01', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create());
        $code = DB::table('announcer_settings')->value('player_code');
        $wav = 'RIFF'.pack('V', 36).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 0);
        $this->post('/pengumuman/jadwal', [
            'title' => 'Indonesia Raya', 'audio' => UploadedFile::fake()->createWithContent('lagu.wav', $wav),
            'time_type' => 'fixed', 'play_time' => '10:00', 'weekdays' => [1],
            'sort_order' => 10, 'active' => 1,
        ])->assertRedirect('/pengumuman');
        $schedule = DB::table('announcer_schedules')->first();
        $this->assertNotNull($schedule);
        $this->get('/pengumuman')->assertOk()->assertSee('Indonesia Raya');
        $this->assertTrue(AnnouncerSchedule::due($schedule, ['1'=>'16:00'], CarbonImmutable::now('Asia/Jakarta')));
        $event = $this->postJson('/api/announcer/'.$code.'/next')->assertOk()->json('event');
        $this->assertSame('audio', $event['kind']);
        $this->get($event['audio_url'])->assertOk()->assertHeader('Content-Type', 'audio/x-wav');
        $this->postJson('/api/announcer/'.$code.'/next')->assertOk()->assertJsonPath('event', null);
        $this->postJson('/api/announcer/'.$code.'/complete/'.$event['play_id'], ['success'=>true])->assertNoContent();
        $this->assertDatabaseHas('announcer_plays', ['status'=>'played','event_key'=>'routine:'.$schedule->id.':2026-11-30']);
        $afternoon = (object) [
            'active'=>true,'weekdays'=>'[1,2,3,4,5]','starts_on'=>null,'ends_on'=>null,
            'time_type'=>'before_close','minutes_before_close'=>15,'play_time'=>null,
        ];
        $this->assertSame('15:45', AnnouncerSchedule::slot($afternoon, ['1'=>'16:00'], CarbonImmutable::now('Asia/Jakarta'))->format('H:i'));
    }

    public function test_scheduled_blast_uses_queue_and_records_result(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-27 09:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create());
        $code = DB::table('announcer_settings')->value('player_code');
        $this->post('/pengumuman/blast', [
            'title'=>'Pengumuman darurat','text'=>'Mohon berkumpul di ruang rapat.',
            'scheduled_at'=>'2026-11-27T09:05','bell'=>1,
        ])->assertRedirect('/pengumuman');
        $this->get('/pengumuman')->assertOk()->assertSee('Pengumuman darurat');
        $this->postJson('/api/announcer/'.$code.'/next')->assertJsonPath('event', null);
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-27 09:05:01', 'Asia/Jakarta'));
        $event = $this->postJson('/api/announcer/'.$code.'/next')->assertOk()->json('event');
        $this->assertSame('speech', $event['kind']);
        $this->assertTrue($event['bell']);
        $this->assertSame('Mohon berkumpul di ruang rapat.', $event['text']);
        $this->postJson('/api/announcer/'.$code.'/complete/'.$event['play_id'], [
            'success'=>false,'error'=>'Suara tidak tersedia',
        ])->assertNoContent();
        $this->assertDatabaseHas('announcer_blasts', ['status'=>'failed']);
        $blast = DB::table('announcer_blasts')->first();
        $this->post('/pengumuman/blast/'.$blast->id.'/ulang')->assertRedirect();
        $retry = $this->postJson('/api/announcer/'.$code.'/next')->assertOk()->json('event');
        $this->assertSame('speech', $retry['kind']);
        $this->postJson('/api/announcer/'.$code.'/complete/'.$retry['play_id'], ['success'=>true])->assertNoContent();
        $this->assertDatabaseHas('announcer_blasts', ['status'=>'played']);
    }
}
