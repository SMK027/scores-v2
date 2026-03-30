<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\User;
use App\Models\Space;
use App\Models\UserBan;
use App\Models\IpBan;
use App\Models\PasswordPolicy;
use App\Models\Fail2banConfig;
use App\Models\LeaderboardConfig;
use App\Models\Player;
use App\Models\GameType;
use App\Config\Database;
use App\Models\ActivityLog;
use App\Models\SpaceMember;
use App\Models\PasswordReset;
use App\Core\Mailer;

/**
 * Contrôleur d'administration globale (superadmin, admin, moderator).
 */
class AdminController extends Controller
{
    private User $userModel;
    private Space $spaceModel;
    private Player $playerModel;
    private \PDO $pdo;

    public function __construct()
    {
        $this->userModel  = new User();
        $this->spaceModel = new Space();
        $this->playerModel = new Player();
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Vérifie l'accès admin global.
     */
    private function checkAdmin(): void
    {
        $this->requireAuth();
        if (!Middleware::isGlobalStaff()) {
            $this->setFlash('danger', 'Accès réservé aux administrateurs.');
            $this->redirect('/');
            exit;
        }
    }

    /**
     * Tableau de bord admin.
     */
    public function dashboard(): void
    {
        $this->checkAdmin();

        // Stats globales
        $stats = [];
        $stats['total_users']   = $this->countTable('users');
        $stats['total_spaces']  = $this->countTable('spaces');
        $stats['total_games']   = $this->countTable('games');
        $stats['total_players'] = $this->countTable('players');

        // Derniers inscrits
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, global_role, created_at
            FROM users ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->execute();
        $recentUsers = $stmt->fetchAll();

        // Derniers espaces
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.name, s.created_at, u.username AS owner_name
            FROM spaces s
            JOIN users u ON u.id = s.created_by
            ORDER BY s.created_at DESC LIMIT 10
        ");
        $stmt->execute();
        $recentSpaces = $stmt->fetchAll();

        $role = \App\Core\Session::get('global_role');
        $canAdminOnly = in_array($role, ['admin', 'superadmin'], true);

        $this->render('admin/dashboard', [
            'title'        => 'Administration',
            'activeMenu'   => 'admin',
            'stats'        => $stats,
            'recentUsers'  => $recentUsers,
            'recentSpaces' => $recentSpaces,
            'canAdminOnly' => $canAdminOnly,
        ]);
    }

    /**
     * Liste des utilisateurs.
     */
    public function users(): void
    {
        $this->checkAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $filters = [
            'username' => trim((string) ($_GET['username'] ?? '')),
            'email' => trim((string) ($_GET['email'] ?? '')),
            'global_role' => trim((string) ($_GET['global_role'] ?? '')),
            'created_date' => trim((string) ($_GET['created_date'] ?? '')),
        ];

        $users = $this->userModel->paginate($page, $perPage, $filters);

        $this->render('admin/users', [
            'title'      => 'Gestion des utilisateurs',
            'activeMenu' => 'admin',
            'users'      => $users['data'],
            'pagination' => $users,
            'filters'    => $users['filters'] ?? $filters,
        ]);
    }

    /**
     * Modifier le rôle global d'un utilisateur.
     */
    public function updateUserRole(string $uid): void
    {
        $this->checkAdmin();

        if (!Middleware::isSuperAdmin()) {
            $this->setFlash('danger', 'Seul le superadmin peut modifier les rôles.');
            $this->redirect('/admin/users');
            return;
        }

        $this->validateCSRF();

        $user = $this->userModel->find((int) $uid);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/admin/users');
            return;
        }

        // Ne pas modifier son propre rôle
        if ($user['id'] == $this->getCurrentUserId()) {
            $this->setFlash('warning', 'Vous ne pouvez pas modifier votre propre rôle.');
            $this->redirect('/admin/users');
            return;
        }

        $data = $this->getPostData(['global_role']);
        $role = $data['global_role'];
        $allowed = ['user', 'moderator', 'admin', 'superadmin'];

        if (!in_array($role, $allowed)) {
            $this->setFlash('danger', 'Rôle invalide.');
            $this->redirect('/admin/users');
            return;
        }

        $this->userModel->updateGlobalRole((int) $uid, $role);

        ActivityLog::logAdmin('user.role_update', $this->getCurrentUserId(), 'user', (int) $uid, ['username' => $user['username'], 'new_role' => $role]);

        $this->setFlash('success', "Rôle de {$user['username']} mis à jour : {$role}");
        $this->redirect('/admin/users');
    }

    /**
     * Formulaire de gestion des restrictions d'un utilisateur.
     */
    public function userRestrictions(string $uid): void
    {
        $this->checkAdminOrSuperAdmin();

        $user = $this->userModel->find((int) $uid);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/admin/users');
            return;
        }

        if (in_array((string) ($user['global_role'] ?? 'user'), ['admin', 'superadmin'], true)) {
            $this->setFlash('danger', 'Les restrictions compte ne peuvent pas être appliquées aux administrateurs et super-administrateurs globaux.');
            $this->redirect('/admin/users');
            return;
        }

        $restrictions = $this->userModel->getRestrictions((int) $uid);

