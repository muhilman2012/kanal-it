<?php

namespace App\Http\Controllers;

use App\Services\AnnouncerSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnnouncerPlayerController extends Controller
{
    private function settings(string $code): object
    {
        return DB::table('announcer_settings')->where('player_code', strtoupper($code))->firstOrFail();
    }

    public function show(string $code)
    {
        $this->settings($code);
        return response()->view('announcer.player', ['code' => strtoupper($code)])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; media-src 'self'; connect-src 'self'; frame-ancestors 'none'");
    }

    public function next(string $code)
    {
        $settings = $this->settings($code);
        $now = CarbonImmutable::now('Asia/Jakarta');
        if (!$settings->last_seen_at || CarbonImmutable::parse($settings->last_seen_at)->lt(now()->subSeconds(30))) {
            DB::table('announcer_settings')->where('id', 1)->update(['last_seen_at' => now()]);
        }
        $event = DB::transaction(function () use ($settings, $now, $code) {
            DB::table('announcer_blasts')->where('status', 'queued')
                ->where('scheduled_at', '<', $now->subMinutes(30)->format('Y-m-d H:i:s'))
                ->update(['status' => 'expired', 'updated_at' => now()]);
            $blast = DB::table('announcer_blasts')->where('status', 'queued')
                ->where('scheduled_at', '<=', $now->format('Y-m-d H:i:s'))
                ->orderBy('scheduled_at')->orderBy('id')->first();
            if ($blast) {
                $key = 'blast:'.$blast->id;
                if (DB::table('announcer_plays')->insertOrIgnore([
                    'event_key' => $key, 'title' => $blast->title, 'kind' => 'blast',
                    'claimed_at' => now(), 'status' => 'playing', 'created_at' => now(), 'updated_at' => now(),
                ])) {
                    DB::table('announcer_blasts')->where('id', $blast->id)->update(['status' => 'playing', 'updated_at' => now()]);
                    $play = DB::table('announcer_plays')->where('event_key', $key)->first();
                    return ['play_id' => $play->id, 'kind' => 'speech', 'title' => $blast->title,
                        'text' => $blast->text, 'bell' => (bool) $blast->bell];
                }
            }
            $closing = json_decode($settings->closing_times, true) ?: [];
            foreach (DB::table('announcer_schedules')->where('active', true)->orderBy('sort_order')->orderBy('id')->get() as $schedule) {
                if (!AnnouncerSchedule::due($schedule, $closing, $now)) continue;
                $key = 'routine:'.$schedule->id.':'.$now->toDateString();
                if (DB::table('announcer_plays')->insertOrIgnore([
                    'event_key' => $key, 'title' => $schedule->title, 'kind' => 'routine',
                    'claimed_at' => now(), 'status' => 'playing', 'created_at' => now(), 'updated_at' => now(),
                ])) {
                    $play = DB::table('announcer_plays')->where('event_key', $key)->first();
                    return ['play_id' => $play->id, 'kind' => 'audio', 'title' => $schedule->title,
                        'audio_url' => '/speaker/'.strtoupper($code).'/audio/'.$schedule->id,
                        'bell' => (bool) $schedule->bell];
                }
            }
            return null;
        });
        return response()->json(['event' => $event, 'server_time' => $now->toIso8601String()])
            ->header('Cache-Control', 'no-store');
    }

    public function complete(string $code, int $play)
    {
        $this->settings($code);
        $data = request()->validate(['success' => 'required|boolean', 'error' => 'nullable|string|max:300']);
        $row = DB::table('announcer_plays')->find($play);
        abort_unless($row, 404);
        if ($row->status !== 'playing') return response()->noContent();
        $success = (bool) $data['success'];
        DB::table('announcer_plays')->where('id', $play)->where('status', 'playing')->update([
            'status' => $success ? 'played' : 'failed',
            'error' => $success ? null : ($data['error'] ?? 'Pemutaran gagal'),
            'finished_at' => now(), 'updated_at' => now(),
        ]);
        if ($row->kind === 'blast' && preg_match('/^blast:(\d+)$/', $row->event_key, $matches)) {
            DB::table('announcer_blasts')->where('id', (int) $matches[1])->update([
                'status' => $success ? 'played' : 'failed',
                'error' => $success ? null : ($data['error'] ?? 'Pemutaran gagal'),
                'played_at' => $success ? now() : null, 'updated_at' => now(),
            ]);
        }
        return response()->noContent();
    }

    public function audio(string $code, int $schedule)
    {
        $this->settings($code);
        $row = DB::table('announcer_schedules')->find($schedule);
        abort_unless($row && $row->audio_path && $row->audio_path === basename($row->audio_path), 404);
        $path = config('announcer.media_path').DIRECTORY_SEPARATOR.$row->audio_path;
        abort_unless(is_file($path), 404);
        $type = mime_content_type($path);
        abort_unless(str_starts_with($type, 'audio/'), 404);
        return response()->file($path, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
