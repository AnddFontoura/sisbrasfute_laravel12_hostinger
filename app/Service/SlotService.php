<?php

namespace App\Service;

use App\Models\CourtScheduleConfig;

/**
 * Pure, side-effect-free slot/price math (NO Eloquent, NO DB). Unit-testable by
 * feeding a config / rules and asserting the output. Times are handled as
 * integer minutes-since-midnight so comparisons are identical on MySQL and
 * SQLite (see design §2/§3).
 */
class SlotService
{
    /**
     * Generate the sequential, back-to-back free-slot windows for a weekday's
     * schedule config. Returns an array of ['start_time' => 'HH:MM:SS',
     * 'end_time' => 'HH:MM:SS']. An inactive / incomplete / misconfigured
     * window yields [].
     */
    public static function generateSlots(?CourtScheduleConfig $config): array
    {
        if (
            $config === null
            || $config->is_active === false
            || $config->start_time === null
            || $config->end_time === null
            || $config->reservation_duration === null
        ) {
            return [];
        }

        $duration = (int) ($config->reservation_duration instanceof \BackedEnum
            ? $config->reservation_duration->value
            : $config->reservation_duration);

        if ($duration <= 0) {
            return [];
        }

        $start = self::toMinutes($config->start_time);
        $end = self::toMinutes($config->end_time);

        $slots = [];
        $cursor = $start;

        // Last slot must END on/before the window end. start >= end or a window
        // smaller than one duration naturally yields [].
        while (($cursor + $duration) <= $end) {
            $slots[] = [
                'start_time' => self::fromMinutes($cursor),
                'end_time' => self::fromMinutes($cursor + $duration),
            ];
            $cursor += $duration;
        }

        return $slots;
    }

    /**
     * Resolve the price (in centavos) for a slot from the court's weekday
     * pricing rules. Matching and tie-break are done purely in PHP on integer
     * minutes — never on HH:MM:SS string equality (see design §3). No match
     * resolves to 0 (the operator enters the price manually).
     */
    public static function resolvePriceCents(iterable $rules, string $slotStart, string $slotEnd): int
    {
        $toMin = static fn (string $t): int => self::toMinutes($t);

        $slotStartMin = $toMin($slotStart);
        $slotEndMin = $toMin($slotEnd);

        $matching = [];
        foreach ($rules as $rule) {
            if ($toMin($rule->start_time) <= $slotStartMin && $toMin($rule->end_time) >= $slotEndMin) {
                $matching[] = $rule;
            }
        }

        if ($matching === []) {
            return 0;
        }

        // Tie-break: narrowest window first, then lowest id — all in PHP.
        usort($matching, static function ($a, $b) use ($toMin) {
            $widthA = $toMin($a->end_time) - $toMin($a->start_time);
            $widthB = $toMin($b->end_time) - $toMin($b->start_time);

            return ($widthA <=> $widthB) ?: ($a->id <=> $b->id);
        });

        return (int) $matching[0]->price_cents;
    }

    /**
     * Minutes-since-midnight from an HH:MM or HH:MM:SS string. substr(,0,5)
     * strips any seconds suffix so the parse is engine-width-independent.
     */
    private static function toMinutes(string $time): int
    {
        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }

    private static function fromMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%02d:%02d:00', $h, $m);
    }
}
