<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamCreateOrUpdateRequest;
use App\Repository\TeamRepository;
use App\Service\TeamService;
use App\Service\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{
    public function __construct(
        protected UploadService $uploadService,
        protected TeamService $teamService,
        protected TeamRepository $teamRepository,
    ) {

    }

    public function index()
    {
        $teamList = $this->teamRepository->getOrderedByName();

        return response()->json($teamList, Response::HTTP_OK);
    }

    public function save(TeamCreateOrUpdateRequest $request, int $teamId = null): JsonResponse
    {
        $data = $request->only([
            'teamCityId',
            'teamName',
            'teamGender',
            'teamModalityId',
            'teamDescription',
            'teamFoundationDate',
            'teamLogo',
            'teamBanner',
            'teamFacebook',
            'teamInstagram',
            'teamX',
            'teamTiktok',
            'teamYoutube',
            'teamKwai',
        ]);

        if ($teamId) {
            $teamInfo = $this->teamService->updateTeam($data, $teamId);

            $message = "Time atualizado com sucesso";
        } else {
            $teamInfo = $this->teamService->createTeam($data);

            $message = "Time criado com sucesso";
        }

        return response()->json($message, Response::HTTP_CREATED);
    }

    public function show(int $teamId): JsonResponse
    {
        $team = $this->teamRepository->getById($teamId);

        return response()->json($team, Response::HTTP_OK);
    }

    public function listOfManagedTeamsByUser()
    {
        $user = Auth::user();
        $teamList = $this->teamRepository->getTeamsManagedByUser($user);

        return response()->json(['teams' => $teamList], Response::HTTP_OK);
    }
}
