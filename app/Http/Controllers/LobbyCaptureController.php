<?php

namespace App\Http\Controllers;

use App\Models\LobbyItem;
use Illuminate\Http\Request;

class LobbyCaptureController extends Controller
{
    private function authorizeWorker(Request $request): void
    {
        $expected = config('lobby.capture_token');
        abort_unless(is_string($expected) && strlen($expected) >= 32
            && hash_equals($expected, (string) $request->bearerToken()), 403);
    }

    public function due(Request $request)
    {
        $this->authorizeWorker($request);
        $item = LobbyItem::query()->where('kind', 'webshot')->where('active', true)
            ->whereHas('screen', fn ($query) => $query->where('active', true))
            ->where(fn ($query) => $query->whereNull('snapshot_next_at')->orWhere('snapshot_next_at', '<=', now()))
            ->orderBy('snapshot_next_at')->first();
        if (!$item) return response()->json(['item' => null]);
        // Reserve briefly so a second worker cannot start the same capture.
        $item->update(['snapshot_next_at' => now()->addMinutes(3)]);
        return response()->json(['item' => ['id' => $item->id, 'url' => $item->website_url]]);
    }

    public function store(Request $request, LobbyItem $item)
    {
        $this->authorizeWorker($request);
        abort_unless($item->kind === 'webshot', 404);
        $request->validate(['image' => 'required|file|mimetypes:image/png|max:10240']);
        $directory = config('lobby.media_path');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        $name = bin2hex(random_bytes(18)).'.png';
        $request->file('image')->move($directory, $name);
        $old = $item->file_path;
        try {
            $item->update([
                'file_path' => $name,
                'file_name' => 'Tangkapan layar',
                'snapshot_at' => now(),
                'snapshot_next_at' => now()->addMinutes($item->snapshot_interval_minutes ?: 15),
                'snapshot_error' => null,
            ]);
        } catch (\Throwable $error) {
            @unlink($directory.DIRECTORY_SEPARATOR.$name);
            throw $error;
        }
        if ($old && $old === basename($old)) @unlink($directory.DIRECTORY_SEPARATOR.$old);
        return response()->json(['ok' => true]);
    }

    public function failed(Request $request, LobbyItem $item)
    {
        $this->authorizeWorker($request);
        abort_unless($item->kind === 'webshot', 404);
        $data = $request->validate(['error' => 'required|string|max:300']);
        $item->update([
            'snapshot_error' => $data['error'],
            'snapshot_next_at' => now()->addMinutes(5),
        ]);
        return response()->json(['ok' => true]);
    }
}
