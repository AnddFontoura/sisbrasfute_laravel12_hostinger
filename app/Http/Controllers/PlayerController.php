<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayerCreateOrUpdateRequest;
use App\Repository\PlayerRepository;
use App\Service\PlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerController extends Controller
{
    public function __construct(
        protected PlayerRepository $playerRepository,
        protected PlayerService $playerService
    ) {

    }

    public function index(Request $request): JsonResponse
    {
        $players = $this->playerRepository->paginatedByName();

        return response()->json($players, JsonResponse::HTTP_OK);
    }

    public function save(PlayerCreateOrUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->playerService->saveOrUpdate($data);

        return response()->json(['Dado atualizado com sucesso'], JsonResponse::HTTP_OK);
    }

    public function show(?int $id = null): JsonResponse
    {
        if ($id) {
            $player = $this->playerRepository->getById($id);
        } else {
            $player = $this->playerRepository->getByUserId(Auth::id());
        }

        return response()->json($player, JsonResponse::HTTP_OK);
    }
}
