<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    /**
     * Journalise une action sur un espace.
     */
    public static function logSpace(
        int $spaceId,
        string $action,
        ?int $userId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): int {
        return (new self())->log('space', $spaceId, $action, $userId, $entityType, $entityId, null, $details);
    }

    /**
     * Journalise une action sur une compétition.
     */
    public static function logCompetition(
        int $competitionId,
        string $action,
        ?int $userId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $sessionId = null,
        ?array $details = null
    ): int {
        return (new self())->log('competition', $competitionId, $action, $userId, $entityType, $entityId, $sessionId, $details);
    }

    /**
     * Journalise une action d'administration.
     */
    public static function logAdmin(
        string $action,
        ?int $userId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): int {
        return (new self())->log('admin', null, $action, $userId, $entityType, $entityId, null, $details);
    }

    /**
     * Journalise une action d'authentification.
     */
    public static function logAuth(
        string $action,
        ?int $userId,
        ?array $details = null
    ): int {
        return (new self())->log('auth', null, $action, $userId, null, null, null, $details);
    }

    /**
     * Méthode interne de journalisation.
     */
    private function log(
        string $scope,
        ?int $scopeId,
        string $action,
        ?int $userId,
        ?string $entityType,
        ?int $entityId,
        ?int $sessionId,
        ?array $details
    ): int {
        return $this->create([
            'scope' => $scope,
            'scope_id' => $scopeId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * Récupère les logs d'un espace avec pagination.
     */
    public function getBySpace(int $spaceId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->query(
            "SELECT al.*, u.username 
             FROM {$this->table} al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.scope = 'space' AND al.scope_id = :space_id
             ORDER BY al.created_at DESC
             LIMIT :lim OFFSET :off",
            ['space_id' => $spaceId, 'lim' => $limit, 'off' => $offset]
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère les logs d'une compétition avec pagination.
     */
    public function getByCompetition(int $competitionId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->query(
            "SELECT al.*, u.username
             FROM {$this->table} al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.scope = 'competition' AND al.scope_id = :comp_id
             ORDER BY al.created_at DESC
             LIMIT :lim OFFSET :off",
            ['comp_id' => $competitionId, 'lim' => $limit, 'off' => $offset]
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère les logs d'administration avec pagination.
     */
    public function getAdminLogs(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->query(
            "SELECT al.*, u.username
             FROM {$this->table} al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.scope = 'admin'
             ORDER BY al.created_at DESC
             LIMIT :lim OFFSET :off",
            ['lim' => $limit, 'off' => $offset]
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère tous les logs avec filtres et pagination.
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['scope'])) {
            $where[] = 'al.scope = :scope';
            $params['scope'] = $filters['scope'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'al.action LIKE :action';
            $params['action'] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['user'])) {
            $where[] = 'u.username LIKE :user';
            $params['user'] = '%' . $filters['user'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        // Count total
        $countStmt = $this->query(
            "SELECT COUNT(*) FROM {$this->table} al
             LEFT JOIN users u ON al.user_id = u.id
             {$whereClause}",
            $params
        );
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $params['lim'] = $perPage;
        $params['off'] = $offset;
        $stmt = $this->query(
            "SELECT al.*, u.username
             FROM {$this->table} al
             LEFT JOIN users u ON al.user_id = u.id
             {$whereClause}
             ORDER BY al.created_at DESC
             LIMIT :lim OFFSET :off",
            $params
        );

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
