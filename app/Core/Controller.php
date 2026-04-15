<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contrôleur de base.
 * Fournit les méthodes communes à tous les contrôleurs.
 */
abstract class Controller
{
    /**
     * Rend une vue avec le layout principal.
     *
     * @param string $view   Chemin de la vue (ex: 'auth/login')
     * @param array  $data   Données à passer à la vue
     */
    protected function render(string $view, array $data = []): void
    {
        // Extraire les données pour les rendre disponibles dans la vue
        extract($data);

        // Capturer le contenu de la vue
        ob_start();
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            ob_end_clean();
            throw new \RuntimeException("Vue introuvable : {$view}");
        }
        require $viewPath;
        $content = ob_get_clean();

        // Inclure le layout principal
        require __DIR__ . '/../Views/layouts/main.php';
    }

    /**
     * Redirige vers une URL.
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Définit un message flash en session.
     */
    protected function setFlash(string $type, string $message): void
    {
        Session::set('flash', ['type' => $type, 'message' => $message]);
    }

    /**
     * Retourne une réponse JSON.
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Alias pour json().
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        $this->json($data, $statusCode);
    }

    /**
     * Vérifie si la requête est AJAX.
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Vérifie que l'utilisateur est connecté, sinon redirige vers login.
     * Synchronise aussi le rôle global depuis la DB pour refléter immédiatement
     * tout changement effectué par un administrateur sans reconnexion.
     */
    protected function requireAuth(): void
    {
        if (!Session::get('user_id')) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Vous devez être connecté.'], 401);
            }
            $this->setFlash('warning', 'Vous devez être connecté pour accéder à cette page.');
            $this->redirect('/login');
        }

        $this->syncRoleFromDb();
    }

    /**
     * Relit le rôle global de l'utilisateur connecté depuis la DB et met à jour
     * la session si nécessaire. Exécuté au plus une fois par requête HTTP.
     */
    private function syncRoleFromDb(): void
    {
        static $synced = false;
        if ($synced) {
            return;
        }
        $synced = true;

        $userId = (int) Session::get('user_id');
        $user = (new \App\Models\User())->find($userId);

        if (!$user) {
            // Compte supprimé : forcer la déconnexion
            Session::destroy();
            Session::start();
            $this->redirect('/login');
        }

        $status = (string) ($user['account_status'] ?? \App\Models\User::ACCOUNT_STATUS_ACTIVE);
        $isAnonymized = !empty($user['is_anonymized']);
        $isImpersonating = Session::get('impersonator_id') !== null;
        if (($status !== \App\Models\User::ACCOUNT_STATUS_ACTIVE || $isAnonymized) && !$isImpersonating) {
            Session::destroy();
            Session::start();
            Session::set('flash', [
                'type' => 'warning',
                'message' => 'Votre compte est désactivé ou suspendu.',
            ]);
            $this->redirect('/login');
        }

        if ((string) $user['global_role'] !== (string) Session::get('global_role')) {
            Session::set('global_role', $user['global_role']);
        }
    }

    /**
     * Vérifie que l'utilisateur possède un rôle global spécifique.
     */
    protected function requireGlobalRole(array $roles): void
    {
        $this->requireAuth();
        $userRole = Session::get('global_role') ?? 'user';
        if (!in_array($userRole, $roles, true)) {
            $this->setFlash('danger', 'Vous n\'avez pas les permissions nécessaires.');
            $this->redirect('/');
        }
    }

    /**
     * Récupère et valide les données POST.
     */
    protected function getPostData(array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = isset($_POST[$field]) ? trim((string) $_POST[$field]) : '';
        }
        return $data;
    }

    /**
     * Vérifie le token CSRF.
     */
    protected function validateCSRF(): void
    {
        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Token de sécurité invalide.'], 403);
            }
            $this->setFlash('danger', 'Token de sécurité invalide. Veuillez réessayer.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Retourne l'ID de l'utilisateur connecté.
     */
    protected function getCurrentUserId(): ?int
    {
        $id = Session::get('user_id');
        return $id ? (int) $id : null;
    }

    /**
     * Vérifie si une fonctionnalité est restreinte pour un espace.
     * Si oui, affiche un message et redirige.
     */
    protected function checkSpaceRestriction(int $spaceId, string $key): void
    {
        $spaceModel = new \App\Models\Space();
        if ($spaceModel->isRestricted($spaceId, $key)) {
            $this->setFlash('danger', 'Cette fonctionnalité est temporairement restreinte par l\'administration du site.');
            $this->redirect('/spaces/' . $spaceId);
        }
    }

    /**
     * Vérifie si une fonctionnalité est restreinte pour un utilisateur.
     * Si oui, affiche un message et redirige.
     */
    protected function checkUserRestriction(string $key, ?int $userId = null, ?string $redirectUrl = null): void
    {
        $targetUserId = $userId ?? $this->getCurrentUserId();
        if (!$targetUserId) {
            return;
        }

        $userModel = new \App\Models\User();
        if ($userModel->isRestricted($targetUserId, $key)) {
            $user = $userModel->find($targetUserId);
            $reason = trim((string) ($user['restriction_reason'] ?? ''));
            $message = 'Cette action est temporairement restreinte sur votre compte.';
            if ($reason !== '') {
                $message .= ' Motif: ' . $reason;
            }

            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => $message], 403);
            }

            $this->setFlash('danger', $message);
            $this->redirect($redirectUrl ?? '/');
        }
    }
}
