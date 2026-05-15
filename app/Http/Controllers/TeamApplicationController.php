<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamApplyAnswerRequest;
use App\Http\Requests\TeamApplyCreateOrUpdateRequest;
use App\Http\Requests\TeamApplyListRequest;
use App\Repository\TeamApplyRepository;
use App\Service\TeamApplyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TeamApplicationController extends Controller
{
    public function __construct(
        protected TeamApplyRepository $teamApplyRepository,
        protected TeamApplyService $teamApplyService,
    ) {
    }

    public function index(TeamApplyListRequest $request, int $teamId, int $page = 1)
    {
        $data = $request->validated();
        $playerApplications = $this->teamApplyRepository->getTeamApplicationsPaginated($data, $teamId, $page);

        return response()->json($playerApplications, Response::HTTP_OK);
    }
    public function save(TeamApplyCreateOrUpdateRequest $request)
    {
        $data = $request->validated();

        $this->teamApplyService->createApplication($data);

        return response()->json(['message' => 'Team application created successfully'], Response::HTTP_CREATED);
    }

    public function answer(TeamApplyAnswerRequest $request, int $teamId, int $teamApplicationId)
    {
        $data = $request->validated();

        $this->teamApplyService->answerApplication($data, $teamId, $teamApplicationId);

        return response()->json(['message' => 'Team application answered successfully'], Response::HTTP_OK);
    }
}