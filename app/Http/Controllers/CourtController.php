<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourtCreateOrUpdateRequest;
use App\Repository\CourtRepository;
use App\Service\CourtService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CourtController extends Controller
{
    public function __construct(
        protected CourtService $courtService,
        protected CourtRepository $courtRepository
    ) {
    }

    public function myCourts(): JsonResponse
    {
        return response()->json(
            ['courts' => $this->courtService->getMyCourts()],
            Response::HTTP_OK
        );
    }

    public function show(int $courtId): JsonResponse
    {
        try {
            $court = $this->courtService->getOwnedDetail($courtId);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json($court, Response::HTTP_OK);
    }

    public function save(CourtCreateOrUpdateRequest $request, int $courtId = null): JsonResponse
    {
        $data = $request->validated();

        try {
            if ($courtId) {
                $this->courtService->updateCourt($data, $courtId);
                $message = __('messages.court.updated');
            } else {
                $this->courtService->createCourt($data);
                $message = __('messages.court.created');
            }
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json($message, Response::HTTP_CREATED);
    }

    public function destroy(int $courtId): JsonResponse
    {
        try {
            $this->courtService->delete($courtId);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json(__('messages.court.deleted'), Response::HTTP_OK);
    }
}
