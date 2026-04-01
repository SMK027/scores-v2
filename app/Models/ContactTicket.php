<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle ContactTicket - Tickets de contact entre espaces et modération.
 */
class ContactTicket extends Model
{
    protected string $table = 'contact_tickets';

    public const CATEGORIES = [
        'assistance'           => 'Demande d\'assistance',
        'competition_request'  => 'Demande de compétition',
        'restriction_contest'  => 'Contestation de restriction',
        'member_report'        => 'Signalement de membre(s)',
        'bug_report'           => 'Signalement de bug',
    ];

    public const STATUSES = [
        'open'        => 'Ouvert',
        'in_progress' => 'En cours',
        'closed'      => 'Fermé',
    ];

    /**
     * Liste les tickets d'un espace avec pagination et filtre de statut.
     */
    public function findBySpace(int $spaceId, int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $params = ['space_id' => $spaceId];
        $where  = "t.space_id = :space_id";

        if ($status !== '') {
            $where .= " AND t.status = :status";
            $params['status'] = $status;
        }

        $countStmt = $this->query(
            "SELECT COUNT(*) FROM {$this->table} t WHERE {$where}",
            $params
        );
        $total = (int) $countStmt->fetchColumn();

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $stmt = $this->query(
            "SELECT t.*, u.username AS author_username,
                    (SELECT COUNT(*) FROM contact_messages WHERE ticket_id = t.id) AS message_count
             FROM {$this->table} t
             JOIN users u ON u.id = t.user_id
             WHERE {$where}
             ORDER BY t.updated_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Liste tous les tickets (admin).
     */
    public function findAll(string $status = '', string $category = ''): array
    {
        $sql = "SELECT t.*, u.username AS author_username, s.name AS space_name,
                       (SELECT COUNT(*) FROM contact_messages WHERE ticket_id = t.id) AS message_count
                FROM {$this->table} t
                JOIN users u ON u.id = t.user_id
                JOIN spaces s ON s.id = t.space_id
                WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= " AND t.status = :status";
            $params['status'] = $status;
        }
        if ($category !== '') {
            $sql .= " AND t.category = :category";
            $params['category'] = $category;
        }

        $sql .= " ORDER BY FIELD(t.status, 'open', 'in_progress', 'closed'), t.updated_at DESC";

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Récupère un ticket avec ses détails.
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->query(
            "SELECT t.*, u.username AS author_username, s.name AS space_name
             FROM {$this->table} t
             JOIN users u ON u.id = t.user_id
             JOIN spaces s ON s.id = t.space_id
             WHERE t.id = :id",
            ['id' => $id]
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Met à jour le statut d'un ticket.
     */
    public function updateStatus(int $id, string $status, ?int $closedBy = null): bool
    {
        $data = ['status' => $status];
        if ($status === 'closed') {
            $data['closed_by'] = $closedBy;
            $data['closed_at'] = date('Y-m-d H:i:s');
        }
        return (bool) $this->update($id, $data);
    }

    /**
     * Libellé de la catégorie.
     */
    public static function categoryLabel(string $category): string
    {
        return self::CATEGORIES[$category] ?? $category;
    }

    /**
     * Libellé du statut.
     */
    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }
}
