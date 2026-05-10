<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamApplyCreateOrUpdateRequest;
use App\Repository\TeamApplyRepository;
use App\Service\TeamApplyService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TeamApplicationController extends Controller
{
    public function __construct(
        protected TeamApplyRepository $teamApplyRepository,
        protected TeamApplyService $teamApplyService,
    ) {
    }

    public function save(TeamApplyCreateOrUpdateRequest $request)
    {
        $data = $request->validated();

        $this->teamApplyService->createApplication($data);

        return response()->json(['message' => 'Team application created successfully'], Response::HTTP_CREATED);
    }
}