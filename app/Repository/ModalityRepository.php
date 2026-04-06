<?php

namespace App\Repository;

use App\Models\Modality;

class ModalityRepository extends BaseRepository
{
    public function __construct(Modality $model)
    {
        $this->model = $model;
    }
}
