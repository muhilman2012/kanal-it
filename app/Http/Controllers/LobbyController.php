<?php

namespace App\Http\Controllers;

use App\Models\LobbyItem;
use App\Models\LobbyScreen;
use App\Services\LobbySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LobbyController extends Controller
{
    public function index(Request $request)
    {
        $screens = LobbyScreen::withCount('items')->orderBy('name')->get();
        $selected = $request->filled('screen')
            ? $screens->firstWhere('admin_code', $request->query('screen'))
            : $screens->first();
        if ($request->filled('screen') && !$selected) return redirect('/lobby');
        $items = $selected?->items()->orderBy('sort_order')->orderBy('id')->get() ?? collect();
        $now = CarbonImmutable::now('Asia/Jakarta');
        $playing = collect(LobbySchedule::playlist($items, $now))->pluck('id')->all();
        return view('lobby.index', compact('screens', 'selected', 'items', 'now', 'playing'));
    }

    public function saveScreen(Request $request, ?LobbyScreen $screen = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'location' => 'nullable|string|max:120',
        ]);
        if (!$screen) {
            $token = bin2hex(random_bytes(24));
            $screen = new LobbyScreen([
                'token_hash' => hash('sha256', $token),
                'token_encrypted' => $token,
                'short_code' => LobbyScreen::newShortCode(),
                'admin_code' => LobbyScreen::newAdminCode(),
            ]);
        }
        $screen->fill($data + ['active' => $request->boolean('active')])->save();
        return redirect('/lobby?screen='.$screen->admin_code)->with('status', 'Layar tersimpan.');
    }

    public function rotateToken(LobbyScreen $screen)
    {
        $token = bin2hex(random_bytes(24));
        $screen->update([
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => $token,
            'short_code' => LobbyScreen::newShortCode(),
        ]);
        return back()->with('status', 'Alamat layar diperbarui. Buka URL baru pada perangkat TV.');
    }

    public function deleteScreen(LobbyScreen $screen)
    {
        $files = $screen->items()->whereNotNull('file_path')->pluck('file_path');
        $screen->delete();
        foreach ($files as $file) $this->removeFile($file);
        return redirect('/lobby')->with('status', 'Layar dan semua tayangannya dihapus.');
    }

    public function saveItem(Request $request, LobbyScreen $screen, ?LobbyItem $item = null)
    {
        if ($item) abort_unless($item->lobby_screen_id === $screen->id, 404);
        $kind = $request->input('kind');
        $needsFile = in_array($kind, ['image', 'video'], true)
            && (!$item || $item->kind !== $kind || !$item->file_path);
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'kind' => ['required', Rule::in(['image', 'video', 'website', 'webshot'])],
            'file' => [
                $needsFile ? 'required' : (in_array($kind, ['image', 'video'], true) ? 'nullable' : 'prohibited'), 'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm',
                'max:307200',
            ],
            'website_url' => [in_array($kind, ['website', 'webshot'], true) ? 'required' : 'nullable', 'url:https', 'max:2048'],
            'snapshot_interval_minutes' => 'nullable|integer|between:5,1440',
            'fit' => ['required', Rule::in(['contain', 'cover'])],
            'duration_seconds' => 'required|integer|min:0|max:3600',
            'mode' => ['required', Rule::in(['always', 'scheduled'])],
            'weekdays' => 'nullable|array',
            'weekdays.*' => 'integer|between:1,7',
            'starts_on' => 'nullable|date_format:Y-m-d',
            'ends_on' => 'nullable|date_format:Y-m-d|after_or_equal:starts_on',
            'starts_at' => 'nullable|date_format:H:i|required_with:ends_at',
            'ends_at' => 'nullable|date_format:H:i|required_with:starts_at',
        ]);
        if ($kind !== 'video' && (int) $data['duration_seconds'] === 0) {
            return back()->withErrors(['duration_seconds' => 'Gambar dan laman web membutuhkan durasi minimal satu detik.'])->withInput();
        }
        if ($data['mode'] === 'scheduled' && isset($data['starts_at'], $data['ends_at'])
            && $data['starts_at'] === $data['ends_at']) {
            return back()->withErrors(['ends_at' => 'Jam mulai dan jam akhir harus berbeda. Kosongkan keduanya untuk tayang sepanjang hari.'])->withInput();
        }
        if ($kind === 'image' && $request->hasFile('file') && !str_starts_with($request->file('file')->getMimeType(), 'image/')) {
            return back()->withErrors(['file' => 'Pilih berkas gambar.'])->withInput();
        }
        if ($kind === 'video' && $request->hasFile('file') && !str_starts_with($request->file('file')->getMimeType(), 'video/')) {
            return back()->withErrors(['file' => 'Pilih berkas video.'])->withInput();
        }

        $oldFile = $item?->file_path;
        $newFile = null;
        if ($file = $request->file('file')) {
            $directory = $this->mediaDirectory();
            $newFile = bin2hex(random_bytes(18)).'.'.$file->guessExtension();
            $file->move($directory, $newFile);
        }
        $values = [
            'title' => $data['title'],
            'kind' => $data['kind'],
            'website_url' => in_array($kind, ['website', 'webshot'], true) ? $data['website_url'] : null,
            'snapshot_interval_minutes' => $kind === 'webshot' ? ($data['snapshot_interval_minutes'] ?? 15) : 15,
            'fit' => $data['fit'],
            'duration_seconds' => $data['duration_seconds'],
            'mode' => $data['mode'],
            'weekdays' => $data['mode'] === 'scheduled' ? array_values(array_unique(array_map('intval', $data['weekdays'] ?? []))) : [],
            'starts_on' => $data['mode'] === 'scheduled' ? ($data['starts_on'] ?? null) : null,
            'ends_on' => $data['mode'] === 'scheduled' ? ($data['ends_on'] ?? null) : null,
            'starts_at' => $data['mode'] === 'scheduled' ? ($data['starts_at'] ?? null) : null,
            'ends_at' => $data['mode'] === 'scheduled' ? ($data['ends_at'] ?? null) : null,
            'exclusive' => $data['mode'] === 'scheduled' && $request->boolean('exclusive'),
            'active' => $request->boolean('active'),
        ];
        if ($newFile) {
            $values['file_path'] = $newFile;
            $values['file_name'] = mb_substr($file->getClientOriginalName(), 0, 240);
        } elseif ($kind !== 'webshot' || $item?->kind !== 'webshot') {
            $values['file_path'] = null;
            $values['file_name'] = null;
        }
        if ($kind === 'webshot') {
            if ($item?->kind !== 'webshot' || $item->website_url !== $data['website_url']) {
                $values['snapshot_at'] = null;
                $values['snapshot_error'] = null;
                $values['snapshot_next_at'] = now();
                $values['file_path'] = null;
                $values['file_name'] = null;
            } elseif ((int) $item->snapshot_interval_minutes !== (int) ($data['snapshot_interval_minutes'] ?? 15)) {
                $values['snapshot_next_at'] = now();
            }
        } else {
            $values['snapshot_at'] = null;
            $values['snapshot_next_at'] = null;
            $values['snapshot_error'] = null;
        }
        $item ??= new LobbyItem([
            'lobby_screen_id' => $screen->id,
            'sort_order' => ($screen->items()->max('sort_order') ?? 0) + 10,
        ]);
        try {
            $item->fill($values)->save();
        } catch (\Throwable $error) {
            if ($newFile) $this->removeFile($newFile);
            throw $error;
        }
        if ($oldFile && $oldFile !== $item->file_path) $this->removeFile($oldFile);
        return redirect('/lobby?screen='.$screen->admin_code)->with('status', 'Tayangan tersimpan.');
    }

    public function moveItem(Request $request, LobbyScreen $screen, LobbyItem $item)
    {
        abort_unless($item->lobby_screen_id === $screen->id, 404);
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $ordered = $screen->items()->orderBy('sort_order')->orderBy('id')->get();
        $index = $ordered->search(fn ($row) => $row->id === $item->id);
        $neighborIndex = $index + ($data['direction'] === 'up' ? -1 : 1);
        $other = $ordered->get($neighborIndex);
        if (!$other) return back();
        $ordered[$neighborIndex] = $item;
        $ordered[$index] = $other;
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $row) {
                $row->update(['sort_order' => ($position + 1) * 10]);
            }
        });
        return back()->with('status', 'Urutan tayangan diperbarui.');
    }

    public function deleteItem(LobbyScreen $screen, LobbyItem $item)
    {
        abort_unless($item->lobby_screen_id === $screen->id, 404);
        $file = $item->file_path;
        $item->delete();
        if ($file) $this->removeFile($file);
        return back()->with('status', 'Tayangan dihapus.');
    }

    private function mediaDirectory(): string
    {
        $path = config('lobby.media_path');
        if (!is_dir($path)) mkdir($path, 0700, true);
        return $path;
    }

    private function removeFile(string $name): void
    {
        if ($name !== basename($name)) return;
        $path = config('lobby.media_path').DIRECTORY_SEPARATOR.$name;
        if (is_file($path)) unlink($path);
    }
}
