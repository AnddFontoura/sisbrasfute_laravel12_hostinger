<?php

namespace App\Service;

use App\Repository\ModalityRepository;
use App\Repository\PlayerHasModalitiesRepository;

class ModalityService
{
    public function __construct(
        protected ModalityRepository $modalityRepository,
    ) {
    }

}
