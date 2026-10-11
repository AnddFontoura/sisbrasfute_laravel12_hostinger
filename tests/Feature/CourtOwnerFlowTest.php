<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourtOwnerFlowTest extends TestCase
{
    use RefreshDatabase;

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

    private function courtPayload(int $modalityId, int $cityId, int $photoCount = 1): array
    {
        $photos = [];
        for ($i = 0; $i < $photoCount; $i++) {
            // create() (not image()) avoids the GD dependency while still
            // producing a file whose MIME type satisfies the "image" rule.
            $photos[] = UploadedFile::fake()->create("court-$i.jpg", 100, 'image/jpeg');
        }

        return [
            'courtName' => 'Quadra Teste',
            'courtDescription' => 'Uma quadra de teste',
            'courtModalityId' => $modalityId,
            'courtCityId' => $cityId,
            'courtAddressLine' => 'Rua Teste, 123',
            'courtSurfaceType' => 'sintetico',
            'contacts' => [
                ['type' => 'whatsapp', 'value' => '5511999999999', 'label' => 'Principal'],
            ],
            'photos' => $photos,
            'scheduleConfigs' => [
                ['weekday' => 1, 'isActive' => true, 'startTime' => '08:00', 'endTime' => '22:00', 'reservationDuration' => 60],
            ],
        ];
    }

    public function test_create_court_sets_pending_and_assigns_court_owner_role(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->postJson('/api/court/save', $this->courtPayload($modalityId, $cityId));

        $response->assertCreated();

        $court = Court::first();
        $this->assertNotNull($court);
        $this->assertSame('pending', $court->status->value);
        $this->assertSame($user->id, $court->user_id);
        $this->assertTrue($user->fresh()->hasRole('court-owner'));
    }

    public function test_zero_photos_returns_422(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        Sanctum::actingAs(User::factory()->create(), ['*'], 'api');

        $payload = $this->courtPayload($modalityId, $cityId, 0);

        $this->postJson('/api/court/save', $payload)
            ->assertStatus(422);
    }

    public function test_eleven_photos_returns_422(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        Sanctum::actingAs(User::factory()->create(), ['*'], 'api');

        $payload = $this->courtPayload($modalityId, $cityId, 11);

        $this->postJson('/api/court/save', $payload)
            ->assertStatus(422);
    }

    public function test_missing_contact_returns_422(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        Sanctum::actingAs(User::factory()->create(), ['*'], 'api');

        $payload = $this->courtPayload($modalityId, $cityId);
        unset($payload['contacts']);

        $this->postJson('/api/court/save', $payload)
            ->assertStatus(422);
    }

    public function test_bad_surface_returns_422(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        Sanctum::actingAs(User::factory()->create(), ['*'], 'api');

        $payload = $this->courtPayload($modalityId, $cityId);
        $payload['courtSurfaceType'] = 'marmore';

        $this->postJson('/api/court/save', $payload)
            ->assertStatus(422);
    }

    public function test_duplicate_schedule_weekday_returns_422(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        Sanctum::actingAs(User::factory()->create(), ['*'], 'api');

        $payload = $this->courtPayload($modalityId, $cityId);
        $payload['scheduleConfigs'] = [
            ['weekday' => 1, 'isActive' => true, 'startTime' => '08:00', 'endTime' => '22:00', 'reservationDuration' => 60],
            ['weekday' => 1, 'isActive' => false],
        ];

        $this->postJson('/api/court/save', $payload)
            ->assertStatus(422);
    }

    public function test_non_owner_show_returns_403(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson('/api/court/save', $this->courtPayload($modalityId, $cityId))->assertCreated();
        $court = Court::first();

        $other = User::factory()->create();
        Sanctum::actingAs($other, ['*'], 'api');

        $this->getJson("/api/court/show/{$court->id}")
            ->assertStatus(403);
    }

    public function test_admin_approve_sets_status_and_approved_by(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson('/api/court/save', $this->courtPayload($modalityId, $cityId))->assertCreated();
        $court = Court::first();

        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin, ['*'], 'api');

        $this->postJson("/api/admin/courts/{$court->id}/approve")
            ->assertOk();

        $court->refresh();
        $this->assertSame('approved', $court->status->value);
        $this->assertSame($admin->id, $court->approved_by);
        $this->assertNotNull($court->approved_at);
    }

    public function test_admin_reject_sets_status_and_rejection_reason(): void
    {
        Storage::fake('public');
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson('/api/court/save', $this->courtPayload($modalityId, $cityId))->assertCreated();
        $court = Court::first();

        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin, ['*'], 'api');

        $this->postJson("/api/admin/courts/{$court->id}/reject", ['reason' => 'Documentação incompleta'])
            ->assertOk();

        $court->refresh();
        $this->assertSame('rejected', $court->status->value);
        $this->assertSame('Documentação incompleta', $court->rejection_reason);
    }

    public function test_non_admin_hitting_admin_courts_returns_403(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*'], 'api');

        $this->getJson('/api/admin/courts')
            ->assertStatus(403);
    }
}
