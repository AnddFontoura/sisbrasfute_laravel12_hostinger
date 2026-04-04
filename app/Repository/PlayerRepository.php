<?php

namespace App\Repository;

use App\Models\Player;

class PlayerRepository extends BaseRepository
{
    public function __construct(Player $player)
    {
        $this->model = $player;
    }

    public function paginatedByName()
    {
        return $this->model
            ->orderBy('name')
            ->paginate(15);
    }

    public function updateOrCreate(array $data)
    {
        return $this->model
            ->updateOrCreate(
                [
                    'user_id' => $data['user_id']
                ],
                $data
            );
    }

    public function getById(int $id)
    {
        return $this->model
            ->with('cityInfo.stateInfo')
            ->where('id', $id)
            ->first();
    }

    public function getByUserId(int $userId)
    {
        return $this->model
            ->where('user_id', $userId)
            ->first();
    }
}
