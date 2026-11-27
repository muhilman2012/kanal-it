<?php

namespace App\Http\Controllers;

use App\Models\LobbyScreen;
use App\Services\LobbySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class LobbyPlayerController extends Controller
{
    private function screen(string $token): LobbyScreen
    {
        return LobbyScreen::where('token_hash', hash('sha256', $token))
            ->where('active', true)->firstOrFail();
    }

    public function show(string $token)
    {
        $screen = $this->screen($token);
        return response()->view('lobby.player', compact('screen', 'token'))
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; frame-src https:; connect-src 'self'; frame-ancestors 'none'");
    }

    public function playlist(string $token)
    {
        $screen = $this->screen($token);
        $now = CarbonImmutable::now('Asia/Jakarta');
        if (!$screen->last_seen_at || $screen->last_seen_at->lt(now()->subSeconds(45))) {
            $screen->update(['last_seen_at' => now()]);
        }
        $items = LobbySchedule::playlist($screen->items()->orderBy('sort_order')->orderBy('id')->get(), $now);
        $base = '/layar-lobby/'.$token.'/media/';
        $entries = array_map(fn ($item) => [
            'id' => $item->id,
            'kind' => $item->kind === 'webshot' ? 'image' : $item->kind,
            'url' => $item->kind === 'website' ? $item->website_url : $base.$item->id.($item->kind === 'webshot' ? '?v='.$item->snapshot_at?->timestamp : ''),
            'fit' => $item->fit,
            'duration' => $item->duration_seconds,
        ], $items);
        return response()->json([
            'screen' => $screen->name,
            'time' => $now->toIso8601String(),
            'version' => sha1(json_encode($entries)),
            'items' => $entries,
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function media(Request $request, string $token, int $item)
    {
        $screen = $this->screen($token);
        $row = $screen->items()->where('id', $item)->where('active', true)->firstOrFail();
        abort_unless(in_array($row->kind, ['image', 'video', 'webshot'], true) && is_string($row->file_path)
            && $row->file_path === basename($row->file_path), 404);
        $path = config('lobby.media_path').DIRECTORY_SEPARATOR.$row->file_path;
        abort_unless(is_file($path), 404);
        $type = mime_content_type($path);
        abort_unless($row->kind === 'webshot' ? $type === 'image/png' : str_starts_with($type, $row->kind.'/'), 404);
        return response()->file($path, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
