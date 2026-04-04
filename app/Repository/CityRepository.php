<?php

namespace App\Repository;

use App\Models\City;
use Illuminate\Database\Eloquent\Collection;

class CityRepository extends BaseRepository
{
    public function __construct(City $model)
    {
        $this->model = $model;
    }

    public function getCityByState(int $stateId): Collection
    {
        return $this->model
            ->where('state_id', $stateId)
            ->orderBy('name', 'asc')
            ->get();
    }
}
