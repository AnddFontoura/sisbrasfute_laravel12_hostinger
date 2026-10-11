<?php

namespace App\Repository;

use App\Models\Court;

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

    public function myCourts(int $userId)
    {
        return $this->model
            ->with(['photos', 'modalityInfo', 'cityInfo.stateInfo'])
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }

    public function getOwnedDetail(int $id, int $userId)
    {
        return $this->model
            ->with(['contacts', 'photos', 'pricingRules', 'scheduleConfigs', 'modalityInfo', 'cityInfo.stateInfo'])
            ->where('id', $id)
            ->where('user_id', $userId)
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
