<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\CSRF;
use App\Models\Notification;

/**
 * Contrôleur des notifications in-app.
 */
class NotificationController extends Controller
{
    private Notification $notifModel;

    public function __construct()
    {
        $this->notifModel = new Notification();
    }

    /**
     * Endpoint de polling léger.
     * GET /notifications/poll
     *
     * Retourne:
     *   { unread_count: int, notifications: [...], max_id: int }
     *
     * Si ?since=ID est fourni, retourne aussi les nouvelles notifs depuis cet ID.
     */
    public function poll(): void
    {
        $this->requireAuth();
        $userId = (int) $this->getCurrentUserId();

        $unreadCount   = $this->notifModel->countUnread($userId);
        $notifications = $this->notifModel->getRecentForUser($userId, 15);

        $maxId = 0;
        if (!empty($notifications)) {
            $maxId = (int) max(array_column($notifications, 'id'));
        }

        // Nouvelles notifications depuis un ID connu (pour toasts JS)
        $sinceId = max(0, (int) ($_GET['since'] ?? 0));
        $newItems = $sinceId > 0
            ? $this->notifModel->getNewSince($userId, $sinceId)
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
     * POST /notifications/{id}/read
     */
    public function markRead(string $id): void
    {
        $this->requireAuth();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Token invalide.'], 403);
            return;
        }

        $this->notifModel->markAsRead((int) $id, (int) $this->getCurrentUserId());
        $this->json(['success' => true]);
    }

    /**
     * Marque toutes les notifications comme lues.
     * POST /notifications/read-all
     */
    public function markAllRead(): void
    {
        $this->requireAuth();

        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Token invalide.'], 403);
            return;
        }

        $this->notifModel->markAllAsRead((int) $this->getCurrentUserId());
        $this->json(['success' => true]);
    }
}
