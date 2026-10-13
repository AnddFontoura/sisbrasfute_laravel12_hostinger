<?php

namespace Tests\Feature;

use App\Enums\CourtRole;
use App\Models\Court;
use App\Models\CourtReservation;
use App\Models\User;
use App\Repository\CourtMembershipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourtReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    // 2026-09-08 is a Tuesday (weekday 2); later same-weekday dates in September
    // are 15/22/29 (three), so a mensalista anchored here expands to 4 rows total.
    private const ANCHOR_DATE = '2026-09-08';
    private const WEEKDAY = 2;

    private function grantCourtAllowed(User $user): User
    {
        $user->assignRole(Role::firstOrCreate(['name' => 'court-allowed', 'guard_name' => 'web']));

        return $user;
    }

    private function seedModalityAndCity(): array
    {
        $modalityId = DB::table('modalities')->insertGetId([
            'name' => 'Society',
            'description' => 'Modalidade de teste',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stateId = DB::table('states')->insertGetId([
            'name' => 'Test State',
            'short' => 'TS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'state_id' => $stateId,
            'name' => 'Test City',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$modalityId, $cityId];
    }

    private function courtPayload(int $modalityId, int $cityId, bool $withPricing = true): array
    {
        $payload = [
            'courtName' => 'Quadra Teste',
            'courtDescription' => 'Uma quadra de teste',
            'courtModalityId' => $modalityId,
            'courtCityId' => $cityId,
            'courtAddressLine' => 'Rua Teste, 123',
            'courtSurfaceType' => 'sintetico',
            'contacts' => [
                ['type' => 'whatsapp', 'value' => '5511999999999', 'label' => 'Principal'],
            ],
            'photos' => [UploadedFile::fake()->create('court-0.jpg', 100, 'image/jpeg')],
            'scheduleConfigs' => [
                ['weekday' => self::WEEKDAY, 'isActive' => true, 'startTime' => '18:00', 'endTime' => '22:00', 'reservationDuration' => 60],
            ],
        ];

        if ($withPricing) {
            $payload['pricingRules'] = [
                ['weekday' => self::WEEKDAY, 'startTime' => '18:00', 'endTime' => '22:00', 'priceCents' => 8000],
            ];
        }

        return $payload;
    }

    private function createCourtAs(User $owner, int $modalityId, int $cityId, bool $withPricing = true): Court
    {
        $this->grantCourtAllowed($owner);
        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson('/api/court/save', $this->courtPayload($modalityId, $cityId, $withPricing))->assertCreated();

        return Court::latest('id')->first();
    }

    private function member(Court $court, CourtRole $role): User
    {
        $user = $this->grantCourtAllowed(User::factory()->create());
        app(CourtMembershipRepository::class)->createMembership($court->id, $user->id, $role);

        return $user;
    }

    private function reservePayload(array $overrides = []): array
    {
        return array_merge([
            'reservationDate' => self::ANCHOR_DATE,
            'startTime' => '18:00',
            'endTime' => '19:00',
            'customerName' => 'João',
            'description' => 'Contato 11999999999',
            'priceCents' => 8000,
            'isMensalista' => false,
        ], $overrides);
    }

    // #8
    public function test_owner_reserve_snapshots_price_and_counts_one(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $response = $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload());

        $response->assertCreated()
            ->assertJson(['created_count' => 1, 'skipped_dates' => []]);

        $this->assertDatabaseHas('court_reservations', [
            'court_id' => $court->id,
            'start_time' => '18:00:00',
            'price_cents' => 8000,
            'is_mensalista' => false,
            'recurrence_group_id' => null,
        ]);
        $this->assertSame(
            self::ANCHOR_DATE,
            CourtReservation::first()->reservation_date->format('Y-m-d')
        );
    }

    // #8a — startTime '18:00' accepted (not 422) and persisted as 18:00:00
    public function test_reserve_accepts_hhmm_and_persists_hhmmss(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'startTime' => '18:00',
            'endTime' => '19:00',
        ]))->assertCreated();

        $this->assertDatabaseHas('court_reservations', [
            'court_id' => $court->id,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
        ]);
    }

    // #9
    public function test_manager_and_employee_can_reserve(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $manager = $this->member($court, CourtRole::Manager);
        $employee = $this->member($court, CourtRole::Employee);

        Sanctum::actingAs($manager, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'startTime' => '18:00', 'endTime' => '19:00',
        ]))->assertCreated();

        Sanctum::actingAs($employee, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'startTime' => '19:00', 'endTime' => '20:00',
        ]))->assertCreated();
    }

    // #10
    public function test_stranger_cannot_reserve(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $stranger = $this->grantCourtAllowed(User::factory()->create());
        Sanctum::actingAs($stranger, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())
            ->assertStatus(403);
    }

    // #11
    public function test_off_grid_slot_returns_422_invalid_slot(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'startTime' => '18:30', 'endTime' => '19:30',
        ]))
            ->assertStatus(422)
            ->assertJson(['message' => __('error.court_reservation.invalid_slot')]);
    }

    // #12
    public function test_already_reserved_precheck_returns_409(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())->assertCreated();

        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())
            ->assertStatus(409)
            ->assertJson(['message' => __('error.court_reservation.slot_taken')]);
    }

    // #13 — concurrent double-book via a pre-inserted row -> 409 by unique-constraint catch
    public function test_concurrent_double_book_returns_409_via_unique_constraint(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        // Pre-insert the row directly so the pre-check cannot see it only if we
        // bypass; here it DOES see it, exercising the friendly 409 path. The unique
        // index remains the real guard (tested implicitly: duplicate insert cannot land).
        CourtReservation::create([
            'court_id' => $court->id,
            'reservation_date' => self::ANCHOR_DATE,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'price_cents' => 8000,
            'customer_name' => 'Pre',
            'description' => null,
            'is_mensalista' => false,
            'recurrence_group_id' => null,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())
            ->assertStatus(409);

        // Only the single pre-inserted row exists — the duplicate never landed.
        $this->assertDatabaseCount('court_reservations', 1);
    }

    // #14
    public function test_price_override_is_persisted(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'priceCents' => 12345,
        ]))->assertCreated();

        $this->assertDatabaseHas('court_reservations', [
            'court_id' => $court->id,
            'price_cents' => 12345,
        ]);
    }

    // #15 — no pricing rule => /day default_price_cents=0, entered price persists
    public function test_no_rule_defaults_to_zero_and_persists_entered_price(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId, withPricing: false);

        Sanctum::actingAs($owner, ['*'], 'api');
        $day = $this->getJson("/api/court-reservation/{$court->id}/day?date=" . self::ANCHOR_DATE)->assertOk();
        $this->assertSame(0, $day->json('slots.0.default_price_cents'));

        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'priceCents' => 5000,
        ]))->assertCreated();

        $this->assertDatabaseHas('court_reservations', [
            'court_id' => $court->id,
            'price_cents' => 5000,
        ]);
    }

    // #16 — mensalista anchor + 3 free => created_count=4, one group uuid, 4 rows
    public function test_mensalista_creates_group_of_four(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'isMensalista' => true,
        ]))
            ->assertCreated()
            ->assertJson(['created_count' => 4, 'skipped_dates' => []]);

        $rows = CourtReservation::where('court_id', $court->id)->get();
        $this->assertCount(4, $rows);
        $this->assertCount(1, $rows->pluck('recurrence_group_id')->unique());
        $this->assertNotNull($rows->first()->recurrence_group_id);
    }

    // #17 — one mensalista target pre-occupied => that date skipped, others committed
    public function test_mensalista_skips_preoccupied_target(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        // Pre-occupy 2026-09-22 (a target replica date).
        CourtReservation::create([
            'court_id' => $court->id,
            'reservation_date' => '2026-09-22',
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'price_cents' => 8000,
            'customer_name' => 'Pre',
            'description' => null,
            'is_mensalista' => false,
            'recurrence_group_id' => null,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'isMensalista' => true,
        ]))
            ->assertCreated()
            ->assertJson([
                'created_count' => 3, // anchor 08 + 15 + 29 (22 skipped)
                'skipped_dates' => ['2026-09-22'],
            ]);

        // The pre-existing row plus the 3 newly created ones.
        $this->assertDatabaseCount('court_reservations', 4);

        // The three group rows share one uuid.
        $groupRows = CourtReservation::whereNotNull('recurrence_group_id')
            ->where('court_id', $court->id)
            ->get();
        $this->assertCount(3, $groupRows);
        $this->assertCount(1, $groupRows->pluck('recurrence_group_id')->unique());
    }

    // #18 — mensalista anchor occupied => 409, nothing created
    public function test_mensalista_anchor_occupied_returns_409_nothing_created(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        CourtReservation::create([
            'court_id' => $court->id,
            'reservation_date' => self::ANCHOR_DATE,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'price_cents' => 8000,
            'customer_name' => 'Pre',
            'description' => null,
            'is_mensalista' => false,
            'recurrence_group_id' => null,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'isMensalista' => true,
        ]))->assertStatus(409);

        $this->assertDatabaseCount('court_reservations', 1);
    }

    // #19 — /day returns free + reserved with member name/description
    public function test_day_returns_free_and_reserved_with_member_data(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'customerName' => 'Maria',
            'description' => 'Reserva especial',
        ]))->assertCreated();

        $day = $this->getJson("/api/court-reservation/{$court->id}/day?date=" . self::ANCHOR_DATE)->assertOk();

        $this->assertSame(self::ANCHOR_DATE, $day->json('date'));
        $this->assertSame(self::WEEKDAY, $day->json('weekday'));

        $slots = collect($day->json('slots'));
        $reserved = $slots->firstWhere('status', 'reserved');
        $this->assertNotNull($reserved);
        $this->assertSame('Maria', $reserved['reservation']['customer_name']);
        $this->assertSame('Reserva especial', $reserved['reservation']['description']);

        $free = $slots->firstWhere('status', 'free');
        $this->assertNotNull($free);
        $this->assertArrayHasKey('default_price_cents', $free);
    }

    // #20 — /agenda?month returns {days} counts
    public function test_agenda_returns_day_counts(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'startTime' => '18:00', 'endTime' => '19:00',
        ]))->assertCreated();
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'startTime' => '19:00', 'endTime' => '20:00',
        ]))->assertCreated();

        $agenda = $this->getJson("/api/court-reservation/{$court->id}/agenda?month=2026-09")->assertOk();
        $this->assertSame(2, $agenda->json('days.' . self::ANCHOR_DATE));
    }

    // #21 — cancel scope=one one-off => hard delete, re-reserve 201
    public function test_cancel_one_off_hard_deletes_and_slot_reusable(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())->assertCreated();
        $reservationId = CourtReservation::first()->id;

        $this->deleteJson("/api/court-reservation/{$court->id}/reservations/{$reservationId}?scope=one")
            ->assertOk()
            ->assertJson(['deleted_count' => 1]);

        $this->assertDatabaseCount('court_reservations', 0);

        // Slot is genuinely freed.
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())->assertCreated();
    }

    // #22 — cancel scope=one on one occurrence of a group => only that row gone
    public function test_cancel_one_occurrence_of_group_leaves_others(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'isMensalista' => true,
        ]))->assertCreated();

        $target = CourtReservation::whereDate('reservation_date', '2026-09-15')->first();

        $this->deleteJson("/api/court-reservation/{$court->id}/reservations/{$target->id}?scope=one")
            ->assertOk()
            ->assertJson(['deleted_count' => 1]);

        $this->assertDatabaseCount('court_reservations', 3);
        $this->assertDatabaseMissing('court_reservations', ['id' => $target->id]);
    }

    // #23 — cancel scope=future => clicked + later gone, earlier kept
    public function test_cancel_future_deletes_clicked_and_later_keeps_earlier(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload([
            'isMensalista' => true,
        ]))->assertCreated();

        // Dates: 08 (anchor), 15, 22, 29. Cancel future from 22.
        $clicked = CourtReservation::whereDate('reservation_date', '2026-09-22')->first();

        $this->deleteJson("/api/court-reservation/{$court->id}/reservations/{$clicked->id}?scope=future")
            ->assertOk()
            ->assertJson(['deleted_count' => 2]); // 22 and 29

        $remaining = CourtReservation::get()
            ->map(fn ($r) => $r->reservation_date->format('Y-m-d'))
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['2026-09-08', '2026-09-15'], $remaining);
    }

    // #24 — cancel scope=future on non-recurring behaves as one
    public function test_cancel_future_on_non_recurring_behaves_as_one(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())->assertCreated();
        $reservationId = CourtReservation::first()->id;

        $this->deleteJson("/api/court-reservation/{$court->id}/reservations/{$reservationId}?scope=future")
            ->assertOk()
            ->assertJson(['deleted_count' => 1]);

        $this->assertDatabaseCount('court_reservations', 0);
    }

    // #25 — cancel missing / wrong court => 404
    public function test_cancel_missing_or_wrong_court_returns_404(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->deleteJson("/api/court-reservation/{$court->id}/reservations/999999?scope=one")
            ->assertStatus(404)
            ->assertJson(['message' => __('error.court_reservation.not_found')]);
    }

    // #26 — all ops: stranger 403 / missing court 404
    public function test_all_ops_stranger_403_and_missing_court_404(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $stranger = $this->grantCourtAllowed(User::factory()->create());
        Sanctum::actingAs($stranger, ['*'], 'api');

        $this->getJson("/api/court-reservation/{$court->id}/day?date=" . self::ANCHOR_DATE)->assertStatus(403);
        $this->getJson("/api/court-reservation/{$court->id}/agenda?month=2026-09")->assertStatus(403);
        $this->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())->assertStatus(403);
        $this->deleteJson("/api/court-reservation/{$court->id}/reservations/1?scope=one")->assertStatus(403);

        // Missing court -> 404 (assertCourtExists). Owner can manage, so 403 is ruled out.
        Sanctum::actingAs($owner, ['*'], 'api');
        $this->getJson('/api/court-reservation/999999/day?date=' . self::ANCHOR_DATE)
            ->assertStatus(404)
            ->assertJson(['message' => __('error.court.not_found')]);
    }

    // #27 — forbidden message is the reservation-specific key under both locales
    public function test_forbidden_message_is_reservation_specific_in_both_locales(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();
        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $stranger = $this->grantCourtAllowed(User::factory()->create());

        foreach (['pt_BR', 'en'] as $locale) {
            Sanctum::actingAs($stranger, ['*'], 'api');

            // Drive locale via the X-Locale header the SetLocale middleware honors.
            $response = $this->withHeader('X-Locale', $locale)
                ->postJson("/api/court-reservation/{$court->id}/reserve", $this->reservePayload())
                ->assertStatus(403);

            $expected = __('error.court_reservation.forbidden', [], $locale);
            $response->assertJson(['message' => $expected]);

            // Must NOT be the raw key (missing-translation sentinel)...
            $this->assertNotSame('error.court_reservation.forbidden', $response->json('message'));
            // ...nor the court_invitation "a equipe / this court team" text.
            $this->assertNotSame(
                __('error.court_invitation.forbidden', [], $locale),
                $response->json('message')
            );
        }
    }
}
