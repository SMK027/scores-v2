<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\User;
use App\Models\Space;
use App\Config\Database;

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

        $role = $this->getPostData('global_role');
        $allowed = ['user', 'moderator', 'admin', 'superadmin'];

        if (!in_array($role, $allowed)) {
            $this->setFlash('danger', 'Rôle invalide.');
            $this->redirect('/admin/users');
            return;
        }

        $this->userModel->updateGlobalRole((int) $uid, $role);

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
            ORDER BY s.created_at DESC
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
     * Helper : compter une table.
     */
    private function countTable(string $table): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");
        return (int) $stmt->fetchColumn();
    }
}
