<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class AnnouncerSchedule
{
    public static function slot(object $schedule, array $closingTimes, CarbonImmutable $day): ?CarbonImmutable
    {
        $weekday = (string) $day->isoWeekday();
        $weekdays = json_decode($schedule->weekdays ?: '[]', true) ?: [];
        if (!in_array((int) $weekday, array_map('intval', $weekdays), true)) return null;
        if ($schedule->starts_on && $day->toDateString() < $schedule->starts_on) return null;
        if ($schedule->ends_on && $day->toDateString() > $schedule->ends_on) return null;

        if ($schedule->time_type === 'before_close') {
            $closing = $closingTimes[$weekday] ?? null;
            if (!$closing) return null;
            return CarbonImmutable::parse($day->toDateString().' '.$closing, 'Asia/Jakarta')
                ->subMinutes((int) ($schedule->minutes_before_close ?: 15));
        }
        if ($schedule->time_type === 'fixed' && $schedule->play_time) {
            return CarbonImmutable::parse($day->toDateString().' '.$schedule->play_time, 'Asia/Jakarta');
        }
        return null;
    }

    public static function due(object $schedule, array $closingTimes, CarbonImmutable $now): bool
    {
        if (!$schedule->active) return false;
        $slot = self::slot($schedule, $closingTimes, $now->startOfDay());
        return $slot && $now->greaterThanOrEqualTo($slot) && $now->lessThan($slot->addMinutes(10));
    }
}
