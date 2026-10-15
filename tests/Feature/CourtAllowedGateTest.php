<?php

namespace Tests\Feature;

use App\Enums\CourtInvitationStatus;
use App\Enums\CourtRole;
use App\Models\Court;
use App\Models\CourtInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourtAllowedGateTest extends TestCase
{
    use RefreshDatabase;

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

    private function courtPayload(int $modalityId, int $cityId): array
    {
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
            'photos' => [UploadedFile::fake()->create('court-0.jpg', 100, 'image/jpeg')],
            'scheduleConfigs' => [
                ['weekday' => 1, 'isActive' => true, 'startTime' => '08:00', 'endTime' => '22:00', 'reservationDuration' => 60],
            ],
        ];
    }

    private function createCourtAs(User $owner, int $modalityId, int $cityId): Court
    {
        $this->grantCourtAllowed($owner);
        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson('/api/court/save', $this->courtPayload($modalityId, $cityId))->assertCreated();

        return Court::latest('id')->first();
    }

    // G1 — no role → 403 error.court.access_denied on gated routes.
    public function test_g1_no_role_is_403_on_gated_routes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/court/my-courts');
        $response->assertStatus(403);
        $response->assertJson(['error' => __('error.court.access_denied')]);

        // A second gated surface (court-team) is also blocked.
        $this->getJson('/api/court-team/1/members')->assertStatus(403);
    }

    // G2 — with role → passes the middleware to the controller/service.
    public function test_g2_with_role_passes_the_gate(): void
    {
        Storage::fake('public');
        $user = $this->grantCourtAllowed(User::factory()->create());
        Sanctum::actingAs($user, ['*'], 'api');

        // my-courts reaches the controller (200, empty list) rather than 403.
        $this->getJson('/api/court/my-courts')->assertOk();
    }

    // G3 — carve-out: no-role user can list/accept received invitations; accept
    // grants court-allowed and a follow-up gated request then passes.
    public function test_g3_carveout_received_and_accept_then_inherits(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'invitee@example.com',
            'role' => 'manager',
        ])->assertCreated();

        $invitation = CourtInvitation::first();

        // Fresh invitee WITHOUT court-allowed can reach the carve-out routes.
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $this->assertFalse($invitee->hasRole('court-allowed'));
        Sanctum::actingAs($invitee, ['*'], 'api');

        $received = $this->getJson('/api/court-invitation/received');
        $this->assertNotSame(403, $received->status());

        $this->postJson('/api/court-invitation/accept', ['token' => $invitation->token])
            ->assertOk();

        // Accepting granted court-allowed (invitation inheritance).
        $this->assertTrue($invitee->fresh()->hasRole('court-allowed'));

        // A follow-up gated request now passes the gate. Re-authenticate with a
        // fresh instance so Sanctum resolves the newly granted role.
        Sanctum::actingAs($invitee->fresh(), ['*'], 'api');
        $this->getJson('/api/court/my-courts')->assertOk();
    }

    // G4 — admin toggle grants then revokes; works on a fresh DB; non-admin 403.
    public function test_g4_admin_toggle_grant_then_revoke(): void
    {
        $target = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin, ['*'], 'api');

        // First call grants (firstOrCreate provisions the role on a fresh DB).
        $grant = $this->postJson("/api/admin/users/{$target->id}/court-allowed")->assertOk();
        $this->assertTrue($grant->json('court_allowed'));
        $this->assertTrue($target->fresh()->hasRole('court-allowed'));

        // Second call revokes.
        $revoke = $this->postJson("/api/admin/users/{$target->id}/court-allowed")->assertOk();
        $this->assertFalse($revoke->json('court_allowed'));
        $this->assertFalse($target->fresh()->hasRole('court-allowed'));
    }

    public function test_g4_non_admin_cannot_toggle(): void
    {
        $target = User::factory()->create();
        $nonAdmin = User::factory()->create();
        Sanctum::actingAs($nonAdmin, ['*'], 'api');

        $this->postJson("/api/admin/users/{$target->id}/court-allowed")
            ->assertStatus(403);
    }

    // G5 — court_allowed is present on /user and /admin/users/{id}.
    public function test_g5_court_allowed_present_on_user_payloads(): void
    {
        $user = $this->grantCourtAllowed(User::factory()->create());
        Sanctum::actingAs($user, ['*'], 'api');

        $me = $this->getJson('/api/user')->assertOk();
        $this->assertTrue($me->json('court_allowed'));

        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin, ['*'], 'api');

        $show = $this->getJson("/api/admin/users/{$user->id}")->assertOk();
        $this->assertTrue($show->json('court_allowed'));

        $plain = User::factory()->create();
        $showPlain = $this->getJson("/api/admin/users/{$plain->id}")->assertOk();
        $this->assertFalse($showPlain->json('court_allowed'));
    }
}
