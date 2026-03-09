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
use App\Config\Database;
use App\Models\ActivityLog;

/**
 * Contrôleur d'administration globale (superadmin, admin, moderator).
 */
class AdminController extends Controller
{
    private User $userModel;
    private Space $spaceModel;
    private \PDO $pdo;

    public function __construct()
    {
        $this->userModel  = new User();
        $this->spaceModel = new Space();
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

        $this->render('admin/dashboard', [
            'title'        => 'Administration',
            'activeMenu'   => 'admin',
            'stats'        => $stats,
            'recentUsers'  => $recentUsers,
            'recentSpaces' => $recentSpaces,
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

        $users = $this->userModel->paginate($page, $perPage);

        $this->render('admin/users', [
            'title'      => 'Gestion des utilisateurs',
            'activeMenu' => 'admin',
            'users'      => $users['data'],
            'pagination' => $users,
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
     * Liste des espaces.
     */
    public function spaces(): void
    {
        $this->checkAdmin();

        $stmt = $this->pdo->prepare("
            SELECT s.*, u.username AS owner_name,
                   (SELECT COUNT(*) FROM space_members WHERE space_id = s.id) AS member_count,
                   (SELECT COUNT(*) FROM games WHERE space_id = s.id) AS game_count
            FROM spaces s
            JOIN users u ON u.id = s.created_by
            ORDER BY s.restrictions IS NOT NULL DESC, s.created_at DESC
        ");
        $stmt->execute();
        $spaces = $stmt->fetchAll();

        $this->render('admin/spaces', [
            'title'      => 'Gestion des espaces',
            'activeMenu' => 'admin',
            'spaces'     => $spaces,
        ]);
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

        $this->render('admin/ip_ban_form', [
            'title'      => 'Bannir une adresse IP',
            'activeMenu' => 'admin',
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
            // Tous les modérateurs, admins et superadmins peuvent bannir une IP permanemment
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

        $this->setFlash('success', empty($restrictions)
            ? 'Toutes les restrictions ont été levées.'
            : 'Restrictions mises à jour.'
        );
        $this->redirect('/admin/spaces/' . $id . '/restrictions');
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
}
