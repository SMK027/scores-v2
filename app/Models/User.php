<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle User - Gestion des utilisateurs.
 */
class User extends Model
{
    protected string $table = 'users';
    private ?bool $hasEmailNormalizedColumn = null;
    private ?bool $hasAccountDeletionColumns = null;

    /**
     * Normalise un email pour les contrôles d'unicité.
     * - Lowercase global
     * - Gmail/Googlemail: suppression des points et du +alias sur la partie locale
     */
    public static function normalizeEmail(string $email): string
    {
        $email = trim(strtolower($email));

        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = explode('+', $local, 2)[0];
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local . '@' . $domain;
    }

    /**
     * Clés de restriction possibles au niveau compte utilisateur.
     */
    public const RESTRICTION_KEYS = [
        'space_create'               => 'Création d\'espaces',
        'space_join'                 => 'Ajout dans des espaces existants',
        'games_manage'               => 'Création/modification/suppression de parties',
        'games_participation'        => 'Participation aux parties',
        'competitions_participation' => 'Participation aux compétitions',
        'arbitration_access'         => 'Accès à l\'arbitrage',
        'profile_photo_manage'       => 'Création/modification/suppression de photo de profil',
        'comments_manage'            => 'Création/modification/suppression de commentaires',
    ];

    public const ACCOUNT_STATUS_ACTIVE = 'active';
    public const ACCOUNT_STATUS_PENDING_DELETION = 'pending_deletion';
    public const ACCOUNT_STATUS_SUSPENDED = 'suspended';

    /**
     * Crée un nouvel utilisateur avec mot de passe hashé.
     * La vérification email est requise pour les nouveaux comptes.
     */
    public function register(string $username, string $email, string $password): int
    {
        $email = trim($email);

        $payload = [
            'username'                    => $username,
            'email'                       => $email,
            'password_hash'               => password_hash($password, PASSWORD_DEFAULT),
            'global_role'                 => 'user',
            'email_verification_required' => 1,
        ];

        if ($this->supportsEmailNormalizedColumn()) {
            $payload['email_normalized'] = self::normalizeEmail($email);
        }

        return $this->create($payload);
    }

