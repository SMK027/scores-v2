<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\ContactTicket;
use App\Models\ContactMessage;
use App\Models\ActivityLog;

/**
 * API REST pour les tickets de contact d'un espace.
 * Accès réservé aux administrateurs et gestionnaires de l'espace.
 */
class TicketApiController extends ApiController
{
    private ContactTicket $ticketModel;
    private ContactMessage $messageModel;

    public function __construct()
    {
        $this->ticketModel  = new ContactTicket();
        $this->messageModel = new ContactMessage();
    }

    /**
     * GET /api/spaces/{id}/tickets?page=1&status=
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $status = $_GET['status'] ?? '';

        $result = $this->ticketModel->findBySpace((int) $id, $page, 20, $status);

        $this->json(['success' => true, ...$result]);
    }

    /**
     * POST /api/spaces/{id}/tickets
     * Body: { category, subject, body }
     */
    public function create(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'contact');

        $data     = $this->getJsonBody();
        $category = trim($data['category'] ?? '');
        $subject  = trim($data['subject'] ?? '');
        $body     = trim($data['body'] ?? '');

        if (!array_key_exists($category, ContactTicket::CATEGORIES)) {
            $this->error('Catégorie invalide.');
        }
        if ($subject === '' || mb_strlen($subject) > 255) {
            $this->error('Le sujet est requis (255 caractères max.).');
        }
        if ($body === '') {
            $this->error('Le message est requis.');
        }

        $ticketId = $this->ticketModel->create([
            'space_id' => (int) $id,
            'user_id'  => $this->userId,
            'category' => $category,
            'subject'  => $subject,
            'status'   => 'open',
        ]);

        $this->messageModel->create([
            'ticket_id' => $ticketId,
            'user_id'   => $this->userId,
            'body'      => $body,
        ]);

        ActivityLog::logSpace((int) $id, 'contact_ticket_create', $this->userId, null, $ticketId, [
            'category' => $category,
            'subject'  => $subject,
        ]);

        $ticket   = $this->ticketModel->findWithDetails($ticketId);
        $messages = $this->messageModel->findByTicket($ticketId);

        $this->json(['success' => true, 'ticket' => $ticket, 'messages' => $messages], 201);
    }

    /**
     * GET /api/spaces/{id}/tickets/{tid}
     */
    public function show(string $id, string $tid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);

        $ticket = $this->ticketModel->findWithDetails((int) $tid);
        if (!$ticket || (int) $ticket['space_id'] !== (int) $id) {
            $this->error('Ticket introuvable.', 404);
        }

        $messages = $this->messageModel->findByTicket((int) $tid);

        $this->json(['success' => true, 'ticket' => $ticket, 'messages' => $messages]);
    }

    /**
     * POST /api/spaces/{id}/tickets/{tid}/reply
     * Body: { body }
     */
    public function reply(string $id, string $tid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id, ['admin', 'manager']);
        $this->checkSpaceRestriction((int) $id, 'contact');

        $ticket = $this->ticketModel->findWithDetails((int) $tid);
        if (!$ticket || (int) $ticket['space_id'] !== (int) $id) {
            $this->error('Ticket introuvable.', 404);
        }
        if ($ticket['status'] === 'closed') {
            $this->error('Ce ticket est fermé.', 409);
        }

        $data = $this->getJsonBody();
        $body = trim($data['body'] ?? '');
        if ($body === '') {
            $this->error('Le message ne peut pas être vide.');
        }

        $this->messageModel->create([
            'ticket_id' => (int) $tid,
            'user_id'   => $this->userId,
            'body'      => $body,
        ]);

        $this->ticketModel->update((int) $tid, ['updated_at' => date('Y-m-d H:i:s')]);

        $updatedTicket = $this->ticketModel->findWithDetails((int) $tid);
        $messages      = $this->messageModel->findByTicket((int) $tid);

        $this->json(['success' => true, 'ticket' => $updatedTicket, 'messages' => $messages]);
    }
}
