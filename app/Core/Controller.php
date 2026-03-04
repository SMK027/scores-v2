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
}
