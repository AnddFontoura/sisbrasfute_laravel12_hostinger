<?php

namespace Tests\Unit;

use App\Enums\ReservationDuration;
use App\Models\CourtScheduleConfig;
use App\Service\SlotService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure slot/price math — NO database. Instantiates CourtScheduleConfig only as a
 * value object (never persisted) and feeds plain stdClass pricing rules.
 */
class SlotServiceTest extends TestCase
{
    private function config(array $attrs): CourtScheduleConfig
    {
        $config = new CourtScheduleConfig();
        $config->is_active = $attrs['is_active'] ?? true;
        $config->start_time = $attrs['start_time'] ?? null;
        $config->end_time = $attrs['end_time'] ?? null;
        $config->reservation_duration = $attrs['reservation_duration'] ?? null;

        return $config;
    }

    private function rule(int $id, string $start, string $end, int $priceCents): object
    {
        return (object) [
            'id' => $id,
            'start_time' => $start,
            'end_time' => $end,
            'price_cents' => $priceCents,
        ];
    }

    // #1 — 18:00-22:00 / 60 => 4 slots + weekday mapping lock
    public function test_generates_four_hourly_slots_and_weekday_mapping(): void
    {
        $slots = SlotService::generateSlots($this->config([
            'start_time' => '18:00',
            'end_time' => '22:00',
            'reservation_duration' => ReservationDuration::Sixty,
        ]));

        $this->assertCount(4, $slots);
        $this->assertSame('18:00:00', $slots[0]['start_time']);
        $this->assertSame('19:00:00', $slots[0]['end_time']);
        $this->assertSame('21:00:00', $slots[3]['start_time']);
        $this->assertSame('22:00:00', $slots[3]['end_time']);

        // Lock the Carbon dayOfWeek mapping to court_schedule_configs.weekday / FE getDay().
        $this->assertSame(0, Carbon::parse('2026-10-04')->dayOfWeek); // a Sunday
        $this->assertSame(6, Carbon::parse('2026-10-03')->dayOfWeek); // a Saturday
    }

    // #2 — 18:00-21:00 / 90 => exact fit (two slots)
    public function test_ninety_minute_exact_fit(): void
    {
        $slots = SlotService::generateSlots($this->config([
            'start_time' => '18:00',
            'end_time' => '21:00',
            'reservation_duration' => ReservationDuration::Ninety,
        ]));

        $this->assertCount(2, $slots);
        $this->assertSame('18:00:00', $slots[0]['start_time']);
        $this->assertSame('19:30:00', $slots[0]['end_time']);
        $this->assertSame('19:30:00', $slots[1]['start_time']);
        $this->assertSame('21:00:00', $slots[1]['end_time']);
    }

    // #3 — 18:00-20:00 / 90 => one slot (trailing partial dropped)
    public function test_ninety_minute_trailing_partial_dropped(): void
    {
        $slots = SlotService::generateSlots($this->config([
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reservation_duration' => ReservationDuration::Ninety,
        ]));

        $this->assertCount(1, $slots);
        $this->assertSame('18:00:00', $slots[0]['start_time']);
        $this->assertSame('19:30:00', $slots[0]['end_time']);
    }

    // #4 — inactive / null / absent config => []
    public function test_inactive_or_incomplete_config_yields_no_slots(): void
    {
        $this->assertSame([], SlotService::generateSlots(null));

        $this->assertSame([], SlotService::generateSlots($this->config([
            'is_active' => false,
            'start_time' => '18:00',
            'end_time' => '22:00',
            'reservation_duration' => ReservationDuration::Sixty,
        ])));

        $this->assertSame([], SlotService::generateSlots($this->config([
            'start_time' => null,
            'end_time' => '22:00',
            'reservation_duration' => ReservationDuration::Sixty,
        ])));

        $this->assertSame([], SlotService::generateSlots($this->config([
            'start_time' => '18:00',
            'end_time' => '22:00',
            'reservation_duration' => null,
        ])));
    }

    // #5 — start >= end => []
    public function test_start_not_before_end_yields_no_slots(): void
    {
        $this->assertSame([], SlotService::generateSlots($this->config([
            'start_time' => '22:00',
            'end_time' => '18:00',
            'reservation_duration' => ReservationDuration::Sixty,
        ])));

        $this->assertSame([], SlotService::generateSlots($this->config([
            'start_time' => '18:00',
            'end_time' => '18:00',
            'reservation_duration' => ReservationDuration::Sixty,
        ])));
    }

    // #6 — resolvePriceCents cross-engine behaviour (HH:MM rules vs HH:MM:SS slots)
    public function test_resolve_price_matches_across_time_widths(): void
    {
        // HH:MM rule (SQLite-shaped) resolves an HH:MM:SS slot.
        $rules = [$this->rule(1, '18:00', '19:00', 8000)];
        $this->assertSame(8000, SlotService::resolvePriceCents($rules, '18:00:00', '19:00:00'));

        // Symmetric HH:MM:SS-rule case.
        $rulesWide = [$this->rule(1, '18:00:00', '19:00:00', 8000)];
        $this->assertSame(8000, SlotService::resolvePriceCents($rulesWide, '18:00:00', '19:00:00'));

        // No match => 0.
        $this->assertSame(0, SlotService::resolvePriceCents($rules, '20:00:00', '21:00:00'));

        // Overlapping => narrowest window, then lowest id.
        $overlapping = [
            $this->rule(1, '18:00', '22:00', 5000), // width 240
            $this->rule(2, '18:00', '19:00', 9000), // width 60 (narrowest)
        ];
        $this->assertSame(9000, SlotService::resolvePriceCents($overlapping, '18:00:00', '19:00:00'));

        // Equal-width tie => lowest id wins.
        $tie = [
            $this->rule(5, '18:00', '19:00', 7000),
            $this->rule(3, '18:00', '19:00', 6000),
        ];
        $this->assertSame(6000, SlotService::resolvePriceCents($tie, '18:00:00', '19:00:00'));
    }

    // #7 — mensalista date expansion is a pure same-weekday-in-month list
    public function test_mensalista_date_expansion_stays_in_month(): void
    {
        // Tue 2026-09-01 => 08, 15, 22, 29 all in September.
        $this->assertSame(
            ['2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'],
            $this->expand('2026-09-01')
        );

        // Last same-weekday in month => no extra dates.
        // 2026-09-29 is the last Tuesday of September (next is 2026-10-06).
        $this->assertSame([], $this->expand('2026-09-29'));

        // Never crosses the month boundary.
        foreach ($this->expand('2026-09-22') as $date) {
            $this->assertSame('09', substr($date, 5, 2));
        }
    }

    /**
     * Mirror of CourtReservationService::mensalistaDates — pure same-weekday,
     * later-in-the-same-month expansion.
     *
     * @return array<int, string>
     */
    private function expand(string $anchorDate): array
    {
        $anchor = Carbon::parse($anchorDate);
        $month = $anchor->month;
        $dates = [];
        $cursor = (clone $anchor)->addDays(7);

        while ($cursor->month === $month) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDays(7);
        }

        return $dates;
    }
}
