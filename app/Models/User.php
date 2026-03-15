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

    /**
     * Clés de restriction possibles au niveau compte utilisateur.
     */
    public const RESTRICTION_KEYS = [
        'space_create'               => 'Création d\'espaces',
        'space_join'                 => 'Ajout dans des espaces existants',
        'games_manage'               => 'Création/modification/suppression de parties',
        'competitions_participation' => 'Participation aux compétitions',
        'profile_photo_manage'       => 'Création/modification/suppression de photo de profil',
        'comments_manage'            => 'Création/modification/suppression de commentaires',
    ];

    /**
     * Crée un nouvel utilisateur avec mot de passe hashé.
     * La vérification email est requise pour les nouveaux comptes.
     */
    public function register(string $username, string $email, string $password): int
    {
        return $this->create([
            'username'                    => $username,
            'email'                       => $email,
            'password_hash'               => password_hash($password, PASSWORD_DEFAULT),
            'global_role'                 => 'user',
            'email_verification_required' => 1,
        ]);
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
        $user = $this->findOneBy(['email' => $email]);
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
        return $this->findOneBy(['email' => $email]);
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
        return $this->update($id, $filtered);
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
     * Liste tous les utilisateurs avec pagination.
     */
    public function paginate(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];

        $where = '';
        if ($search) {
            $where = "WHERE username LIKE :search OR email LIKE :search2";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }

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
        ];
    }
}
