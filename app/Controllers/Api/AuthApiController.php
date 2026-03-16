<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\JWT;
use App\Models\User;
use App\Models\UserBan;
use App\Models\IpBan;
use App\Models\LoginAttempt;
use App\Models\LoginLock;
use App\Models\Fail2banConfig;
use App\Models\PasswordPolicy;
use App\Models\ActivityLog;

/**
 * API d'authentification pour l'application mobile.
 */
class AuthApiController extends ApiController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * POST /api/login
     * Body: { email, password }
     * Retourne un JWT en cas de succès.
     */
    public function login(): void
    {
        $data = $this->getJsonBody();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $clientIp = function_exists('get_client_ip') ? get_client_ip() : $_SERVER['REMOTE_ADDR'];

        if (empty($email) || empty($password)) {
            $this->error('Email et mot de passe requis.');
        }

        // Vérifier verrou fail2ban IP
        $lockModel = new LoginLock();
        $ipLock = $lockModel->findActiveByIp($clientIp);
        if ($ipLock) {
            $unlockAt = date('d/m/Y à H:i', strtotime($ipLock['locked_until']));
            $this->error("Connexion verrouillée jusqu'au {$unlockAt}.", 429);
        }

        // Authentification
        $user = $this->userModel->authenticate($email, $password);
        if (!$user) {
            $this->recordFailedAttempt($clientIp, $email);
            $this->error('Email ou mot de passe incorrect.', 401);
        }

        $accountStatus = (string) ($user['account_status'] ?? User::ACCOUNT_STATUS_ACTIVE);
        $isAnonymized = !empty($user['is_anonymized']);
        if ($accountStatus !== User::ACCOUNT_STATUS_ACTIVE || $isAnonymized) {
            $this->error($this->buildAccountAccessDeniedMessage($user), 403);
        }

        // Vérifier verrou fail2ban compte
        $userLock = $lockModel->findActiveByUser((int) $user['id']);
        if ($userLock) {
            $unlockAt = date('d/m/Y à H:i', strtotime($userLock['locked_until']));
            $this->error("Compte verrouillé jusqu'au {$unlockAt}.", 429);
        }

        // Vérifier bans
        $userBanModel = new UserBan();
        $ban = $userBanModel->findActiveBan((int) $user['id']);
        if ($ban) {
            $this->error('Votre compte est banni. Raison : ' . $ban['reason'], 403);
        }

        $ipBanModel = new IpBan();
        $ipBan = $ipBanModel->findActiveBan($clientIp);
        if ($ipBan) {
            $this->error('Votre adresse IP est bannie.', 403);
        }

        // Succès : effacer tentatives
        $attemptModel = new LoginAttempt();
        $attemptModel->clearByIp($clientIp);
        $attemptModel->clearByUser((int) $user['id']);

        // Générer le JWT
        $token = JWT::encode([
            'user_id'     => (int) $user['id'],
            'username'    => $user['username'],
            'global_role' => $user['global_role'],
        ]);

        ActivityLog::logAuth('login.success.api', (int) $user['id']);

        $this->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'          => (int) $user['id'],
                'username'    => $user['username'],
                'email'       => $user['email'],
                'global_role' => $user['global_role'],
                'avatar'      => $user['avatar'] ?? null,
                'bio'         => $user['bio'] ?? null,
            ],
        ]);
    }

    /**
     * POST /api/register
     * Body: { username, email, password, password_confirm }
     */
    public function register(): void
    {
        $data = $this->getJsonBody();

        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';

        $errors = [];

        if (empty($username)) {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères.';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores.';
        }

        if (empty($email)) {
            $errors[] = 'L\'email est requis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse email invalide.';
        }

        if (empty($password)) {
            $errors[] = 'Le mot de passe est requis.';
        } else {
            $policyModel = new PasswordPolicy();
            $policyErrors = $policyModel->validate($password);
            $errors = array_merge($errors, $policyErrors);
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        if (empty($errors)) {
            if ($this->userModel->findByUsername($username)) {
                $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
            }
            if ($this->userModel->findByEmail($email)) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }
        }

        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $userId = $this->userModel->register($username, $email, $password);
        $user = $this->userModel->find($userId);

        $token = JWT::encode([
            'user_id'     => $userId,
            'username'    => $user['username'],
            'global_role' => $user['global_role'],
        ]);

        ActivityLog::logAuth('register.api', $userId);

        $this->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'          => $userId,
                'username'    => $user['username'],
                'email'       => $user['email'],
                'global_role' => $user['global_role'],
                'avatar'      => $user['avatar'] ?? null,
                'bio'         => $user['bio'] ?? null,
            ],
        ], 201);
    }

    /**
     * GET /api/me — Retourne le profil de l'utilisateur connecté.
     */
    public function me(): void
    {
        $this->requireAuth();

        $user = $this->userModel->find($this->userId);
        if (!$user) {
            $this->error('Utilisateur introuvable.', 404);
        }

        $this->json([
            'success' => true,
            'user'    => [
                'id'          => (int) $user['id'],
                'username'    => $user['username'],
                'email'       => $user['email'],
                'global_role' => $user['global_role'],
                'avatar'      => $user['avatar'] ?? null,
                'bio'         => $user['bio'] ?? null,
                'created_at'  => $user['created_at'],
            ],
        ]);
    }

    /**
     * Enregistre une tentative échouée et déclenche fail2ban si nécessaire.
     */
    private function recordFailedAttempt(string $clientIp, ?string $email): void
    {
        $f2bConfig = new Fail2banConfig();
        if (!$f2bConfig->isEnabled()) {
            return;
        }

        $attemptModel = new LoginAttempt();
        $targetUser = $email ? $this->userModel->findByEmail($email) : null;

        $attemptModel->record($clientIp, $email, $targetUser ? (int) $targetUser['id'] : null);

        $maxAttempts   = $f2bConfig->getInt('max_attempts');
        $windowMinutes = $f2bConfig->getInt('window_minutes');
        $banDuration   = $f2bConfig->getInt('ban_duration');
        $banIp         = $f2bConfig->getBool('ban_ip');
        $banAccount    = $f2bConfig->getBool('ban_account');
        $exemptStaff   = $f2bConfig->getBool('exempt_staff');

        $ipAttempts = $attemptModel->countRecentByIp($clientIp, $windowMinutes);

        if ($ipAttempts >= $maxAttempts) {
            $lockedUntil = (new \DateTime())->modify("+{$banDuration} minutes")->format('Y-m-d H:i:s');
            $reason = "Fail2ban API : {$maxAttempts} tentatives en {$windowMinutes} min.";

            $lockModel = new LoginLock();

            if ($banIp && !$lockModel->findActiveByIp($clientIp)) {
                $lockModel->lockIp($clientIp, $lockedUntil, $reason);
            }

            if ($banAccount && $targetUser) {
                $isStaff = in_array($targetUser['global_role'], ['moderator', 'admin', 'superadmin'], true);
                if ((!$isStaff || !$exemptStaff) && !$lockModel->findActiveByUser((int) $targetUser['id'])) {
                    $lockModel->lockUser((int) $targetUser['id'], $lockedUntil, $reason);
                }
            }

            $attemptModel->clearByIp($clientIp);
            if ($targetUser) {
                $attemptModel->clearByUser((int) $targetUser['id']);
            }
        }
    }

    /**
     * Message API lorsque l'accès au compte est suspendu.
     */
    private function buildAccountAccessDeniedMessage(array $user): string
    {
        $status = (string) ($user['account_status'] ?? User::ACCOUNT_STATUS_ACTIVE);
        if ($status === User::ACCOUNT_STATUS_PENDING_DELETION) {
            return 'Votre compte est désactivé suite à une demande de suppression.';
        }
        return 'Ce compte est définitivement suspendu et anonymisé.';
    }
}
