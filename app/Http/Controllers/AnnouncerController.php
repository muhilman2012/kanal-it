<?php

namespace App\Http\Controllers;

use App\Services\AnnouncerSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnnouncerController extends Controller
{
    public function index()
    {
        $settings = DB::table('announcer_settings')->find(1);
        $closingTimes = json_decode($settings->closing_times, true) ?: [];
        $schedules = DB::table('announcer_schedules')->orderBy('sort_order')->orderBy('id')->get();
        $blasts = DB::table('announcer_blasts')->orderByDesc('id')->limit(30)->get();
        $plays = DB::table('announcer_plays')->orderByDesc('id')->limit(20)->get();
        $now = CarbonImmutable::now('Asia/Jakarta');
        $upcoming = $schedules->filter(fn ($schedule) => $schedule->active)
            ->map(fn ($schedule) => [
                'title' => $schedule->title,
                'slot' => AnnouncerSchedule::slot($schedule, $closingTimes, $now->startOfDay()),
            ])->filter(fn ($row) => $row['slot'])->sortBy('slot')->values();
        return view('announcer.index', compact('settings', 'closingTimes', 'schedules', 'blasts', 'plays', 'upcoming', 'now'));
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'closing_times' => 'required|array',
            'closing_times.1' => 'required|date_format:H:i',
            'closing_times.2' => 'required|date_format:H:i',
            'closing_times.3' => 'required|date_format:H:i',
            'closing_times.4' => 'required|date_format:H:i',
            'closing_times.5' => 'required|date_format:H:i',
        ]);
        DB::table('announcer_settings')->where('id', 1)->update([
            'closing_times' => json_encode($data['closing_times'], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
        return back()->with('status', 'Jam pulang diperbarui.');
    }

    public function rotate()
    {
        DB::table('announcer_settings')->where('id', 1)->update([
            'player_code' => strtoupper(bin2hex(random_bytes(6))),
            'updated_at' => now(),
        ]);
        return back()->with('status', 'Alamat speaker diganti. Perbarui URL pada perangkat.');
    }

    public function saveSchedule(Request $request, ?int $id = null)
    {
        $existing = $id ? DB::table('announcer_schedules')->find($id) : null;
        if ($id) abort_unless($existing, 404);
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'audio' => [
                $existing ? 'nullable' : 'required', 'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,audio/aac,audio/webm',
                'max:102400',
            ],
            'time_type' => ['required', Rule::in(['fixed', 'before_close'])],
            'play_time' => 'nullable|date_format:H:i',
            'minutes_before_close' => 'nullable|integer|between:1,120',
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'integer|between:1,7',
            'starts_on' => 'nullable|date_format:Y-m-d',
            'ends_on' => 'nullable|date_format:Y-m-d|after_or_equal:starts_on',
            'sort_order' => 'nullable|integer|between:0,9999',
        ]);
        if ($data['time_type'] === 'fixed' && empty($data['play_time']))
            return back()->withErrors(['play_time' => 'Isi jam tayang.'])->withInput();
        $newFile = null;
        if ($audio = $request->file('audio')) {
            $newFile = bin2hex(random_bytes(18)).'.'.$audio->guessExtension();
            $audio->move($this->mediaDirectory(), $newFile);
        }
        $values = [
            'title' => $data['title'],
            'time_type' => $data['time_type'],
            'play_time' => $data['time_type'] === 'fixed' ? $data['play_time'] : null,
            'minutes_before_close' => $data['time_type'] === 'before_close' ? ($data['minutes_before_close'] ?? 15) : null,
            'weekdays' => json_encode(array_values(array_unique(array_map('intval', $data['weekdays']))), JSON_THROW_ON_ERROR),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'bell' => $request->boolean('bell'),
            'active' => $request->boolean('active'),
            'updated_at' => now(),
        ];
        if ($newFile) {
            $values['audio_path'] = $newFile;
            $values['audio_name'] = mb_substr($audio->getClientOriginalName(), 0, 240);
        }
        try {
            if ($id) DB::table('announcer_schedules')->where('id', $id)->update($values);
            else DB::table('announcer_schedules')->insert($values + ['created_at' => now()]);
        } catch (\Throwable $error) {
            if ($newFile) $this->removeFile($newFile);
            throw $error;
        }
        if ($newFile && $existing) $this->removeFile($existing->audio_path);
        return redirect('/pengumuman')->with('status', 'Jadwal audio tersimpan.');
    }

    public function deleteSchedule(int $id)
    {
        $schedule = DB::table('announcer_schedules')->find($id);
        abort_unless($schedule, 404);
        DB::table('announcer_schedules')->where('id', $id)->delete();
        $this->removeFile($schedule->audio_path);
        return back()->with('status', 'Jadwal audio dihapus.');
    }

    public function blast(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'text' => 'required|string|max:2000',
            'scheduled_at' => 'nullable|date_format:Y-m-d\TH:i',
        ]);
        $scheduled = isset($data['scheduled_at'])
            ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['scheduled_at'], 'Asia/Jakarta')
            : CarbonImmutable::now('Asia/Jakarta');
        if ($scheduled->lt(CarbonImmutable::now('Asia/Jakarta')->subMinute()))
            return back()->withErrors(['scheduled_at' => 'Waktu blast tidak boleh di masa lalu.'])->withInput();
        DB::table('announcer_blasts')->insert([
            'title' => $data['title'],
            'text' => $data['text'],
            'scheduled_at' => $scheduled->format('Y-m-d H:i:s'),
            'bell' => $request->boolean('bell'),
            'status' => 'queued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return redirect('/pengumuman')->with('status', 'Blast TTS dijadwalkan.');
    }

    public function cancelBlast(int $id)
    {
        abort_unless(DB::table('announcer_blasts')->where('id', $id)->where('status', 'queued')
            ->update(['status' => 'cancelled', 'updated_at' => now()]), 409);
        return back()->with('status', 'Blast dibatalkan.');
    }

    public function retryBlast(int $id)
    {
        DB::transaction(function () use ($id) {
            abort_unless(DB::table('announcer_blasts')->where('id', $id)->whereIn('status', ['failed', 'playing', 'expired'])
                ->update(['status' => 'queued', 'scheduled_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
                    'error' => null, 'updated_at' => now()]), 409);
            DB::table('announcer_plays')->where('event_key', 'blast:'.$id)->delete();
        });
        return back()->with('status', 'Blast dimasukkan ulang ke antrean.');
    }

    private function mediaDirectory(): string
    {
        $path = config('announcer.media_path');
        if (!is_dir($path)) mkdir($path, 0700, true);
        return $path;
    }

    private function removeFile(?string $name): void
    {
        if (!$name || $name !== basename($name)) return;
        $path = config('announcer.media_path').DIRECTORY_SEPARATOR.$name;
        if (is_file($path)) unlink($path);
    }
}
