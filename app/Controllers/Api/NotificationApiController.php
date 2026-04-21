<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Notification;

/**
 * API de notifications pour l'application mobile.
 * Utilise l'authentification JWT (Bearer token).
 */
class NotificationApiController extends ApiController
{
    private Notification $notifModel;

    public function __construct()
    {
        $this->notifModel = new Notification();
    }

    /**
     * Endpoint de polling léger.
     * GET /api/notifications/poll
     *
     * Retourne :
     *   { unread_count, notifications, new_items, max_id }
     *
     * Paramètre optionnel ?since=ID : retourne les nouvelles notifs depuis cet ID.
     */
    public function poll(): void
    {
        $this->requireAuth();

        $unreadCount   = $this->notifModel->countUnread($this->userId);
        $notifications = $this->notifModel->getRecentForUser($this->userId, 15);

        $maxId = 0;
        if (!empty($notifications)) {
            $maxId = (int) max(array_column($notifications, 'id'));
        }

        $sinceId  = max(0, (int) ($_GET['since'] ?? 0));
        $newItems = $sinceId > 0
            ? $this->notifModel->getNewSince($this->userId, $sinceId)
            : [];

        $this->json([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
            'new_items'     => $newItems,
            'max_id'        => $maxId,
        ]);
    }

    /**
     * Marque une notification comme lue.
     * POST /api/notifications/{id}/read
     */
    public function markRead(string $id): void
    {
        $this->requireAuth();
        $this->notifModel->markAsRead((int) $id, $this->userId);
        $this->json(['success' => true]);
    }

    /**
     * Marque toutes les notifications comme lues.
     * POST /api/notifications/read-all
     */
    public function markAllRead(): void
    {
        $this->requireAuth();
        $this->notifModel->markAllAsRead($this->userId);
        $this->json(['success' => true]);
    }
}
