<?php

namespace Tests\Unit;

use App\Enums\CourtRole;
use App\Models\Court;
use App\Models\User;
use App\Repository\CourtMembershipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourtRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_manage_team_only_for_owner(): void
    {
        $this->assertTrue(CourtRole::Owner->canManageTeam());
        $this->assertFalse(CourtRole::Manager->canManageTeam());
        $this->assertFalse(CourtRole::Employee->canManageTeam());
    }

    public function test_can_edit_data_for_owner_and_manager_only(): void
    {
        $this->assertTrue(CourtRole::Owner->canEditData());
        $this->assertTrue(CourtRole::Manager->canEditData());
        $this->assertFalse(CourtRole::Employee->canEditData());
    }

    public function test_can_manage_reservations_for_all_roles(): void
    {
        $this->assertTrue(CourtRole::Owner->canManageReservations());
        $this->assertTrue(CourtRole::Manager->canManageReservations());
        $this->assertTrue(CourtRole::Employee->canManageReservations());
    }

    public function test_role_for_resolves_each_membership_and_null_for_stranger(): void
    {
        [$modalityId, $cityId] = $this->seedModalityAndCity();

        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $stranger = User::factory()->create();

        $court = Court::create([
            'user_id' => $owner->id,
            'modality_id' => $modalityId,
            'city_id' => $cityId,
            'slug' => 'quadra-teste',
            'name' => 'Quadra Teste',
            'surface_type' => 'sintetico',
            'status' => 'pending',
        ]);

        $repository = app(CourtMembershipRepository::class);
        $repository->createMembership($court->id, $owner->id, CourtRole::Owner);
        $repository->createMembership($court->id, $manager->id, CourtRole::Manager);
        $repository->createMembership($court->id, $employee->id, CourtRole::Employee);

        $this->assertSame(CourtRole::Owner, $repository->roleFor($court->id, $owner->id));
        $this->assertSame(CourtRole::Manager, $repository->roleFor($court->id, $manager->id));
        $this->assertSame(CourtRole::Employee, $repository->roleFor($court->id, $employee->id));
        $this->assertNull($repository->roleFor($court->id, $stranger->id));
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
}
