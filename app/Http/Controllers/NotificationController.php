<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationListRequest;
use App\Service\NotificationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    public function index(NotificationListRequest $request): JsonResponse
    {
        $filters = $request->only(['type', 'date_start', 'date_end']);
        $perPage = (int) $request->query('per_page', 15);

        $notifications = $this->notificationService->listForUser(
            auth()->id(),
            $filters,
            $perPage
        );

        return response()->json($notifications, Response::HTTP_OK);
    }

    public function latest(): JsonResponse
    {
        $notifications = $this->notificationService->latestForUser(auth()->id(), 5);

        return response()->json($notifications, Response::HTTP_OK);
    }

    public function unreadCount(): JsonResponse
    {
        $count = $this->notificationService->unreadCount(auth()->id());

        return response()->json(['unread_count' => $count], Response::HTTP_OK);
    }

    public function markAsRead(int $notificationUserId): JsonResponse
    {
        $this->notificationService->markAsRead($notificationUserId, auth()->id());

        return response()->json(['message' => 'Notificação marcada como lida.'], Response::HTTP_OK);
    }

    public function markAllAsRead(): JsonResponse
    {
        $this->notificationService->markAllAsRead(auth()->id());

        return response()->json(['message' => 'Todas as notificações foram marcadas como lidas.'], Response::HTTP_OK);
    }
}
