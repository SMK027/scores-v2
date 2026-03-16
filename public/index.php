<?php

declare(strict_types=1);

/**
 * Point d'entrée de l'application (Front Controller).
 * Toutes les requêtes HTTP passent par ce fichier.
 */

// Charger l'autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Fuseau horaire par défaut : Paris, France
date_default_timezone_set('Europe/Paris');

use App\Core\Router;
use App\Core\Session;
use App\Core\CSRF;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\ProfileController;
use App\Controllers\SpaceController;
use App\Controllers\GameTypeController;
use App\Controllers\PlayerController;
use App\Controllers\GameController;
use App\Controllers\RoundController;
use App\Controllers\StatController;
use App\Controllers\SearchController;
use App\Controllers\AdminController;
use App\Controllers\CompetitionController;
use App\Controllers\CompetitionSessionController;
use App\Controllers\SpaceTransferController;
use App\Controllers\LeaderboardController;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\SpaceApiController;
use App\Controllers\Api\GameApiController;
use App\Controllers\Api\GameTypeApiController;
use App\Controllers\Api\PlayerApiController;
use App\Controllers\Api\ProfileApiController;
use App\Controllers\Api\StatApiController;
use App\Controllers\Api\SearchApiController;
use App\Models\IpBan;
use App\Models\UserBan;
use App\Models\RememberToken;
use App\Models\User;
use App\Models\ActivityLog;

// ============================================================
// CORS pour l'API mobile
// ============================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (str_starts_with(parse_url($requestUri, PHP_URL_PATH) ?? '', '/api/')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Démarrer la session (pas nécessaire pour les routes API pures, mais requis pour le Middleware)
Session::start();

$clientIp = get_client_ip();
$isApiRequest = str_starts_with(parse_url($requestUri, PHP_URL_PATH) ?? '', '/api/');

