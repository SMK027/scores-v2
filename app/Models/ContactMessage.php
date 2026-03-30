<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle ContactMessage - Messages d'un ticket de contact.
 */
class ContactMessage extends Model
{
    protected string $table = 'contact_messages';

    /**
     * Liste les messages d'un ticket (conversation ordonnée).
     */
    public function findByTicket(int $ticketId): array
    {
        $stmt = $this->query(
            "SELECT m.*, u.username, u.global_role, u.avatar
             FROM {$this->table} m
             JOIN users u ON u.id = m.user_id
             WHERE m.ticket_id = :ticket_id
             ORDER BY m.created_at ASC",
            ['ticket_id' => $ticketId]
        );
        return $stmt->fetchAll();
    }
}
