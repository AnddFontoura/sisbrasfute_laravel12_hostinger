<?php

namespace App\Repository;

use App\Enums\CourtRole;
use App\Models\Court;
use Illuminate\Support\Facades\DB;

class CourtRepository extends BaseRepository
{
    public function __construct(Court $court)
    {
        $this->model = $court;
    }

    public function firstOwnedById(int $id, int $userId)
    {
        return $this->model
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function createOwnerMembership(int $courtId, int $userId): void
    {
        $exists = DB::table('court_user')
            ->where('court_id', $courtId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('court_user')->insert([
            'court_id' => $courtId,
            'user_id' => $userId,
            'role' => CourtRole::Owner->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function myCourts(int $userId)
    {
        $courts = $this->model
            ->with(['photos', 'modalityInfo', 'cityInfo.stateInfo'])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('court_user')
                            ->whereColumn('court_user.court_id', 'courts.id')
                            ->where('court_user.user_id', $userId)
                            ->whereNull('court_user.deleted_at');
                    });
            })
            ->orderByDesc('id')
            ->get();

        $roles = DB::table('court_user')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('role', 'court_id');

        return $courts->each(function ($court) use ($roles, $userId) {
            $role = $roles[$court->id] ?? ($court->user_id === $userId ? CourtRole::Owner->value : null);
            $court->setAttribute('my_role', $role);
        });
    }

    public function getOwnedDetail(int $id, int $userId)
    {
        return $this->model
            ->with(['contacts', 'photos', 'pricingRules', 'scheduleConfigs', 'modalityInfo', 'cityInfo.stateInfo'])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function getDetailById(int $id)
    {
        return $this->model
            ->with(['contacts', 'photos', 'pricingRules', 'scheduleConfigs', 'modalityInfo', 'cityInfo.stateInfo'])
            ->where('id', $id)
            ->first();
    }

    public function getDetailForAdmin(int $id)
    {
        return $this->model
            ->with(['contacts', 'photos', 'pricingRules', 'scheduleConfigs', 'modalityInfo', 'cityInfo.stateInfo', 'owner'])
            ->where('id', $id)
            ->first();
    }
}