// ============================================================
// Auto-connexion via cookie "Se souvenir de moi" (web uniquement)
// ============================================================
if (!$isApiRequest && !Session::get('user_id')) {
    $rememberCookie = $_COOKIE['remember_me'] ?? '';

    if ($rememberCookie !== '') {
        $parts = explode(':', $rememberCookie, 2);
        if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            $selector = $parts[0];
            $validator = $parts[1];

            $rememberModel = new RememberToken();
            $rememberModel->purgeExpired();

            $tokenRow = $rememberModel->findBySelector($selector);
            if ($tokenRow && strtotime((string) $tokenRow['expires_at']) > time()) {
                $valid = hash_equals((string) $tokenRow['token_hash'], hash('sha256', $validator));
                if ($valid) {
                    $userModel = new User();
                    $user = $userModel->find((int) $tokenRow['user_id']);
                    $accountStatus = (string) ($user['account_status'] ?? 'active');
                    $isAnonymized = !empty($user['is_anonymized']);

                    if ($user && $accountStatus === 'active' && !$isAnonymized) {
                        Session::regenerate();
                        Session::set('user_id', (int) $user['id']);
                        Session::set('username', $user['username']);
                        Session::set('global_role', $user['global_role']);
                        Session::set('avatar', $user['avatar'] ?? '');
                        CSRF::regenerate();
                        $rememberModel->touch((int) $tokenRow['id']);
                        ActivityLog::logAuth('login.remember', (int) $user['id']);
                    } else {
                        $rememberModel->deleteBySelector($selector);
                    }
                } else {
                    $rememberModel->deleteBySelector($selector);
                }
            } else {
                if ($tokenRow) {
                    $rememberModel->deleteBySelector($selector);
                }
            }
        }

        // Toujours nettoyer le cookie local si aucune session valide n'a été restaurée.
        if (!Session::get('user_id')) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443');

            setcookie('remember_me', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

// ============================================================
// Vérification globale de bannissement IP (sauf API — gérée par JWT)
// ============================================================

if (!$isApiRequest) {
    $ipBanModel = new IpBan();
    $ipBan = $ipBanModel->findActiveBan($clientIp);
if ($ipBan) {
    // Si l'utilisateur est connecté, le déconnecter
    if (Session::get('user_id')) {
        Session::destroy();
        Session::start();
    }
    http_response_code(403);
    $reason = htmlspecialchars($ipBan['reason'], ENT_QUOTES, 'UTF-8');
    $expires = $ipBan['expires_at']
        ? 'jusqu\'au ' . date('d/m/Y à H:i', strtotime($ipBan['expires_at']))
        : 'permanent';
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Accès interdit</title>';
    echo '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#1a1a2e;color:#e0e0e0;margin:0;}';
    echo '.box{background:#16213e;padding:2rem;border-radius:12px;max-width:500px;text-align:center;border:1px solid #e94560;}';
    echo 'h1{color:#e94560;margin-top:0;}p{line-height:1.6;}</style></head><body>';
    echo '<div class="box"><h1>🚫 Accès interdit</h1>';
    echo '<p>Votre adresse IP est bannie du site.</p>';
    echo '<p><strong>Raison :</strong> ' . $reason . '</p>';
    echo '<p><strong>Durée :</strong> ' . $expires . '</p>';
    echo '</div></body></html>';
    exit;
}
} // end if !isApiRequest

// ============================================================
// Vérification bannissement du compte (utilisateur connecté, hors API)
// ============================================================
if (!$isApiRequest) {
$loggedUserId = Session::get('user_id');
if ($loggedUserId) {
    $userBanModel = new UserBan();
    $userBan = $userBanModel->findActiveBan((int) $loggedUserId);
    if ($userBan) {
        // Déconnecter immédiatement
        Session::destroy();
        Session::start();
        $banMsg = 'Votre compte est banni. Raison : ' . htmlspecialchars($userBan['reason'], ENT_QUOTES, 'UTF-8');
        if ($userBan['expires_at']) {
            $banMsg .= ' — Débannissement le ' . date('d/m/Y à H:i', strtotime($userBan['expires_at'])) . '.';
        } else {
            $banMsg .= ' — Bannissement permanent.';
        }
        Session::set('flash', ['type' => 'danger', 'message' => $banMsg]);
        header('Location: /login');
        exit;
    }
}
} // end if !isApiRequest

// Initialiser le routeur
$router = new Router();

// ============================================================
// Routes publiques
// ============================================================
$router->get('/', HomeController::class, 'index');
$router->get('/legal', HomeController::class, 'legal');
$router->get('/login', AuthController::class, 'loginForm');
$router->post('/login', AuthController::class, 'login');
$router->get('/register', AuthController::class, 'registerForm');
$router->post('/register', AuthController::class, 'register');
$router->get('/logout', AuthController::class, 'logout');
$router->get('/forgot-password', AuthController::class, 'forgotPasswordForm');
$router->post('/forgot-password', AuthController::class, 'forgotPassword');
$router->get('/reset-password/{token}', AuthController::class, 'resetPasswordForm');
$router->post('/reset-password/{token}', AuthController::class, 'resetPassword');
// Vérification email — nouveaux comptes (non connectés)
$router->get('/verify-email', AuthController::class, 'verifyEmailForm');
$router->post('/verify-email', AuthController::class, 'verifyEmail');
$router->post('/verify-email/resend', AuthController::class, 'resendVerification');
// Vérification email — comptes existants (connectés)
$router->get('/account/verify-email', AuthController::class, 'requestVerifyEmailForm');
$router->post('/account/verify-email/request', AuthController::class, 'requestVerifyEmail');
$router->post('/account/verify-email/confirm', AuthController::class, 'confirmVerifyEmail');

// ============================================================
// Profil utilisateur
// ============================================================
$router->get('/profile', ProfileController::class, 'show');
$router->get('/profile/calendar/events', ProfileController::class, 'calendarEvents');
$router->get('/profile/calendar', ProfileController::class, 'calendar');
$router->get('/profile/edit', ProfileController::class, 'editForm');
$router->post('/profile/edit', ProfileController::class, 'update');
$router->post('/profile/delete-request', ProfileController::class, 'requestDeletion');
$router->get('/profile/{username}', ProfileController::class, 'showPublic');
$router->get('/leaderboard', LeaderboardController::class, 'index');

// ============================================================
// Espaces
// ============================================================
$router->get('/spaces', SpaceController::class, 'index');
$router->get('/spaces/create', SpaceController::class, 'createForm');
$router->post('/spaces/create', SpaceController::class, 'create');
$router->get('/spaces/join/{token}', SpaceController::class, 'join');
$router->get('/spaces/{id}', SpaceController::class, 'show');
$router->get('/spaces/{id}/edit', SpaceController::class, 'editForm');
$router->post('/spaces/{id}/edit', SpaceController::class, 'update');
$router->post('/spaces/{id}/delete', SpaceController::class, 'delete');
$router->post('/spaces/{id}/leave', SpaceController::class, 'leave');
$router->post('/spaces/{id}/export', SpaceTransferController::class, 'export');
$router->post('/spaces/{id}/import', SpaceTransferController::class, 'import');
$router->get('/spaces/{id}/members', SpaceController::class, 'members');
$router->post('/spaces/{id}/members/add', SpaceController::class, 'addMember');
$router->post('/spaces/{id}/members/{mid}/role', SpaceController::class, 'updateMemberRole');
$router->post('/spaces/{id}/members/{mid}/remove', SpaceController::class, 'removeMember');
$router->post('/spaces/{id}/invite', SpaceController::class, 'invite');
$router->post('/spaces/{id}/invite/{iid}/revoke', SpaceController::class, 'revokeInvite');
$router->post('/spaces/{id}/invitations/{invId}/cancel', SpaceController::class, 'cancelInvitation');
$router->post('/invitations/{invId}/accept', SpaceController::class, 'acceptInvitation');
$router->post('/invitations/{invId}/decline', SpaceController::class, 'declineInvitation');

// ============================================================
// Types de jeux
// ============================================================
$router->get('/spaces/{id}/game-types', GameTypeController::class, 'index');
$router->get('/spaces/{id}/game-types/create', GameTypeController::class, 'createForm');
$router->post('/spaces/{id}/game-types/create', GameTypeController::class, 'create');
$router->get('/spaces/{id}/game-types/{gtid}/edit', GameTypeController::class, 'editForm');
$router->post('/spaces/{id}/game-types/{gtid}/edit', GameTypeController::class, 'update');
$router->post('/spaces/{id}/game-types/{gtid}/delete', GameTypeController::class, 'delete');

// ============================================================
// Joueurs
// ============================================================
$router->get('/spaces/{id}/players', PlayerController::class, 'index');
$router->get('/spaces/{id}/players/create', PlayerController::class, 'createForm');
$router->post('/spaces/{id}/players/create', PlayerController::class, 'create');
$router->get('/spaces/{id}/players/{pid}/edit', PlayerController::class, 'editForm');
$router->post('/spaces/{id}/players/{pid}/edit', PlayerController::class, 'update');
$router->post('/spaces/{id}/players/{pid}/delete', PlayerController::class, 'delete');

// ============================================================
// Parties
// ============================================================
$router->get('/spaces/{id}/games', GameController::class, 'index');
$router->get('/spaces/{id}/games/create', GameController::class, 'createForm');
$router->post('/spaces/{id}/games/create', GameController::class, 'create');
$router->get('/spaces/{id}/games/{gid}', GameController::class, 'show');
$router->get('/spaces/{id}/games/{gid}/edit', GameController::class, 'editForm');
$router->post('/spaces/{id}/games/{gid}/edit', GameController::class, 'update');
$router->post('/spaces/{id}/games/{gid}/delete', GameController::class, 'delete');
$router->post('/spaces/{id}/games/{gid}/status', GameController::class, 'updateStatus');
$router->post('/spaces/{id}/games/{gid}/comments', GameController::class, 'addComment');
$router->post('/spaces/{id}/games/{gid}/comments/{cid}/delete', GameController::class, 'deleteComment');

// ============================================================
// Manches
// ============================================================
$router->post('/spaces/{id}/games/{gid}/rounds/create', RoundController::class, 'create');
$router->post('/spaces/{id}/games/{gid}/rounds/{rid}/scores', RoundController::class, 'updateScores');
$router->post('/spaces/{id}/games/{gid}/rounds/{rid}/status', RoundController::class, 'updateStatus');
$router->post('/spaces/{id}/games/{gid}/rounds/{rid}/delete', RoundController::class, 'delete');

// ============================================================
// Statistiques
// ============================================================
$router->get('/spaces/{id}/stats', StatController::class, 'index');

// ============================================================
// Recherche
// ============================================================
$router->get('/spaces/{id}/search', SearchController::class, 'index');

// ============================================================
// Compétitions (vue espace — staff + membres)
// ============================================================
$router->get('/spaces/{id}/competitions', CompetitionController::class, 'index');
$router->get('/spaces/{id}/competitions/create', CompetitionController::class, 'createForm');
$router->post('/spaces/{id}/competitions/create', CompetitionController::class, 'create');
$router->get('/spaces/{id}/competitions/{cid}', CompetitionController::class, 'show');
$router->get('/spaces/{id}/competitions/{cid}/edit', CompetitionController::class, 'editForm');
$router->post('/spaces/{id}/competitions/{cid}/edit', CompetitionController::class, 'update');
$router->post('/spaces/{id}/competitions/{cid}/activate', CompetitionController::class, 'activate');
$router->post('/spaces/{id}/competitions/{cid}/pause', CompetitionController::class, 'pause');
$router->post('/spaces/{id}/competitions/{cid}/resume', CompetitionController::class, 'resume');
$router->post('/spaces/{id}/competitions/{cid}/close', CompetitionController::class, 'close');
$router->post('/spaces/{id}/competitions/{cid}/sessions/add', CompetitionController::class, 'addSession');
$router->post('/spaces/{id}/competitions/{cid}/sessions/{sid}/reset-password', CompetitionController::class, 'resetSessionPassword');
$router->post('/spaces/{id}/competitions/{cid}/sessions/{sid}/deactivate', CompetitionController::class, 'deactivateSession');
$router->post('/spaces/{id}/competitions/{cid}/sessions/{sid}/reactivate', CompetitionController::class, 'reactivateSession');
$router->post('/spaces/{id}/competitions/{cid}/delete', CompetitionController::class, 'delete');

// ============================================================
// Session de compétition (interface arbitre — sans auth utilisateur)
// ============================================================
$router->get('/competition/login', CompetitionSessionController::class, 'loginForm');
$router->post('/competition/login', CompetitionSessionController::class, 'login');
$router->get('/competition/logout', CompetitionSessionController::class, 'logout');
$router->get('/competition/dashboard', CompetitionSessionController::class, 'dashboard');
$router->post('/competition/games/create', CompetitionSessionController::class, 'createGame');
$router->get('/competition/games/{gid}', CompetitionSessionController::class, 'showGame');
$router->post('/competition/games/{gid}/rounds/create', CompetitionSessionController::class, 'createRound');
$router->post('/competition/games/{gid}/rounds/{rid}/scores', CompetitionSessionController::class, 'updateScores');
$router->post('/competition/games/{gid}/complete', CompetitionSessionController::class, 'completeGame');

// ============================================================
// Administration
// ============================================================
$router->get('/admin', AdminController::class, 'dashboard');
$router->get('/admin/users', AdminController::class, 'users');
$router->post('/admin/users/{uid}/role', AdminController::class, 'updateUserRole');
$router->post('/admin/users/{uid}/delete', AdminController::class, 'deleteUser');
$router->get('/admin/users/{uid}/restrictions', AdminController::class, 'userRestrictions');
$router->post('/admin/users/{uid}/restrictions', AdminController::class, 'updateUserRestrictions');
$router->get('/admin/spaces', AdminController::class, 'spaces');
$router->get('/admin/password-policy', AdminController::class, 'passwordPolicy');
$router->post('/admin/password-policy', AdminController::class, 'updatePasswordPolicy');
$router->get('/admin/fail2ban', AdminController::class, 'fail2ban');
$router->post('/admin/fail2ban', AdminController::class, 'updateFail2ban');
$router->get('/admin/leaderboard-criteria', AdminController::class, 'leaderboardCriteria');
$router->post('/admin/leaderboard-criteria', AdminController::class, 'updateLeaderboardCriteria');

// Bannissements comptes
$router->get('/admin/bans/users', AdminController::class, 'userBans');
$router->get('/admin/bans/users/search', AdminController::class, 'searchUsersApi');
$router->get('/admin/bans/users/create', AdminController::class, 'userBanForm');
$router->post('/admin/bans/users/create', AdminController::class, 'createUserBan');
$router->post('/admin/bans/users/{bid}/revoke', AdminController::class, 'revokeUserBan');

// Bannissements IP
$router->get('/admin/bans/ips', AdminController::class, 'ipBans');
$router->get('/admin/bans/ips/create', AdminController::class, 'ipBanForm');
$router->post('/admin/bans/ips/create', AdminController::class, 'createIpBan');
$router->post('/admin/bans/ips/{bid}/revoke', AdminController::class, 'revokeIpBan');

// Journal d'activité
$router->get('/admin/logs', AdminController::class, 'activityLogs');

// Restrictions d'espaces
$router->get('/admin/spaces/{id}/restrictions', AdminController::class, 'spaceRestrictions');
$router->post('/admin/spaces/{id}/restrictions', AdminController::class, 'updateSpaceRestrictions');

// Auto-destruction programmée d'espaces
$router->get('/admin/spaces/{id}/schedule-deletion', AdminController::class, 'scheduleDeletion');
$router->post('/admin/spaces/{id}/schedule-deletion', AdminController::class, 'updateScheduleDeletion');
$router->post('/admin/spaces/{id}/cancel-deletion', AdminController::class, 'cancelScheduledDeletion');

// ============================================================
// API REST Mobile
// ============================================================

// Auth
$router->post('/api/login', AuthApiController::class, 'login');
$router->post('/api/register', AuthApiController::class, 'register');
$router->get('/api/me', AuthApiController::class, 'me');

// Profil
$router->get('/api/profile', ProfileApiController::class, 'show');
$router->put('/api/profile', ProfileApiController::class, 'update');
$router->put('/api/profile/password', ProfileApiController::class, 'updatePassword');

// Espaces
$router->get('/api/spaces', SpaceApiController::class, 'index');
$router->post('/api/spaces', SpaceApiController::class, 'create');
$router->get('/api/spaces/{id}', SpaceApiController::class, 'show');
$router->put('/api/spaces/{id}', SpaceApiController::class, 'update');
$router->delete('/api/spaces/{id}', SpaceApiController::class, 'delete');
$router->post('/api/spaces/{id}/leave', SpaceApiController::class, 'leave');

// Membres
$router->get('/api/spaces/{id}/members', SpaceApiController::class, 'members');
$router->post('/api/spaces/{id}/members', SpaceApiController::class, 'addMember');
$router->put('/api/spaces/{id}/members/{mid}/role', SpaceApiController::class, 'updateMemberRole');
$router->delete('/api/spaces/{id}/members/{mid}', SpaceApiController::class, 'removeMember');

// Invitations
$router->post('/api/invitations/{invId}/accept', SpaceApiController::class, 'acceptInvitation');
$router->post('/api/invitations/{invId}/decline', SpaceApiController::class, 'declineInvitation');
$router->post('/api/spaces/join/{token}', SpaceApiController::class, 'join');

// Types de jeux
$router->get('/api/spaces/{id}/game-types', GameTypeApiController::class, 'index');
$router->get('/api/spaces/{id}/game-types/{gtid}', GameTypeApiController::class, 'show');
$router->post('/api/spaces/{id}/game-types', GameTypeApiController::class, 'create');
$router->put('/api/spaces/{id}/game-types/{gtid}', GameTypeApiController::class, 'update');
$router->delete('/api/spaces/{id}/game-types/{gtid}', GameTypeApiController::class, 'delete');

// Joueurs
$router->get('/api/spaces/{id}/players', PlayerApiController::class, 'index');
$router->get('/api/spaces/{id}/players/{pid}', PlayerApiController::class, 'show');
$router->post('/api/spaces/{id}/players', PlayerApiController::class, 'create');
$router->put('/api/spaces/{id}/players/{pid}', PlayerApiController::class, 'update');
$router->delete('/api/spaces/{id}/players/{pid}', PlayerApiController::class, 'delete');

// Parties
$router->get('/api/spaces/{id}/games', GameApiController::class, 'index');
$router->get('/api/spaces/{id}/games/{gid}', GameApiController::class, 'show');
$router->post('/api/spaces/{id}/games', GameApiController::class, 'create');
$router->put('/api/spaces/{id}/games/{gid}', GameApiController::class, 'update');
$router->delete('/api/spaces/{id}/games/{gid}', GameApiController::class, 'delete');
$router->put('/api/spaces/{id}/games/{gid}/status', GameApiController::class, 'updateStatus');

// Commentaires
$router->post('/api/spaces/{id}/games/{gid}/comments', GameApiController::class, 'addComment');
$router->delete('/api/spaces/{id}/games/{gid}/comments/{cid}', GameApiController::class, 'deleteComment');

// Manches
$router->post('/api/spaces/{id}/games/{gid}/rounds', GameApiController::class, 'createRound');
$router->put('/api/spaces/{id}/games/{gid}/rounds/{rid}/scores', GameApiController::class, 'updateScores');
$router->put('/api/spaces/{id}/games/{gid}/rounds/{rid}/status', GameApiController::class, 'updateRoundStatus');
$router->delete('/api/spaces/{id}/games/{gid}/rounds/{rid}', GameApiController::class, 'deleteRound');

// Statistiques
$router->get('/api/spaces/{id}/stats', StatApiController::class, 'index');

// Recherche
$router->get('/api/spaces/{id}/search', SearchApiController::class, 'index');

// Dispatcher la requête
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
