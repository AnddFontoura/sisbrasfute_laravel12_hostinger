<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourtReservationCancelRequest;
use App\Http\Requests\CourtReservationStoreRequest;
use App\Service\CourtReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourtReservationController extends Controller
{
    public function __construct(
        protected CourtReservationService $courtReservationService,
    ) {
    }

    public function agenda(Request $request, int $courtId): JsonResponse
    {
        try {
            $data = $this->courtReservationService->agenda(
                $courtId,
                (string) $request->query('month')
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json($data, Response::HTTP_OK);
    }

    public function day(Request $request, int $courtId): JsonResponse
    {
        try {
            $data = $this->courtReservationService->day(
                $courtId,
                (string) $request->query('date')
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json($data, Response::HTTP_OK);
    }

    public function reserve(CourtReservationStoreRequest $request, int $courtId): JsonResponse
    {
        try {
            $result = $this->courtReservationService->reserve($request->validated(), $courtId);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json([
            'message' => __('messages.court_reservation.created'),
            'created_count' => $result['created_count'],
            'skipped_dates' => $result['skipped_dates'],
        ], Response::HTTP_CREATED);
    }

    public function cancel(CourtReservationCancelRequest $request, int $courtId, int $reservationId): JsonResponse
    {
        try {
            $result = $this->courtReservationService->cancel(
                $courtId,
                $reservationId,
                $request->validated()['scope'] ?? 'one'
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }

        return response()->json([
            'message' => __('messages.court_reservation.cancelled'),
            'deleted_count' => $result['deleted_count'],
        ], Response::HTTP_OK);
    }
}
