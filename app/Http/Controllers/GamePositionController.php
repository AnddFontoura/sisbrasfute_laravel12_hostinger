<?php

namespace App\Http\Controllers;

use App\Http\Requests\GamePositionListRequest;
use App\Repository\GamePositionRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class   GamePositionController extends Controller
{
    public function __construct(
        protected GamePositionRepository $gamePositionRepository
    ) {

    }

    public function list(GamePositionListRequest $request)
    {
        $data = $request->validated();
        $gamePositions = $this->gamePositionRepository->getOrderedByNameWithParameters($data);

        return response()->json(['gamePositions' => $gamePositions], Response::HTTP_OK);
    }
}
