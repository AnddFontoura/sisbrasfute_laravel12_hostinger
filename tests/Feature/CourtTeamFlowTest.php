<?php

namespace Tests\Feature;

use App\Enums\CourtInvitationStatus;
use App\Enums\CourtRole;
use App\Mail\CourtTeamInvitationMail;
use App\Models\Court;
use App\Models\CourtInvitation;
use App\Models\User;
use App\Repository\CourtMembershipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourtTeamFlowTest extends TestCase
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

    /**
     * Create a court owned by $owner through the API so the owner pivot and the
     * global court-owner role are wired exactly as production does.
     */
    private function createCourtAs(User $owner, int $modalityId, int $cityId): Court
    {
        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson('/api/court/save', $this->courtPayload($modalityId, $cityId))->assertCreated();

        return Court::latest('id')->first();
    }

    // #5
    public function test_owner_invites_a_manager(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $response = $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'invitee@example.com',
            'role' => 'manager',
        ]);

        $response->assertCreated();

        $invitation = CourtInvitation::first();
        $this->assertNotNull($invitation);
        $this->assertSame('invitee@example.com', $invitation->email);
        $this->assertSame(CourtInvitationStatus::Pending, $invitation->status);
        $this->assertSame(CourtRole::Manager, $invitation->role);
        $this->assertEqualsWithDelta(
            now()->addHours(48)->timestamp,
            $invitation->expires_at->timestamp,
            60
        );

        Mail::assertQueued(CourtTeamInvitationMail::class);
    }

    // #6
    public function test_owner_invites_an_employee(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'employee@example.com',
            'role' => 'employee',
        ])->assertCreated();

        $this->assertSame(CourtRole::Employee, CourtInvitation::first()->role);
    }

    // #7
    public function test_non_owner_cannot_send_invitation(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $manager = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $manager->id, CourtRole::Manager);

        $employee = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $employee->id, CourtRole::Employee);

        $stranger = User::factory()->create();

        foreach ([$manager, $employee, $stranger] as $actor) {
            Sanctum::actingAs($actor, ['*'], 'api');
            $this->postJson("/api/court-invitation/{$court->id}/send", [
                'email' => 'someone@example.com',
                'role' => 'employee',
            ])->assertStatus(403);
        }
    }

    // #8
    public function test_invite_with_owner_role_returns_422(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'someone@example.com',
            'role' => 'owner',
        ])->assertStatus(422);
    }

    // #9
    public function test_self_invite_returns_422(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'OWNER@example.com',
            'role' => 'manager',
        ])->assertStatus(422);
    }

    // #10
    public function test_duplicate_pending_invite_returns_409(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $payload = ['email' => 'dup@example.com', 'role' => 'manager'];
        $this->postJson("/api/court-invitation/{$court->id}/send", $payload)->assertCreated();
        $this->postJson("/api/court-invitation/{$court->id}/send", $payload)->assertStatus(409);
    }

    // #11
    public function test_unregistered_email_registers_then_accepts_and_creates_pivot(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'newcomer@example.com',
            'role' => 'manager',
        ])->assertCreated();

        $invitation = CourtInvitation::first();

        // Simulate the recipient registering after receiving the invite.
        $newUser = User::factory()->create(['email' => 'newcomer@example.com']);

        Sanctum::actingAs($newUser, ['*'], 'api');
        $this->postJson('/api/court-invitation/accept', ['token' => $invitation->token])
            ->assertOk();

        $pivot = app(CourtMembershipRepository::class)->firstActive($court->id, $newUser->id);
        $this->assertNotNull($pivot);
        $this->assertSame(CourtRole::Manager, $pivot->role);

        $invitation->refresh();
        $this->assertSame(CourtInvitationStatus::Accepted, $invitation->status);
        $this->assertSame($newUser->id, $invitation->accepted_by);
        $this->assertNotNull($invitation->accepted_at);
    }

    // #12
    public function test_already_registered_email_must_accept_to_get_pivot(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $invitee = User::factory()->create(['email' => 'known@example.com']);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'known@example.com',
            'role' => 'employee',
        ])->assertCreated();

        // Not auto-added before acceptance.
        $this->assertNull(app(CourtMembershipRepository::class)->firstActive($court->id, $invitee->id));

        $invitation = CourtInvitation::first();
        Sanctum::actingAs($invitee, ['*'], 'api');
        $this->postJson('/api/court-invitation/accept', ['token' => $invitation->token])->assertOk();

        $this->assertNotNull(app(CourtMembershipRepository::class)->firstActive($court->id, $invitee->id));
    }

    // #13
    public function test_accept_with_mismatched_email_returns_403(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'target@example.com',
            'role' => 'manager',
        ])->assertCreated();

        $invitation = CourtInvitation::first();
        $other = User::factory()->create(['email' => 'other@example.com']);

        Sanctum::actingAs($other, ['*'], 'api');
        $this->postJson('/api/court-invitation/accept', ['token' => $invitation->token])
            ->assertStatus(403);
    }

    // #14
    public function test_accept_after_expiry_returns_4xx_and_lazy_marks_expired(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $invitee = User::factory()->create(['email' => 'late@example.com']);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'late@example.com',
            'role' => 'manager',
        ])->assertCreated();

        $invitation = CourtInvitation::first();

        Carbon::setTestNow(now()->addHours(49));

        Sanctum::actingAs($invitee, ['*'], 'api');
        $response = $this->postJson('/api/court-invitation/accept', ['token' => $invitation->token]);
        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertLessThan(500, $response->status());

        // No pivot was created for the expired invite.
        $this->assertNull(app(CourtMembershipRepository::class)->firstActive($court->id, $invitee->id));

        Carbon::setTestNow();
    }

    // #15
    public function test_accept_already_resolved_invite_returns_4xx(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $invitee = User::factory()->create(['email' => 'once@example.com']);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'once@example.com',
            'role' => 'manager',
        ])->assertCreated();

        $invitation = CourtInvitation::first();

        Sanctum::actingAs($invitee, ['*'], 'api');
        $this->postJson('/api/court-invitation/accept', ['token' => $invitation->token])->assertOk();

        $response = $this->postJson('/api/court-invitation/accept', ['token' => $invitation->token]);
        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertLessThan(500, $response->status());
    }

    // #16
    public function test_expire_command_flips_stale_pending_and_leaves_fresh(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $stale = CourtInvitation::create([
            'court_id' => $court->id,
            'email' => 'stale@example.com',
            'role' => CourtRole::Manager,
            'token' => Str::random(64),
            'status' => CourtInvitationStatus::Pending,
            'invited_by' => $owner->id,
            'expires_at' => now()->subHour(),
        ]);

        $fresh = CourtInvitation::create([
            'court_id' => $court->id,
            'email' => 'fresh@example.com',
            'role' => CourtRole::Manager,
            'token' => Str::random(64),
            'status' => CourtInvitationStatus::Pending,
            'invited_by' => $owner->id,
            'expires_at' => now()->addHours(10),
        ]);

        $this->artisan('court:expire-invitations')->assertExitCode(0);

        $this->assertSame(CourtInvitationStatus::Expired, $stale->fresh()->status);
        $this->assertSame(CourtInvitationStatus::Pending, $fresh->fresh()->status);
    }

    // #17
    public function test_owner_cancels_pending_invite(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'cancel@example.com',
            'role' => 'manager',
        ])->assertCreated();

        $invitation = CourtInvitation::first();

        $this->deleteJson("/api/court-invitation/{$court->id}/cancel/{$invitation->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('court_invitations', ['id' => $invitation->id]);

        // No longer surfaced in the recipient's received list.
        $recipient = User::factory()->create(['email' => 'cancel@example.com']);
        Sanctum::actingAs($recipient, ['*'], 'api');
        $received = $this->getJson('/api/court-invitation/received')->assertOk();
        $this->assertCount(0, $received->json('data'));
    }

    // #18
    public function test_manager_can_edit_court_data(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $manager = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $manager->id, CourtRole::Manager);

        Sanctum::actingAs($manager, ['*'], 'api');
        $payload = $this->courtPayload($modalityId, $cityId);
        $payload['courtName'] = 'Quadra Editada';
        // Use a different weekday on update: court_schedule_configs soft-deletes
        // and carries a unique (court_id, weekday) index, so reusing the created
        // weekday would collide with the soft-deleted row on re-insert.
        $payload['scheduleConfigs'] = [
            ['weekday' => 2, 'isActive' => true, 'startTime' => '08:00', 'endTime' => '22:00', 'reservationDuration' => 60],
        ];

        $this->postJson("/api/court/update/{$court->id}", $payload)->assertCreated();

        $this->assertSame('Quadra Editada', $court->fresh()->name);
    }

    // #19
    public function test_employee_cannot_edit_court_data(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $employee = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $employee->id, CourtRole::Employee);

        Sanctum::actingAs($employee, ['*'], 'api');
        $this->postJson("/api/court/update/{$court->id}", $this->courtPayload($modalityId, $cityId))
            ->assertStatus(403);
    }

    // #20
    public function test_manager_cannot_do_team_management(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $manager = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $manager->id, CourtRole::Manager);

        $employee = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $employee->id, CourtRole::Employee);

        Sanctum::actingAs($manager, ['*'], 'api');

        $this->postJson("/api/court-invitation/{$court->id}/send", [
            'email' => 'x@example.com',
            'role' => 'employee',
        ])->assertStatus(403);

        $this->patchJson("/api/court-team/{$court->id}/members/{$employee->id}/role", ['role' => 'manager'])
            ->assertStatus(403);

        $this->deleteJson("/api/court-team/{$court->id}/members/{$employee->id}")
            ->assertStatus(403);
    }

    // #21
    public function test_owner_changes_role_and_cannot_set_owner(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $member = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $member->id, CourtRole::Manager);

        Sanctum::actingAs($owner, ['*'], 'api');

        $this->patchJson("/api/court-team/{$court->id}/members/{$member->id}/role", ['role' => 'employee'])
            ->assertOk();
        $this->assertSame(CourtRole::Employee, app(CourtMembershipRepository::class)->roleFor($court->id, $member->id));

        // Setting owner is rejected at validation (422).
        $this->patchJson("/api/court-team/{$court->id}/members/{$member->id}/role", ['role' => 'owner'])
            ->assertStatus(422);
    }

    // #22
    public function test_remove_member_and_cannot_remove_owner(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $member = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $member->id, CourtRole::Manager);

        Sanctum::actingAs($owner, ['*'], 'api');

        $this->deleteJson("/api/court-team/{$court->id}/members/{$member->id}")
            ->assertNoContent();
        $this->assertNull(app(CourtMembershipRepository::class)->firstActive($court->id, $member->id));

        // The owner row cannot be removed.
        $this->deleteJson("/api/court-team/{$court->id}/members/{$owner->id}")
            ->assertStatus(409);
    }

    // #23
    public function test_owner_only_delete_still_holds(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        $manager = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $manager->id, CourtRole::Manager);

        $employee = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($court->id, $employee->id, CourtRole::Employee);

        foreach ([$manager, $employee] as $actor) {
            Sanctum::actingAs($actor, ['*'], 'api');
            $this->deleteJson("/api/court/delete/{$court->id}")->assertStatus(403);
        }

        Sanctum::actingAs($owner, ['*'], 'api');
        $this->deleteJson("/api/court/delete/{$court->id}")->assertOk();
        $this->assertSoftDeleted('courts', ['id' => $court->id]);
    }

    // #24
    public function test_my_courts_returns_owner_role_for_owner(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $court = $this->createCourtAs($owner, $modalityId, $cityId);

        Sanctum::actingAs($owner, ['*'], 'api');
        $response = $this->getJson('/api/court/my-courts')->assertOk();

        $courts = $response->json('courts');
        $this->assertCount(1, $courts);
        $this->assertSame('owner', $courts[0]['my_role']);
    }

    // #25
    public function test_my_courts_returns_shared_courts_with_role(): void
    {
        Storage::fake('public');
        Mail::fake();
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $ownerA = User::factory()->create();
        $courtA = $this->createCourtAs($ownerA, $modalityId, $cityId);

        $ownerB = User::factory()->create();
        $courtB = $this->createCourtAs($ownerB, $modalityId, $cityId);

        $user = User::factory()->create();
        app(CourtMembershipRepository::class)->createMembership($courtA->id, $user->id, CourtRole::Manager);
        app(CourtMembershipRepository::class)->createMembership($courtB->id, $user->id, CourtRole::Employee);

        Sanctum::actingAs($user, ['*'], 'api');
        $courts = $this->getJson('/api/court/my-courts')->assertOk()->json('courts');

        $byId = collect($courts)->keyBy('id');
        $this->assertCount(2, $courts);
        $this->assertSame('manager', $byId[$courtA->id]['my_role']);
        $this->assertSame('employee', $byId[$courtB->id]['my_role']);
    }
}
