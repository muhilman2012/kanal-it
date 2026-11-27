<?php

namespace App\Services;

use App\Models\LobbyItem;
use Carbon\CarbonImmutable;

class LobbySchedule
{
    public static function active(LobbyItem $item, CarbonImmutable $now): bool
    {
        if (!$item->active) return false;
        if ($item->kind === 'webshot' && (!$item->file_path || !is_file(config('lobby.media_path').DIRECTORY_SEPARATOR.$item->file_path))) return false;
        if ($item->mode === 'always') return true;
        if ($item->mode !== 'scheduled') return false;

        $time = $now->format('H:i');
        $start = $item->starts_at;
        $end = $item->ends_at;
        $overnight = $start && $end && $start > $end;
        if ($start && $end) {
            if ($overnight ? ($time < $start && $time >= $end) : ($time < $start || $time >= $end)) return false;
        } elseif ($start && $time < $start) {
            return false;
        } elseif ($end && $time >= $end) {
            return false;
        }

        $scheduleDay = $overnight && $time < $end ? $now->subDay() : $now;
        if ($item->starts_on && $scheduleDay->toDateString() < $item->starts_on->toDateString()) return false;
        if ($item->ends_on && $scheduleDay->toDateString() > $item->ends_on->toDateString()) return false;
        if ($item->weekdays && !in_array($scheduleDay->isoWeekday(), $item->weekdays, false)) return false;
        return true;
    }

    public static function playlist(iterable $items, CarbonImmutable $now): array
    {
        $active = collect($items)->filter(fn (LobbyItem $item) => self::active($item, $now));
        $exclusive = $active->filter(fn (LobbyItem $item) => $item->mode === 'scheduled' && $item->exclusive);
        return ($exclusive->isNotEmpty() ? $exclusive : $active)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values()->all();
    }
}