        $this->render('admin/user_restrictions', [
            'title'           => 'Restrictions compte — ' . $user['username'],
            'activeMenu'      => 'admin',
            'targetUser'      => $user,
            'restrictions'    => $restrictions,
            'restrictionKeys' => User::RESTRICTION_KEYS,
        ]);
    }

    /**
     * Enregistre les restrictions d'un utilisateur.
     */
    public function updateUserRestrictions(string $uid): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $user = $this->userModel->find((int) $uid);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/admin/users');
            return;
        }

        if (in_array((string) ($user['global_role'] ?? 'user'), ['admin', 'superadmin'], true)) {
            $this->setFlash('danger', 'Les restrictions compte ne peuvent pas être appliquées aux administrateurs et super-administrateurs globaux.');
            $this->redirect('/admin/users');
            return;
        }

        if ((int) $user['id'] === (int) $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Vous ne pouvez pas vous auto-restreindre.');
            $this->redirect('/admin/users/' . $uid . '/restrictions');
            return;
        }

        $restrictions = [];
        foreach (array_keys(User::RESTRICTION_KEYS) as $key) {
            if (!empty($_POST['restrict_' . $key])) {
                $restrictions[$key] = true;
            }
        }

        $reason = trim($_POST['reason'] ?? '');
        if (!empty($restrictions) && empty($reason)) {
            $this->setFlash('danger', 'Un motif est requis pour appliquer des restrictions.');
            $this->redirect('/admin/users/' . $uid . '/restrictions');
            return;
        }

        $this->userModel->setRestrictions((int) $uid, $restrictions, $reason, $this->getCurrentUserId());

        ActivityLog::logAdmin(
            empty($restrictions) ? 'user.restrictions_removed' : 'user.restrictions_updated',
            $this->getCurrentUserId(),
            'user',
            (int) $uid,
            ['username' => $user['username'], 'restrictions' => $restrictions, 'reason' => $reason]
        );

        $this->setFlash('success', empty($restrictions)
            ? 'Toutes les restrictions compte ont été levées.'
            : 'Restrictions compte mises à jour.'
        );
        $this->redirect('/admin/users/' . $uid . '/restrictions');
    }

    /**
     * Liste des espaces.
     */
    public function spaces(): void
    {
        $this->checkAdmin();

        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? '';
        $sort   = $_GET['sort'] ?? '';

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(s.name LIKE :search1 OR u.username LIKE :search2)';
            $params['search1'] = '%' . $search . '%';
            $params['search2'] = '%' . $search . '%';
        }

        if ($status === 'restricted') {
            $where[] = 's.restrictions IS NOT NULL';
        } elseif ($status === 'deletion') {
            $where[] = 's.scheduled_deletion_at IS NOT NULL';
        } elseif ($status === 'clean') {
            $where[] = 's.restrictions IS NULL AND s.scheduled_deletion_at IS NULL';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderSql = match ($sort) {
            'name'         => 's.name ASC',
            'members_desc' => 'member_count DESC',
            'members_asc'  => 'member_count ASC',
            'games_desc'   => 'game_count DESC',
            'games_asc'    => 'game_count ASC',
            'oldest'       => 's.created_at ASC',
            default        => 's.scheduled_deletion_at IS NOT NULL DESC, s.restrictions IS NOT NULL DESC, s.created_at DESC',
        };

        $sql = "
            SELECT s.*, u.username AS owner_name,
                   (SELECT COUNT(*) FROM space_members WHERE space_id = s.id) AS member_count,
                   (SELECT COUNT(*) FROM games WHERE space_id = s.id) AS game_count
            FROM spaces s
            JOIN users u ON u.id = s.created_by
            {$whereSql}
            ORDER BY {$orderSql}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $spaces = $stmt->fetchAll();

        $this->render('admin/spaces', [
            'title'      => 'Gestion des espaces',
            'activeMenu' => 'admin',
            'spaces'     => $spaces,
            'search'     => $search,
            'status'     => $status,
            'sort'       => $sort,
        ]);
    }

    /**
     * Liste des joueurs supprimés (soft delete) avec restauration possible.
     */
    public function deletedPlayers(): void
    {
        $this->checkAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $search = trim((string) ($_GET['q'] ?? ''));

        $where = ['p.deleted_at IS NOT NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(p.name LIKE :q OR s.name LIKE :q OR u.username LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM players p
             INNER JOIN spaces s ON s.id = p.space_id
             LEFT JOIN users u ON u.id = p.user_id
             {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $listStmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.space_id, p.user_id, p.deleted_at,
                    s.name AS space_name,
                    u.username AS linked_username
             FROM players p
             INNER JOIN spaces s ON s.id = p.space_id
             LEFT JOIN users u ON u.id = p.user_id
             {$whereSql}
             ORDER BY p.deleted_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $listStmt->bindValue(':' . $key, $value);
        }
        $listStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $listStmt->execute();
        $players = $listStmt->fetchAll();

        $lastPage = max(1, (int) ceil($total / $perPage));

        $this->render('admin/deleted_players', [
            'title'      => 'Joueurs supprimés',
            'activeMenu' => 'admin',
            'players'    => $players,
            'search'     => $search,
            'pagination' => [
                'page' => $page,
                'lastPage' => $lastPage,
                'total' => $total,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Restaure un joueur supprimé (soft delete).
     */
    public function restoreDeletedPlayer(string $pid): void
    {
        $this->checkAdmin();
        $this->validateCSRF();

        $player = $this->playerModel->findDeletedById((int) $pid);
        if (!$player) {
            $this->setFlash('danger', 'Joueur supprimé introuvable.');
            $this->redirect('/admin/players/deleted');
            return;
        }

        $requestedBy = trim((string) ($_POST['requested_by'] ?? ''));
        $requestNote = trim((string) ($_POST['request_note'] ?? ''));

        $linkedUserId = (int) ($player['user_id'] ?? 0);
        if ($linkedUserId > 0 && $this->playerModel->isUserLinkedInSpace((int) $player['space_id'], $linkedUserId)) {
            $this->setFlash('danger', 'Restauration impossible: ce compte utilisateur est déjà lié à un autre joueur actif dans cet espace.');
            $this->redirect('/admin/players/deleted');
            return;
        }

        $restored = $this->playerModel->restore((int) $pid);
        if (!$restored) {
            $this->setFlash('warning', 'Aucune restauration effectuée.');
            $this->redirect('/admin/players/deleted');
            return;
        }

        ActivityLog::logAdmin(
            'player.restore',
            $this->getCurrentUserId(),
            'player',
            (int) $pid,
            [
                'player_name' => (string) $player['name'],
                'space_id' => (int) $player['space_id'],
                'requested_by' => $requestedBy !== '' ? $requestedBy : null,
                'request_note' => $requestNote !== '' ? $requestNote : null,
            ]
        );

        $this->setFlash('success', 'Le joueur a été restauré.');
        $this->redirect('/admin/players/deleted');
    }

    /**
     * Vérifie que l'utilisateur est admin ou superadmin global.
     */
    private function checkAdminOrSuperAdmin(): void
    {
        $this->requireAuth();
        $role = \App\Core\Session::get('global_role');
        if (!in_array($role, ['superadmin', 'admin'], true)) {
            $this->setFlash('danger', 'Accès réservé aux administrateurs.');
            $this->redirect('/');
            exit;
        }
    }

    // =========================================================
    // Configuration Fail2ban
    // =========================================================

    /**
     * Affiche la page de configuration fail2ban.
     */
    public function fail2ban(): void
    {
        $this->checkAdminOrSuperAdmin();

        $f2bModel = new Fail2banConfig();
        $config = $f2bModel->getConfig();

        $this->render('admin/fail2ban', [
            'title'      => 'Configuration Fail2ban',
            'activeMenu' => 'admin',
            'config'     => $config,
        ]);
    }

    /**
     * Met à jour la configuration fail2ban.
     */
    public function updateFail2ban(): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $f2bModel = new Fail2banConfig();

        $updates = [
            'enabled'        => isset($_POST['enabled']) ? '1' : '0',
            'max_attempts'   => (string) max(1, (int) ($_POST['max_attempts'] ?? 3)),
            'window_minutes' => (string) max(1, (int) ($_POST['window_minutes'] ?? 15)),
            'ban_duration'   => (string) max(1, (int) ($_POST['ban_duration'] ?? 30)),
            'ban_ip'         => isset($_POST['ban_ip']) ? '1' : '0',
            'ban_account'    => isset($_POST['ban_account']) ? '1' : '0',
            'exempt_staff'   => isset($_POST['exempt_staff']) ? '1' : '0',
        ];

        $f2bModel->updateAll($updates);

        ActivityLog::logAdmin('fail2ban.update', $this->getCurrentUserId(), null, null, $updates);

        $this->setFlash('success', 'Configuration Fail2ban mise à jour avec succès.');
        $this->redirect('/admin/fail2ban');
    }

    /**
     * Affiche la configuration des criteres du leaderboard global.
     */
    public function leaderboardCriteria(): void
    {
        $this->checkAdminOrSuperAdmin();

        $model = new LeaderboardConfig();
        $config = $model->getConfig();

        $this->render('admin/leaderboard_criteria', [
            'title'      => 'Critères du leaderboard',
            'activeMenu' => 'admin',
            'config'     => $config,
        ]);
    }

    /**
     * Met a jour les criteres du leaderboard global.
     */
    public function updateLeaderboardCriteria(): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $model = new LeaderboardConfig();

        $minRounds = max(1, (int) ($_POST['min_rounds_played'] ?? 5));
        $minSpaces = max(1, (int) ($_POST['min_spaces_played'] ?? 2));

        $updates = [
            'min_rounds_played' => (string) $minRounds,
            'min_spaces_played' => (string) $minSpaces,
        ];

        $model->updateAll($updates);

        ActivityLog::logAdmin('leaderboard_criteria.update', $this->getCurrentUserId(), null, null, $updates);

        $this->setFlash('success', 'Critères du leaderboard mis à jour avec succès.');
        $this->redirect('/admin/leaderboard-criteria');
    }

    /**
     * Affiche le formulaire de politique de mot de passe.
     */
    public function passwordPolicy(): void
    {
        $this->checkAdminOrSuperAdmin();

        $policyModel = new PasswordPolicy();
        $settings = $policyModel->getPolicyWithLabels();

        $this->render('admin/password_policy', [
            'title'      => 'Politique de mot de passe',
            'activeMenu' => 'admin',
            'settings'   => $settings,
        ]);
    }

    /**
     * Met à jour la politique de mot de passe.
     */
    public function updatePasswordPolicy(): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $policyModel = new PasswordPolicy();

        $updates = [];

        // min_length
        $minLength = max(1, (int) ($_POST['min_length'] ?? 8));
        $updates['min_length'] = (string) $minLength;

        // Champs booléens
        $booleanFields = ['require_lowercase', 'require_uppercase', 'require_digit', 'require_special'];
        foreach ($booleanFields as $field) {
            $updates[$field] = isset($_POST[$field]) ? '1' : '0';
        }

        $policyModel->updateAll($updates);

        ActivityLog::logAdmin('password_policy.update', $this->getCurrentUserId(), null, null, $updates);

        $this->setFlash('success', 'Politique de mot de passe mise à jour avec succès.');
        $this->redirect('/admin/password-policy');
    }

    /**
     * Helper : compter une table.
     */
    private function countTable(string $table): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");
        return (int) $stmt->fetchColumn();
    }

    // =========================================================
    // Gestion des bannissements de comptes
    // =========================================================

    /**
     * Liste des bannissements de comptes.
     */
    public function userBans(): void
    {
        $this->checkAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filter = $_GET['filter'] ?? 'all';

        $banModel = new UserBan();
        $bans = $banModel->listAll($page, 20, $filter);

        $this->render('admin/user_bans', [
            'title'      => 'Bannissements de comptes',
            'activeMenu' => 'admin',
            'bans'       => $bans['data'],
            'pagination' => $bans,
            'filter'     => $filter,
        ]);
    }

    /**
     * API JSON : recherche d'utilisateurs pour l'autocomplétion du formulaire de ban.
     */
    public function searchUsersApi(): void
    {
        $this->checkAdmin();

        $term = trim($_GET['q'] ?? '');
        if (mb_strlen($term) < 2) {
            $this->json(['results' => []]);
            return;
        }

        $globalRole = \App\Core\Session::get('global_role');
        $includeStaff = ($globalRole === 'superadmin');

        $users = $this->userModel->searchForBan($term, $includeStaff);
        $this->json(['results' => $users]);
    }

    /**
     * Formulaire de bannissement d'un compte.
     */
    public function userBanForm(): void
    {
        $this->checkAdmin();

        $globalRole = \App\Core\Session::get('global_role');

        $this->render('admin/user_ban_form', [
            'title'        => 'Bannir un compte',
            'activeMenu'   => 'admin',
            'isSuperAdmin' => ($globalRole === 'superadmin'),
        ]);
    }

    /**
     * Traite le bannissement d'un compte.
     */
    public function createUserBan(): void
    {
        $this->checkAdmin();
        $this->validateCSRF();

        $data = $this->getPostData(['user_id', 'reason', 'duration_type', 'duration_value', 'duration_unit']);
        $globalRole = \App\Core\Session::get('global_role');

        if (empty($data['user_id']) || empty($data['reason'])) {
            $this->setFlash('danger', 'L\'utilisateur et la raison sont requis.');
            $this->redirect('/admin/bans/users/create');
            return;
        }

        $user = $this->userModel->find((int) $data['user_id']);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/admin/bans/users/create');
            return;
        }

        // Interdire de bannir un superadmin
        if ($user['global_role'] === 'superadmin') {
            $this->setFlash('danger', 'Impossible de bannir un super administrateur.');
            $this->redirect('/admin/bans/users');
            return;
        }

        // Interdire de bannir un admin ou modérateur (sauf par un superadmin)
        if (in_array($user['global_role'], ['admin', 'moderator'], true) && $globalRole !== 'superadmin') {
            $this->setFlash('danger', 'Seuls les super administrateurs peuvent bannir un administrateur ou un modérateur.');
            $this->redirect('/admin/bans/users');
            return;
        }

        $expiresAt = null;
        if ($data['duration_type'] === 'permanent') {
            // Seuls admin et superadmin peuvent bannir des comptes de façon permanente
            if (!in_array($globalRole, ['admin', 'superadmin'], true)) {
                $this->setFlash('danger', 'Seuls les administrateurs peuvent appliquer un bannissement de compte permanent.');
                $this->redirect('/admin/bans/users/create');
                return;
            }
        } else {
            $value = max(1, (int) ($data['duration_value'] ?? 1));
            $unit = $data['duration_unit'] ?? 'hours';
            $validUnits = ['minutes', 'hours', 'days', 'weeks', 'months'];
            if (!in_array($unit, $validUnits, true)) {
                $unit = 'hours';
            }
            $interval = new \DateInterval($this->durationToInterval($value, $unit));
            $expires = new \DateTime();
            $expires->add($interval);
            $expiresAt = $expires->format('Y-m-d H:i:s');
        }

        $banModel = new UserBan();
        $banModel->ban((int) $data['user_id'], $this->getCurrentUserId(), $data['reason'], $expiresAt);

        ActivityLog::logAdmin('user.ban', $this->getCurrentUserId(), 'user', (int) $data['user_id'], ['username' => $user['username'], 'reason' => $data['reason'], 'permanent' => ($expiresAt === null)]);

        // Invalider les sessions de l'utilisateur banni en marquant le ban
        // (la vérification dans index.php le déconnectera à sa prochaine requête)

        $this->setFlash('success', 'L\'utilisateur « ' . e($user['username']) . ' » a été banni.');
        $this->redirect('/admin/bans/users');
    }

    /**
     * Annule un bannissement de compte (admin/superadmin uniquement).
     */
    public function revokeUserBan(string $bid): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $banModel = new UserBan();
        $ban = $banModel->find((int) $bid);

        if (!$ban) {
            $this->setFlash('danger', 'Bannissement introuvable.');
            $this->redirect('/admin/bans/users');
            return;
        }

        $banModel->revoke((int) $bid, $this->getCurrentUserId());

        ActivityLog::logAdmin('user.unban', $this->getCurrentUserId(), 'user_ban', (int) $bid);

        $this->setFlash('success', 'Bannissement annulé.');
        $this->redirect('/admin/bans/users');
    }

    // =========================================================
    // Gestion des bannissements d'IP
    // =========================================================

    /**
     * Liste des bannissements d'IP.
     */
    public function ipBans(): void
    {
        $this->checkAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filter = $_GET['filter'] ?? 'all';

        $banModel = new IpBan();
        $bans = $banModel->listAll($page, 20, $filter);

        $this->render('admin/ip_bans', [
            'title'      => 'Bannissements d\'IP',
            'activeMenu' => 'admin',
            'bans'       => $bans['data'],
            'pagination' => $bans,
            'filter'     => $filter,
        ]);
    }

    /**
     * Formulaire de bannissement d'IP.
     */
    public function ipBanForm(): void
    {
        $this->checkAdmin();

        $role = \App\Core\Session::get('global_role');
        $canBanPermanently = in_array($role, ['admin', 'superadmin'], true);

        $this->render('admin/ip_ban_form', [
            'title'            => 'Bannir une adresse IP',
            'activeMenu'       => 'admin',
            'canBanPermanently' => $canBanPermanently,
        ]);
    }

    /**
     * Traite le bannissement d'une IP.
     */
    public function createIpBan(): void
    {
        $this->checkAdmin();
        $this->validateCSRF();

        $data = $this->getPostData(['ip_address', 'reason', 'duration_type', 'duration_value', 'duration_unit']);
        $globalRole = \App\Core\Session::get('global_role');

        if (empty($data['ip_address']) || empty($data['reason'])) {
            $this->setFlash('danger', 'L\'adresse IP et la raison sont requises.');
            $this->redirect('/admin/bans/ips/create');
            return;
        }

        if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP)) {
            $this->setFlash('danger', 'Adresse IP invalide.');
            $this->redirect('/admin/bans/ips/create');
            return;
        }

        $expiresAt = null;
        if ($data['duration_type'] === 'permanent') {
            $role = \App\Core\Session::get('global_role');
            if (!in_array($role, ['admin', 'superadmin'], true)) {
                $this->setFlash('danger', 'Les modérateurs ne peuvent pas imposer de bannissement IP permanent. Veuillez choisir une durée limitée.');
                $this->redirect('/admin/bans/ips/create');
                return;
            }
        } else {
            $value = max(1, (int) ($data['duration_value'] ?? 1));
            $unit = $data['duration_unit'] ?? 'hours';
            $validUnits = ['minutes', 'hours', 'days', 'weeks', 'months'];
            if (!in_array($unit, $validUnits, true)) {
                $unit = 'hours';
            }
            $interval = new \DateInterval($this->durationToInterval($value, $unit));
            $expires = new \DateTime();
            $expires->add($interval);
            $expiresAt = $expires->format('Y-m-d H:i:s');
        }

        $banModel = new IpBan();
        $banModel->ban($data['ip_address'], $this->getCurrentUserId(), $data['reason'], $expiresAt);

        ActivityLog::logAdmin('ip.ban', $this->getCurrentUserId(), 'ip', null, ['ip' => $data['ip_address'], 'reason' => $data['reason'], 'permanent' => ($expiresAt === null)]);

        $this->setFlash('success', 'L\'adresse IP « ' . e($data['ip_address']) . ' » a été bannie.');
        $this->redirect('/admin/bans/ips');
    }

    /**
     * Annule un bannissement d'IP (admin/superadmin uniquement).
     */
    public function revokeIpBan(string $bid): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $banModel = new IpBan();
        $ban = $banModel->find((int) $bid);

        if (!$ban) {
            $this->setFlash('danger', 'Bannissement introuvable.');
            $this->redirect('/admin/bans/ips');
            return;
        }

        $banModel->revoke((int) $bid, $this->getCurrentUserId());

        ActivityLog::logAdmin('ip.unban', $this->getCurrentUserId(), 'ip_ban', (int) $bid);

        $this->setFlash('success', 'Bannissement d\'IP annulé.');
        $this->redirect('/admin/bans/ips');
    }

    // =========================================================
    // Journal d'activité
    // =========================================================

    /**
     * Affiche le journal d'activité global.
     */
    public function activityLogs(): void
    {
        $this->checkAdminOrSuperAdmin();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'scope'  => $_GET['scope'] ?? '',
            'action' => $_GET['action'] ?? '',
            'user'   => $_GET['user'] ?? '',
        ];

        $logModel = new ActivityLog();
        $result = $logModel->search($filters, $page, 50);

        $this->render('admin/activity_logs', [
            'title'      => 'Journal d\'activité',
            'activeMenu' => 'admin',
            'logs'       => $result['data'],
            'pagination' => $result,
            'filters'    => $filters,
        ]);
    }

    /**
     * Formulaire de gestion des restrictions d'un espace.
     */
    public function spaceRestrictions(string $id): void
    {
        $this->checkAdminOrSuperAdmin();

        $spaceModel = new Space();
        $space = $spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/admin/spaces');
            return;
        }

        $restrictions = $spaceModel->getRestrictions((int) $id);

        $this->render('admin/space_restrictions', [
            'title'           => 'Restrictions — ' . $space['name'],
            'activeMenu'      => 'admin',
            'space'           => $space,
            'restrictions'    => $restrictions,
            'restrictionKeys' => Space::RESTRICTION_KEYS,
        ]);
    }

    /**
     * Enregistre les restrictions d'un espace.
     */
    public function updateSpaceRestrictions(string $id): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $spaceModel = new Space();
        $space = $spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/admin/spaces');
            return;
        }

        $restrictions = [];
        foreach (array_keys(Space::RESTRICTION_KEYS) as $key) {
            if (!empty($_POST['restrict_' . $key])) {
                $restrictions[$key] = true;
            }
        }

        $reason = trim($_POST['reason'] ?? '');

        if (!empty($restrictions) && empty($reason)) {
            $this->setFlash('danger', 'Un motif est requis pour appliquer des restrictions.');
            $this->redirect('/admin/spaces/' . $id . '/restrictions');
            return;
        }

        $spaceModel->setRestrictions((int) $id, $restrictions, $reason, $this->getCurrentUserId());

        ActivityLog::logAdmin(
            empty($restrictions) ? 'space.restrictions_removed' : 'space.restrictions_updated',
            $this->getCurrentUserId(),
            'space',
            (int) $id,
            ['restrictions' => $restrictions, 'reason' => $reason]
        );

        // Notifier les gestionnaires par email (BCC)
        if (!empty($restrictions)) {
            $restrictionLabels = [];
            foreach ($restrictions as $key => $v) {
                if (!empty(Space::RESTRICTION_KEYS[$key])) {
                    $restrictionLabels[] = Space::RESTRICTION_KEYS[$key];
                }
            }
            $html = '<h2>⚠️ Restrictions appliquées à votre espace « ' . htmlspecialchars($space['name']) . ' »</h2>'
                  . '<p>L\'administration a appliqué des restrictions sur votre espace :</p>'
                  . '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $restrictionLabels)) . '</li></ul>'
                  . '<p><strong>Motif :</strong> ' . htmlspecialchars($reason) . '</p>'
                  . '<p>Veuillez corriger les infractions aux CGU pour que ces restrictions soient levées.</p>';
            $this->notifySpaceManagers((int) $id, 'Restrictions appliquées — ' . $space['name'], $html);
        }

        $this->setFlash('success', empty($restrictions)
            ? 'Toutes les restrictions ont été levées.'
            : 'Restrictions mises à jour.'
        );
        $this->redirect('/admin/spaces/' . $id . '/restrictions');
    }

    // ─── Auto-destruction programmée ──────────────────────────

    /**
     * Page de planification de la suppression d'un espace.
     */
    public function scheduleDeletion(string $id): void
    {
        $this->checkAdminOrSuperAdmin();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/admin/spaces');
            return;
        }

        $this->render('admin/schedule_deletion', [
            'title'      => 'Suppression programmée — ' . $space['name'],
            'activeMenu' => 'admin',
            'space'      => $space,
        ]);
    }

    /**
     * Enregistre la planification de suppression.
     */
    public function updateScheduleDeletion(string $id): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/admin/spaces');
            return;
        }

        $datetimeInput = trim($_POST['scheduled_at'] ?? '');
        $reason = trim($_POST['deletion_reason'] ?? '');

        if (empty($datetimeInput) || empty($reason)) {
            $this->setFlash('danger', 'La date/heure et le motif sont obligatoires.');
            $this->redirect('/admin/spaces/' . $id . '/schedule-deletion');
            return;
        }

        // Valider et convertir en datetime Paris
        try {
            $paris = new \DateTimeZone('Europe/Paris');
            $dt = new \DateTimeImmutable($datetimeInput, $paris);
            $now = new \DateTimeImmutable('now', $paris);

            if ($dt <= $now) {
                $this->setFlash('danger', 'La date de suppression doit être dans le futur.');
                $this->redirect('/admin/spaces/' . $id . '/schedule-deletion');
                return;
            }

            $minDt = $now->modify('+45 minutes');
            if ($dt < $minDt) {
                $this->setFlash('danger', 'La date de suppression doit être au moins 45 minutes après l\'heure actuelle.');
                $this->redirect('/admin/spaces/' . $id . '/schedule-deletion');
                return;
            }

            $datetimeParis = $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $this->setFlash('danger', 'Format de date invalide.');
            $this->redirect('/admin/spaces/' . $id . '/schedule-deletion');
            return;
        }

        $this->spaceModel->scheduleDeletion((int) $id, $datetimeParis, $reason, $this->getCurrentUserId());

        ActivityLog::logAdmin(
            'space.deletion_scheduled',
            $this->getCurrentUserId(),
            'space',
            (int) $id,
            ['scheduled_at' => $datetimeParis, 'reason' => $reason]
        );

        // Notifier les gestionnaires par email (BCC)
        $html = '<h2>💣 Suppression programmée de votre espace « ' . htmlspecialchars($space['name']) . ' »</h2>'
              . '<p>L\'administration a programmé la suppression automatique de votre espace.</p>'
              . '<p><strong>Date de suppression :</strong> ' . $dt->format('d/m/Y à H:i') . ' (heure de Paris)</p>'
              . '<p><strong>Motif :</strong> ' . htmlspecialchars($reason) . '</p>'
              . '<p>Si vous ne corrigez pas les infractions aux CGU avant cette date, l\'espace sera <strong>définitivement supprimé</strong>.</p>';
        $this->notifySpaceManagers((int) $id, 'Suppression programmée — ' . $space['name'], $html);

        $this->setFlash('success', 'Suppression programmée le ' . $dt->format('d/m/Y à H:i') . ' (heure de Paris).');
        $this->redirect('/admin/spaces/' . $id . '/schedule-deletion');
    }

    /**
     * Annule la suppression programmée d'un espace.
     */
    public function cancelScheduledDeletion(string $id): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/admin/spaces');
            return;
        }

        $this->spaceModel->cancelDeletion((int) $id);

        ActivityLog::logAdmin(
            'space.deletion_cancelled',
            $this->getCurrentUserId(),
            'space',
            (int) $id
        );

        $this->setFlash('success', 'La suppression programmée a été annulée.');
        $this->redirect('/admin/spaces/' . $id . '/schedule-deletion');
    }

    /**
     * Récupère les emails des gestionnaires (admin, manager) et du créateur d'un espace,
     * puis envoie un email unique en BCC.
     */
    private function notifySpaceManagers(int $spaceId, string $subject, string $htmlBody): void
    {
        try {
            $spaceMemberModel = new SpaceMember();
            $members = $spaceMemberModel->findBySpace($spaceId);
            $space = $this->spaceModel->find($spaceId);

            $emails = [];
            foreach ($members as $member) {
                if (in_array($member['role'], ['admin', 'manager'], true) && !empty($member['email'])) {
                    $emails[] = $member['email'];
                }
            }

            // Ajouter le créateur s'il n'est pas déjà dans la liste
            if ($space && !empty($space['created_by'])) {
                $creator = $this->userModel->find((int) $space['created_by']);
                if ($creator && !empty($creator['email'])) {
                    $emails[] = $creator['email'];
                }
            }

            $emails = array_unique(array_filter($emails));
            if (!empty($emails)) {
                (new Mailer())->sendBcc($emails, $subject, $htmlBody);
            }
        } catch (\Exception $e) {
            error_log('Notification espace #' . $spaceId . ' : ' . $e->getMessage());
        }
    }

    /**
     * Convertit une durée en spécification d'intervalle ISO 8601.
     */
    private function durationToInterval(int $value, string $unit): string
    {
        return match ($unit) {
            'minutes' => "PT{$value}M",
            'hours'   => "PT{$value}H",
            'days'    => "P{$value}D",
            'weeks'   => 'P' . ($value * 7) . 'D',
            'months'  => "P{$value}M",
            default   => "PT{$value}H",
        };
    }

    // =========================================================
    // Types de jeux globaux
    // =========================================================

    /**
     * Liste des types de jeux globaux.
     */
    public function globalGameTypes(): void
    {
        $this->checkAdminOrSuperAdmin();

        $gameTypeModel = new GameType();
        $gameTypes = $gameTypeModel->findGlobal();

        $this->render('admin/global_game_types', [
            'title'      => 'Types de jeux globaux',
            'activeMenu' => 'admin',
            'gameTypes'  => $gameTypes,
        ]);
    }

    /**
     * Formulaire de création d'un type de jeu global.
     */
    public function globalGameTypeCreateForm(): void
    {
        $this->checkAdminOrSuperAdmin();

        $this->render('admin/global_game_type_form', [
            'title'      => 'Nouveau type de jeu global',
            'activeMenu' => 'admin',
            'gameType'   => null,
        ]);
    }

    /**
     * Crée un type de jeu global.
     */
    public function createGlobalGameType(): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'description', 'win_condition', 'min_players', 'max_players']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom du type de jeu est requis.');
            $this->redirect('/admin/game-types/create');
            return;
        }

        $gameTypeModel = new GameType();
        $gameTypeModel->create([
            'space_id'      => null,
            'is_global'     => 1,
            'name'          => $data['name'],
            'description'   => $data['description'],
            'win_condition' => $data['win_condition'] ?: 'highest_score',
            'min_players'   => (int) ($data['min_players'] ?: 2),
            'max_players'   => !empty($data['max_players']) ? (int) $data['max_players'] : null,
        ]);

        ActivityLog::logAdmin('global_game_type.create', $this->getCurrentUserId(), 'game_type', null, ['name' => $data['name']]);

        $this->setFlash('success', 'Type de jeu global créé.');
        $this->redirect('/admin/game-types');
    }

    /**
     * Formulaire d'édition d'un type de jeu global.
     */
    public function globalGameTypeEditForm(string $gtid): void
    {
        $this->checkAdminOrSuperAdmin();

        $gameTypeModel = new GameType();
        $gameType = $gameTypeModel->find((int) $gtid);

        if (!$gameType || empty($gameType['is_global'])) {
            $this->setFlash('danger', 'Type de jeu global introuvable.');
            $this->redirect('/admin/game-types');
            return;
        }

        $this->render('admin/global_game_type_form', [
            'title'      => 'Modifier le type de jeu global',
            'activeMenu' => 'admin',
            'gameType'   => $gameType,
        ]);
    }

    /**
     * Met à jour un type de jeu global.
     */
    public function updateGlobalGameType(string $gtid): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $gameTypeModel = new GameType();
        $gameType = $gameTypeModel->find((int) $gtid);

        if (!$gameType || empty($gameType['is_global'])) {
            $this->setFlash('danger', 'Type de jeu global introuvable.');
            $this->redirect('/admin/game-types');
            return;
        }

        $data = $this->getPostData(['name', 'description', 'win_condition', 'min_players', 'max_players']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom est requis.');
            $this->redirect("/admin/game-types/{$gtid}/edit");
            return;
        }

        $gameTypeModel->update((int) $gtid, [
            'name'          => $data['name'],
            'description'   => $data['description'],
            'win_condition' => $data['win_condition'] ?: 'highest_score',
            'min_players'   => (int) ($data['min_players'] ?: 2),
            'max_players'   => !empty($data['max_players']) ? (int) $data['max_players'] : null,
        ]);

        ActivityLog::logAdmin('global_game_type.update', $this->getCurrentUserId(), 'game_type', (int) $gtid, ['name' => $data['name']]);

        $this->setFlash('success', 'Type de jeu global mis à jour.');
        $this->redirect('/admin/game-types');
    }

    /**
     * Supprime un type de jeu global.
     */
    public function deleteGlobalGameType(string $gtid): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $gameTypeModel = new GameType();
        $gameType = $gameTypeModel->find((int) $gtid);

        if (!$gameType || empty($gameType['is_global'])) {
            $this->setFlash('danger', 'Type de jeu global introuvable.');
            $this->redirect('/admin/game-types');
            return;
        }

        ActivityLog::logAdmin('global_game_type.delete', $this->getCurrentUserId(), 'game_type', (int) $gtid, ['name' => $gameType['name']]);

        $gameTypeModel->delete((int) $gtid);
        $this->setFlash('success', 'Type de jeu global supprimé.');
        $this->redirect('/admin/game-types');
    }

    // =========================================================
    // Réinitialisation de mot de passe (admin)
    // =========================================================

    /**
     * Formulaire de demande de réinitialisation admin.
     */
    public function adminResetPasswordForm(string $uid): void
    {
        $this->checkAdminOrSuperAdmin();

        $userModel = new User();
        $user = $userModel->find((int) $uid);

        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/admin/users');
        }

        if (!empty($user['is_anonymized'])) {
            $this->setFlash('danger', 'Ce compte est anonymisé et ne peut pas être réinitialisé.');
            $this->redirect('/admin/users');
        }

        $this->render('admin/reset_password', [
            'title'      => 'Réinitialisation de mot de passe',
            'targetUser' => $user,
        ]);
    }

    /**
     * Traite la demande de réinitialisation admin.
     */
    public function adminResetPassword(string $uid): void
    {
        $this->checkAdminOrSuperAdmin();
        $this->validateCSRF();

        $userModel = new User();
        $user = $userModel->find((int) $uid);

        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/admin/users');
            return;
        }

        if (!empty($user['is_anonymized'])) {
            $this->setFlash('danger', 'Ce compte est anonymisé et ne peut pas être réinitialisé.');
            $this->redirect('/admin/users');
            return;
        }

        $data = $this->getPostData(['duration']);
        $durationMinutes = (int) ($data['duration'] ?? 0);

        // Validation : entre 5 minutes et 3 jours (4320 minutes)
        if ($durationMinutes < 5 || $durationMinutes > 4320) {
            $this->setFlash('danger', 'La durée doit être comprise entre 5 minutes et 3 jours.');
            $this->redirect("/admin/users/{$uid}/reset-password");
            return;
        }

        $resetModel = new PasswordReset();
        $token = $resetModel->createToken($user['id'], $durationMinutes);

        $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
        $resetLink = $appUrl . '/reset-password/' . $token;

        $durationLabel = $this->formatDurationLabel($durationMinutes);

        try {
            $mailer = new Mailer();
            $subject = 'Réinitialisation de votre mot de passe – Scores';
            $body = $this->buildAdminResetEmail($user['username'], $resetLink, $durationLabel);
            $mailer->send($user['email'], $subject, $body);
        } catch (\RuntimeException $e) {
            $this->setFlash('danger', 'Erreur lors de l\'envoi de l\'email.');
            $this->redirect("/admin/users/{$uid}/reset-password");
            return;
        }

        ActivityLog::logAdmin('user.password_reset_sent', $this->getCurrentUserId(), 'user', (int) $uid, [
            'username' => $user['username'],
            'duration' => $durationLabel,
        ]);

        $this->setFlash('success', "Lien de réinitialisation envoyé à {$user['email']} (valide {$durationLabel}).");
        $this->redirect('/admin/users');
    }

    /**
     * Formate une durée en minutes en texte lisible.
     */
    private function formatDurationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minute" . ($minutes > 1 ? 's' : '');
        }
        $hours = intdiv($minutes, 60);
        $remainMinutes = $minutes % 60;
        if ($hours < 24) {
            $label = "{$hours} heure" . ($hours > 1 ? 's' : '');
            if ($remainMinutes > 0) {
                $label .= " {$remainMinutes} min";
            }
            return $label;
        }
        $days = intdiv($hours, 24);
        $remainHours = $hours % 24;
        $label = "{$days} jour" . ($days > 1 ? 's' : '');
        if ($remainHours > 0) {
            $label .= " {$remainHours}h";
        }
        return $label;
    }

    /**
     * Construit l'email HTML de réinitialisation initié par un admin.
     */
    private function buildAdminResetEmail(string $username, string $resetLink, string $durationLabel): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
    <div style="background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">🎲 Scores</h1>
        <p style="margin: 8px 0 0; opacity: 0.9;">Réinitialisation de mot de passe</p>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 12px 12px;">
        <p>Bonjour <strong>{$username}</strong>,</p>
        <p>Un administrateur a initié la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{$resetLink}" style="display: inline-block; background: #4361ee; color: white; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px;">
                Réinitialiser mon mot de passe
            </a>
        </div>
        <p style="color: #666; font-size: 14px;">Ce lien est valable <strong>{$durationLabel}</strong> et ne peut être utilisé qu'une seule fois.</p>
        <p style="color: #666; font-size: 14px;">Si vous n'êtes pas à l'origine de cette demande, contactez un administrateur.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #999; font-size: 12px;">Si le bouton ne fonctionne pas, copiez-collez ce lien :<br><a href="{$resetLink}" style="color: #4361ee; word-break: break-all;">{$resetLink}</a></p>
    </div>
</body>
</html>
HTML;
    }
}
