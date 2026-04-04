<?php

namespace App\Http\Controllers;

use App\Repository\GamePositionRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class   GamePositionController extends Controller
{
    public function __construct(
        protected GamePositionRepository $gamePositionRepository
    ) {

    }

    public function list(Request $request)
    {
        $gamePositions = $this->gamePositionRepository->getOrderedById();

        return response()->json(['gamePositions' => $gamePositions], Response::HTTP_OK);
    }
}
