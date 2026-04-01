<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\JWT;
use App\Core\Session;
use App\Core\Middleware;
use App\Models\ActivityLog;
use App\Models\UserBan;
use App\Models\IpBan;

/**
 * Contrôleur de base pour l'API REST mobile.
 * Gère l'authentification JWT et les réponses JSON.
 */
abstract class ApiController
{
    protected ?int $userId = null;
    protected ?array $userPayload = null;
    private bool $apiRequestLogged = false;

    /**
     * Retourne une réponse JSON.
     */
    protected function json(array $data, int $status = 200): void
    {
        $this->logApiRequest($status, $data);
        // Vider le tampon de sortie pour éviter que des warnings PHP contaminent le JSON
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Retourne une erreur JSON.
     */
    protected function error(string $message, int $status = 400): void
    {
        $this->json(['success' => false, 'message' => $message], $status);
    }

    /**
     * Récupère le token JWT du header Authorization.
     */
    protected function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Exige une authentification JWT valide.
     */
    protected function requireAuth(): void
    {
        $token = $this->getBearerToken();
        if (!$token) {
            $this->error('Token d\'authentification requis.', 401);
        }

        $payload = JWT::decode($token);
        if (!$payload || empty($payload['user_id'])) {
            $this->error('Token invalide ou expiré.', 401);
        }

        // Vérifier bans
        $clientIp = function_exists('get_client_ip') ? get_client_ip() : $_SERVER['REMOTE_ADDR'];

        $ipBanModel = new IpBan();
        if ($ipBanModel->findActiveBan($clientIp)) {
            $this->error('Votre adresse IP est bannie.', 403);
        }

        $userBanModel = new UserBan();
        if ($userBanModel->findActiveBan((int) $payload['user_id'])) {
            $this->error('Votre compte est banni.', 403);
        }

        $this->userId = (int) $payload['user_id'];
        $this->userPayload = $payload;

        // Relire le rôle depuis la DB pour refléter immédiatement un changement
        // effectué par un administrateur sans que l'utilisateur renouvelle son token.
        $freshUser = (new \App\Models\User())->find($this->userId);
        if (!$freshUser) {
            $this->error('Utilisateur introuvable.', 401);
        }

        $status = (string) ($freshUser['account_status'] ?? \App\Models\User::ACCOUNT_STATUS_ACTIVE);
        $isAnonymized = !empty($freshUser['is_anonymized']);
        if ($status !== \App\Models\User::ACCOUNT_STATUS_ACTIVE || $isAnonymized) {
            $this->error('Ce compte est suspendu.', 403);
        }

        $this->userPayload['global_role'] = $freshUser['global_role'];
    }

    /**
     * Vérifie l'accès à un espace et retourne les infos du membre.
     */
    protected function checkSpaceAccess(int $spaceId, array $roles = ['admin', 'manager', 'member', 'guest']): array
    {
        // Simuler la session pour Middleware
        Session::set('user_id', $this->userId);
        Session::set('global_role', $this->userPayload['global_role'] ?? 'user');

        $space = (new \App\Models\Space())->find($spaceId);
        if (!$space) {
            $this->error('Espace introuvable.', 404);
        }

        $member = Middleware::checkSpaceAccess($spaceId, $this->userId, $roles);
        if (!$member) {
            $this->error('Accès non autorisé à cet espace.', 403);
        }

        return ['space' => $space, 'member' => $member];
    }

    /**
     * Récupère les données JSON du corps de la requête.
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Vérifie si l'espace a une restriction sur une fonctionnalité.
     */
    protected function checkSpaceRestriction(int $spaceId, string $key): void
    {
        $spaceModel = new \App\Models\Space();
        if ($spaceModel->isRestricted($spaceId, $key)) {
            $this->error('Cette fonctionnalité est temporairement restreinte.', 403);
        }
    }

    /**
     * Vérifie si une fonctionnalité est restreinte pour un utilisateur.
     */
    protected function checkUserRestriction(string $key, ?int $userId = null): void
    {
        $targetUserId = $userId ?? $this->userId;
        if (!$targetUserId) {
            return;
        }

        $userModel = new \App\Models\User();
        if ($userModel->isRestricted((int) $targetUserId, $key)) {
            $user = $userModel->find((int) $targetUserId);
            $reason = trim((string) ($user['restriction_reason'] ?? ''));
            $message = 'Cette action est temporairement restreinte sur votre compte.';
            if ($reason !== '') {
                $message .= ' Motif: ' . $reason;
            }
            $this->error($message, 403);
        }
    }

    /**
     * Journalise automatiquement les actions API (succès et échecs).
     */
    private function logApiRequest(int $status, array $responseData): void
    {
        if ($this->apiRequestLogged) {
            return;
        }
        $this->apiRequestLogged = true;

        try {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/api');
            $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/api');
            $normalizedPath = preg_replace('#/\d+#', '/:id', $path) ?? $path;
            $normalizedPath = trim($normalizedPath, '/');
            $normalizedPath = str_replace('/', '.', $normalizedPath);
            $normalizedPath = $normalizedPath !== '' ? $normalizedPath : 'root';
            $action = 'api.' . strtolower($method) . '.' . $normalizedPath;
            if (strlen($action) > 100) {
                $action = substr($action, 0, 100);
            }
            $success = array_key_exists('success', $responseData)
                ? (bool) $responseData['success']
                : ($status < 400);

            $details = [
                'path' => $path,
                'method' => $method,
                'status' => $status,
                'success' => $success,
                'message' => isset($responseData['message']) ? (string) $responseData['message'] : null,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null,
            ];

            if (preg_match('#^/api/spaces/(\d+)#', $path, $matches)) {
                ActivityLog::logSpace((int) $matches[1], $action, $this->userId, 'api', null, $details);
                return;
            }

            if (preg_match('#^/api/competitions/(\d+)#', $path, $matches)) {
                ActivityLog::logCompetition((int) $matches[1], $action, $this->userId, 'api', null, null, $details);
                return;
            }

            ActivityLog::logAuth($action, $this->userId, $details);
        } catch (\Throwable $e) {
            // Ne jamais casser une réponse API à cause du logging.
        }
    }
}
