<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\JWT;
use App\Core\Session;
use App\Core\Middleware;
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

    /**
     * Retourne une réponse JSON.
     */
    protected function json(array $data, int $status = 200): void
    {
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
}
