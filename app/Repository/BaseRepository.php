<?php

namespace App\Repository;

use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    protected $model;

    protected int $paginateAmount = 15;

    public function create(array $data): Model
    {
        return $this->model
            ->create($data);
    }

    public function getOrderedByName(string $orderBy = 'asc')
    {
        return $this->model
            ->orderBy('name', $orderBy)
            ->get();
    }

    public function paginatedByName()
    {
        return $this->model
            ->orderBy('name')
            ->paginate(15);
    }

    public function getOrderedById(string $orderBy = 'asc')
    {
        return $this->model
            ->orderBy('id', $orderBy)
            ->get();
    }

    public function firstById(int $id)
    {
        return $this->model
            ->where('id', $id)
            ->first();
    }

    public function updateById(array $data, int $id)
    {
        return $this->model
            ->updateOrCreate(
                [
                    'id' => $id
                ],
                $data
            );
    }

    public function createOrUpdateByParameters(array $search, array $newOrUpdate)
    {
        return $this->model
            ->updateOrCreate(
                $search,
                $newOrUpdate
            );
    }
}