    /**
     * Marque l'adresse email d'un utilisateur comme vérifiée.
     */
    public function markEmailVerified(int $id): bool
    {
        return $this->update($id, ['email_verified_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Authentifie un utilisateur par email et mot de passe.
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Ne pas retourner le hash du mot de passe
        unset($user['password_hash']);
        return $user;
    }

    /**
     * Trouve un utilisateur par nom d'utilisateur.
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findOneBy(['username' => $username]);
    }

    /**
     * Trouve un utilisateur par email.
     */
    public function findByEmail(string $email): ?array
    {
        $email = trim($email);
        $normalized = self::normalizeEmail($email);

        if (!$this->supportsEmailNormalizedColumn()) {
            $stmt = $this->query(
                "SELECT *
                 FROM {$this->table}
                 WHERE (
                     CASE
                         WHEN LOWER(SUBSTRING_INDEX(email, '@', -1)) IN ('gmail.com', 'googlemail.com')
                             THEN CONCAT(
                                 REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(LOWER(email), '@', 1), '+', 1), '.', ''),
                                 '@gmail.com'
                             )
                         ELSE LOWER(email)
                     END
                 ) = :normalized
                 ORDER BY id ASC
                 LIMIT 1",
                ['normalized' => $normalized]
            );

            $user = $stmt->fetch();
            return $user ?: null;
        }

        $stmt = $this->query(
            "SELECT *
             FROM {$this->table}
             WHERE email_normalized = :normalized
                OR (email_normalized IS NULL AND email = :email)
             ORDER BY id ASC
             LIMIT 1",
            [
                'normalized' => $normalized,
                'email' => $email,
            ]
        );

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Met à jour le profil d'un utilisateur.
     */
    public function updateProfile(int $id, array $data): bool
    {
        $allowed = ['username', 'email', 'bio', 'avatar', 'show_win_rate_public'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) {
            return false;
        }

        if (isset($filtered['email'])) {
            $filtered['email'] = trim((string) $filtered['email']);
            if ($this->supportsEmailNormalizedColumn()) {
                $filtered['email_normalized'] = self::normalizeEmail($filtered['email']);
            }
        }

        return $this->update($id, $filtered);
    }

    /**
     * Indique si la colonne email_normalized est disponible en base.
     */
    private function supportsEmailNormalizedColumn(): bool
    {
        if ($this->hasEmailNormalizedColumn !== null) {
            return $this->hasEmailNormalizedColumn;
        }

        $stmt = $this->query("SHOW COLUMNS FROM {$this->table} LIKE 'email_normalized'");
        $this->hasEmailNormalizedColumn = (bool) $stmt->fetch();

        return $this->hasEmailNormalizedColumn;
    }

    /**
     * Indique si les colonnes liées au droit à l'oubli sont disponibles.
     */
    private function supportsAccountDeletionColumns(): bool
    {
        if ($this->hasAccountDeletionColumns !== null) {
            return $this->hasAccountDeletionColumns;
        }

        $stmt = $this->query("SHOW COLUMNS FROM {$this->table} LIKE 'account_status'");
        $this->hasAccountDeletionColumns = (bool) $stmt->fetch();

        return $this->hasAccountDeletionColumns;
    }

    /**
     * Retourne les statistiques globales d'un utilisateur (tous espaces confondus).
     * Agrège les données de tous les profils joueurs rattachés au compte.
     */
    public function getGlobalStats(int $userId): array
    {
        $stmt = $this->query(
            "SELECT
                COUNT(DISTINCT gp.game_id) AS total_games,
                COALESCE(SUM(CASE WHEN gp.is_winner = 1 THEN 1 ELSE 0 END), 0) AS total_wins,
                COUNT(DISTINCT p.space_id) AS total_spaces
             FROM players p
             LEFT JOIN game_players gp ON gp.player_id = p.id
             WHERE p.user_id = :user_id",
            ['user_id' => $userId]
        );

        $row = $stmt->fetch();

        $totalGames = (int) ($row['total_games'] ?? 0);
        $totalWins  = (int) ($row['total_wins']  ?? 0);
        $winRate    = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0.0;

        return [
            'total_games'  => $totalGames,
            'total_wins'   => $totalWins,
            'win_rate'     => $winRate,
            'total_spaces' => (int) ($row['total_spaces'] ?? 0),
        ];
    }

    /**
     * Met à jour le mot de passe d'un utilisateur.
     */
    public function updatePassword(int $id, string $password): bool
    {
        return $this->update($id, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    /**
     * Met à jour le rôle global d'un utilisateur.
     */
    public function updateGlobalRole(int $id, string $role): bool
    {
        $validRoles = ['superadmin', 'admin', 'moderator', 'user'];
        if (!in_array($role, $validRoles, true)) {
            return false;
        }
        return $this->update($id, ['global_role' => $role]);
    }

    /**
     * Retourne les restrictions actives d'un utilisateur.
     */
    public function getRestrictions(int $id): array
    {
        $user = $this->find($id);
        if (!$user || empty($user['restrictions'])) {
            return [];
        }
        $data = json_decode((string) $user['restrictions'], true);
        return is_array($data) ? $data : [];
    }

    /**
     * Vérifie si une fonctionnalité est restreinte pour un utilisateur.
     */
    public function isRestricted(int $userId, string $key): bool
    {
        $restrictions = $this->getRestrictions($userId);
        return !empty($restrictions[$key]);
    }

    /**
     * Met à jour les restrictions d'un utilisateur.
     */
    public function setRestrictions(int $id, array $restrictions, ?string $reason, int $adminId): bool
    {
        $active = array_filter($restrictions);
        $json = empty($active) ? null : json_encode($active, JSON_UNESCAPED_UNICODE);

        return $this->update($id, [
            'restrictions' => $json,
            'restriction_reason' => empty($active) ? null : $reason,
            'restricted_by' => empty($active) ? null : $adminId,
            'restricted_at' => empty($active) ? null : date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Retourne les utilisateurs restreints pour une fonctionnalité donnée.
     */
    public function getUsersRestrictedBy(string $key): array
    {
        $stmt = $this->query(
            "SELECT id, username, email, restriction_reason, restrictions
             FROM {$this->table}
             WHERE restrictions IS NOT NULL
             ORDER BY username ASC"
        );

        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $restrictions = json_decode((string) ($row['restrictions'] ?? ''), true);
            if (!is_array($restrictions) || empty($restrictions[$key])) {
                continue;
            }
            $result[] = [
                'id' => (int) $row['id'],
                'username' => (string) ($row['username'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'restriction_reason' => (string) ($row['restriction_reason'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Recherche d'utilisateurs par nom ou email (pour autocomplétion).
     * Exclut les rôles protégés sauf si $includStaff vaut true.
     */
    public function searchForBan(string $term, bool $includeStaff = false, int $limit = 15): array
    {
        $term = '%' . $term . '%';
        $params = ['t1' => $term, 't2' => $term];

        $roleFilter = '';
        if (!$includeStaff) {
            $roleFilter = "AND global_role = 'user'";
        } else {
            // Même un superadmin ne peut pas se bannir lui-même via la recherche,
            // mais on laisse admin/moderator apparaître
            $roleFilter = "AND global_role != 'superadmin'";
        }

        $sql = "SELECT id, username, email, global_role
                FROM {$this->table}
                WHERE (username LIKE :t1 OR email LIKE :t2)
                {$roleFilter}
                ORDER BY username ASC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Liste tous les utilisateurs avec pagination et filtres.
     */
    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $whereParts = [];

        $username = trim((string) ($filters['username'] ?? ''));
        $email = trim((string) ($filters['email'] ?? ''));
        $role = trim((string) ($filters['global_role'] ?? ''));
        $createdDate = trim((string) ($filters['created_date'] ?? ''));

        if ($username !== '') {
            $whereParts[] = 'username LIKE :username';
            $params['username'] = '%' . $username . '%';
        }

        if ($email !== '') {
            $whereParts[] = 'email LIKE :email';
            $params['email'] = '%' . $email . '%';
        }

        if ($role !== '' && in_array($role, ['user', 'moderator', 'admin', 'superadmin'], true)) {
            $whereParts[] = 'global_role = :global_role';
            $params['global_role'] = $role;
        }

        if ($createdDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdDate) === 1) {
            $whereParts[] = 'DATE(created_at) = :created_date';
            $params['created_date'] = $createdDate;
        }

        $where = empty($whereParts) ? '' : 'WHERE ' . implode(' AND ', $whereParts);

        // Nombre total
        $countStmt = $this->query("SELECT COUNT(*) FROM {$this->table} {$where}", $params);
        $total = (int) $countStmt->fetchColumn();

        // Données
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        $stmt = $this->query(
            "SELECT id, username, email, avatar, global_role, restrictions, restricted_at, created_at
             FROM {$this->table} {$where}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );
        $users = $stmt->fetchAll();

        return [
            'data'     => $users,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
            'filters'  => [
                'username' => $username,
                'email' => $email,
                'global_role' => $role,
                'created_date' => $createdDate,
            ],
        ];
    }

    /**
     * Place le compte en suppression demandée (grâce de 15 jours).
     *
     * @return string|null Date de suppression effective (Y-m-d H:i:s) si succès.
     */
    public function requestDeletion(int $id): ?string
    {
        if (!$this->supportsAccountDeletionColumns()) {
            return null;
        }

        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET account_status = :status,
                 deletion_requested_at = NOW(),
                 deletion_effective_at = DATE_ADD(NOW(), INTERVAL 15 DAY),
                 deletion_contact_email = email
             WHERE id = :id
               AND account_status = :active"
        );
        $stmt->execute([
            'status' => self::ACCOUNT_STATUS_PENDING_DELETION,
            'active' => self::ACCOUNT_STATUS_ACTIVE,
            'id' => $id,
        ]);

        if ($stmt->rowCount() <= 0) {
            return null;
        }

        $row = $this->find($id);
        return $row['deletion_effective_at'] ?? null;
    }

    /**
     * Annule une demande de suppression tant que l'échéance n'est pas atteinte.
     */
    public function cancelDeletionRequest(int $id): bool
    {
        if (!$this->supportsAccountDeletionColumns()) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET account_status = :active,
                 deletion_requested_at = NULL,
                 deletion_effective_at = NULL,
                 deletion_contact_email = NULL
             WHERE id = :id
               AND account_status = :pending
               AND deletion_effective_at IS NOT NULL
               AND deletion_effective_at > NOW()"
        );
        $stmt->execute([
            'active' => self::ACCOUNT_STATUS_ACTIVE,
            'pending' => self::ACCOUNT_STATUS_PENDING_DELETION,
            'id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retourne les demandes de suppression arrivées à échéance.
     */
    public function findDueDeletionRequests(int $limit = 100): array
    {
        if (!$this->supportsAccountDeletionColumns()) {
            return [];
        }

        $limit = max(1, $limit);
        $stmt = $this->db->query(
            "SELECT *
             FROM {$this->table}
             WHERE account_status = 'pending_deletion'
               AND deletion_effective_at IS NOT NULL
               AND deletion_effective_at <= NOW()
             ORDER BY deletion_effective_at ASC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    /**
     * Finalise la suppression: suspension définitive + anonymisation + déliaison des joueurs.
     */
    public function finalizeDeletion(int $id): bool
    {
        if (!$this->supportsAccountDeletionColumns()) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();

            if (!$user || ($user['account_status'] ?? '') !== self::ACCOUNT_STATUS_PENDING_DELETION) {
                $this->db->rollBack();
                return false;
            }

            $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $anonymizedUsername = 'deleted_user_' . $id;
            $anonymizedEmail = 'deleted+' . $id . '@anonymized.scores.local';

            $payload = [
                'username' => $anonymizedUsername,
                'email' => $anonymizedEmail,
                'password_hash' => $passwordHash,
                'avatar' => null,
                'bio' => null,
                'global_role' => 'user',
                'account_status' => self::ACCOUNT_STATUS_SUSPENDED,
                'is_anonymized' => 1,
                'anonymized_at' => date('Y-m-d H:i:s'),
                'show_win_rate_public' => 0,
                'restrictions' => null,
                'restriction_reason' => null,
                'restricted_by' => null,
                'restricted_at' => null,
            ];

            if ($this->supportsEmailNormalizedColumn()) {
                $payload['email_normalized'] = self::normalizeEmail($anonymizedEmail);
            }

            $this->update($id, $payload);

            // Le compte n'apparaît plus dans les membres d'espaces.
            $unlinkMembers = $this->db->prepare("DELETE FROM space_members WHERE user_id = :uid");
            $unlinkMembers->execute(['uid' => $id]);

            // Les joueurs liés au compte sont conservés mais dissociés.
            $unlinkPlayers = $this->db->prepare("UPDATE players SET user_id = NULL WHERE user_id = :uid");
            $unlinkPlayers->execute(['uid' => $id]);

            // Révoquer les sessions persistantes.
            $revokeRemember = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = :uid");
            $revokeRemember->execute(['uid' => $id]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
