<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\ContactTicket;
use App\Models\ContactMessage;
use App\Models\Space;
use App\Models\ActivityLog;

/**
 * Contrôleur du contact espace → modération.
 */
class ContactController extends Controller
{
    private ContactTicket $ticketModel;
    private ContactMessage $messageModel;
    private Space $spaceModel;

    public function __construct()
    {
        $this->ticketModel  = new ContactTicket();
        $this->messageModel = new ContactMessage();
        $this->spaceModel   = new Space();
    }

    /**
     * Vérifie l'accès à l'espace (admin ou manager).
     */
    private function checkAccess(string $spaceId): array
    {
        $this->requireAuth();
        $space = $this->spaceModel->find((int) $spaceId);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            exit;
        }
        $member = Middleware::checkSpaceAccess((int) $spaceId, $this->getCurrentUserId());
        if (!$member || !in_array($member['role'], ['admin', 'manager'], true)) {
            $this->setFlash('danger', 'Accès réservé aux administrateurs et gestionnaires.');
            $this->redirect('/spaces/' . $spaceId);
            exit;
        }
        return ['space' => $space, 'role' => $member['role']];
    }

    /**
     * Liste des tickets de contact de l'espace.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $tickets = $this->ticketModel->findBySpace((int) $id);

        $this->render('contact/index', [
            'title'        => 'Contact modération',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['role'],
            'activeMenu'   => 'contact',
            'tickets'      => $tickets,
        ]);
    }

    /**
     * Formulaire de création de ticket.
     */
    public function createForm(string $id): void
    {
        $ctx = $this->checkAccess($id);

        $this->render('contact/create', [
            'title'        => 'Nouveau ticket',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['role'],
            'activeMenu'   => 'contact',
            'categories'   => ContactTicket::CATEGORIES,
        ]);
    }

    /**
     * Traitement de création de ticket.
     */
    public function create(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $data = $this->getPostData(['category', 'subject', 'body']);
        $category = $data['category'];
        $subject  = $data['subject'];
        $body     = $data['body'];

        if (!array_key_exists($category, ContactTicket::CATEGORIES)) {
            $this->setFlash('danger', 'Catégorie invalide.');
            $this->redirect("/spaces/{$id}/contact/create");
            return;
        }
        if ($subject === '' || mb_strlen($subject) > 255) {
            $this->setFlash('danger', 'Le sujet est requis (255 caractères max.).');
            $this->redirect("/spaces/{$id}/contact/create");
            return;
        }
        if ($body === '') {
            $this->setFlash('danger', 'Le message est requis.');
            $this->redirect("/spaces/{$id}/contact/create");
            return;
        }

        $ticketId = $this->ticketModel->create([
            'space_id'  => (int) $id,
            'user_id'   => $this->getCurrentUserId(),
            'category'  => $category,
            'subject'   => $subject,
            'status'    => 'open',
        ]);

        $this->messageModel->create([
            'ticket_id' => $ticketId,
            'user_id'   => $this->getCurrentUserId(),
            'body'      => $body,
        ]);

        ActivityLog::logSpace((int) $id, 'contact_ticket_create', $this->getCurrentUserId(), null, $ticketId, [
            'category'  => $category,
            'subject'   => $subject,
        ]);

        $this->setFlash('success', 'Ticket envoyé avec succès.');
        $this->redirect("/spaces/{$id}/contact/{$ticketId}");
    }

    /**
     * Affiche un ticket et sa conversation.
     */
    public function show(string $id, string $ticketId): void
    {
        $ctx = $this->checkAccess($id);
        $ticket = $this->ticketModel->findWithDetails((int) $ticketId);

        if (!$ticket || (int) $ticket['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect("/spaces/{$id}/contact");
            return;
        }

        $messages = $this->messageModel->findByTicket((int) $ticketId);

        $this->render('contact/show', [
            'title'        => 'Ticket #' . $ticketId,
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['role'],
            'activeMenu'   => 'contact',
            'ticket'       => $ticket,
            'messages'     => $messages,
            'statuses'     => ContactTicket::STATUSES,
        ]);
    }

    /**
     * Répondre à un ticket.
     */
    public function reply(string $id, string $ticketId): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $ticket = $this->ticketModel->findWithDetails((int) $ticketId);
        if (!$ticket || (int) $ticket['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect("/spaces/{$id}/contact");
            return;
        }

        if ($ticket['status'] === 'closed') {
            $this->setFlash('danger', 'Ce ticket est fermé.');
            $this->redirect("/spaces/{$id}/contact/{$ticketId}");
            return;
        }

        $body = $this->getPostData(['body'])['body'];
        if ($body === '') {
            $this->setFlash('danger', 'Le message ne peut pas être vide.');
            $this->redirect("/spaces/{$id}/contact/{$ticketId}");
            return;
        }

        $this->messageModel->create([
            'ticket_id' => (int) $ticketId,
            'user_id'   => $this->getCurrentUserId(),
            'body'      => $body,
        ]);

        // Mettre à jour le updated_at du ticket
        $this->ticketModel->update((int) $ticketId, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->setFlash('success', 'Réponse envoyée.');
        $this->redirect("/spaces/{$id}/contact/{$ticketId}");
    }
}
