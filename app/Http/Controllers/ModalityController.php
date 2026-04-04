<?php

namespace App\Http\Controllers;

use App\Repository\ModalityRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModalityController extends Controller
{
    public function __construct(
        protected ModalityRepository $modalityRepository
    ) {
    }

    public function list(Request $request)
    {
        $modalities = $this->modalityRepository->getOrderedByName();

        return response()->json(['modalities' => $modalities], Response::HTTP_OK);
    }
}
