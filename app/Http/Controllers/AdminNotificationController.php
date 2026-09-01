<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminNotificationSendRequest;
use App\Http\Requests\AdminNotificationUpdateRequest;
use App\Service\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'date_start', 'date_end', 'title']);
        $perPage = (int) $request->query('per_page', 15);

        $notifications = $this->notificationService->adminList($filters, $perPage);

        return response()->json($notifications, Response::HTTP_OK);
    }

    public function store(AdminNotificationSendRequest $request): JsonResponse
    {
        $data = $request->validated();

        $notification = $this->notificationService->adminSend($data, auth()->id());

        if (!$notification) {
            return response()->json(
                ['message' => 'Nenhum destinatário encontrado para o público selecionado.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return response()->json($notification->fresh(), Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $notification = $this->notificationService->adminShow($id);

        if (!$notification) {
            return response()->json(['error' => 'Notificação não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($notification, Response::HTTP_OK);
    }

    public function update(AdminNotificationUpdateRequest $request, int $id): JsonResponse
    {
        $notification = $this->notificationService->adminUpdate($id, $request->validated());

        if (!$notification) {
            return response()->json(['error' => 'Notificação não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($notification, Response::HTTP_OK);
    }
}
