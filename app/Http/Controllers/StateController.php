<?php

namespace App\Http\Controllers;

use App\Repository\StateRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StateController extends Controller
{
    public function __construct(
      protected StateRepository $stateRepository
    ) {
    }
    public function list(Request $request): JsonResponse
    {
        $states = $this->stateRepository->getOrderedByName();

        return response()->json(['states' => $states], Response::HTTP_OK);
    }
}
