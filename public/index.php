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
use App\Models\IpBan;
use App\Models\UserBan;

// Démarrer la session
Session::start();

// ============================================================
// Vérification globale de bannissement IP
// ============================================================
$clientIp = get_client_ip();
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

// ============================================================
// Vérification bannissement du compte (utilisateur connecté)
// ============================================================
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

// Initialiser le routeur
$router = new Router();

// ============================================================
// Routes publiques
// ============================================================
$router->get('/', HomeController::class, 'index');
$router->get('/login', AuthController::class, 'loginForm');
$router->post('/login', AuthController::class, 'login');
$router->get('/register', AuthController::class, 'registerForm');
$router->post('/register', AuthController::class, 'register');
$router->get('/logout', AuthController::class, 'logout');
$router->get('/forgot-password', AuthController::class, 'forgotPasswordForm');
$router->post('/forgot-password', AuthController::class, 'forgotPassword');
$router->get('/reset-password/{token}', AuthController::class, 'resetPasswordForm');
$router->post('/reset-password/{token}', AuthController::class, 'resetPassword');

// ============================================================
// Profil utilisateur
// ============================================================
$router->get('/profile', ProfileController::class, 'show');
$router->get('/profile/edit', ProfileController::class, 'editForm');
$router->post('/profile/edit', ProfileController::class, 'update');

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
$router->get('/spaces/{id}/members', SpaceController::class, 'members');
$router->post('/spaces/{id}/members/add', SpaceController::class, 'addMember');
$router->post('/spaces/{id}/members/{mid}/role', SpaceController::class, 'updateMemberRole');
$router->post('/spaces/{id}/members/{mid}/remove', SpaceController::class, 'removeMember');
$router->post('/spaces/{id}/invite', SpaceController::class, 'invite');
$router->post('/spaces/{id}/invite/{iid}/revoke', SpaceController::class, 'revokeInvite');

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
$router->get('/admin/spaces', AdminController::class, 'spaces');
$router->get('/admin/password-policy', AdminController::class, 'passwordPolicy');
$router->post('/admin/password-policy', AdminController::class, 'updatePasswordPolicy');
$router->get('/admin/fail2ban', AdminController::class, 'fail2ban');
$router->post('/admin/fail2ban', AdminController::class, 'updateFail2ban');

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

// Dispatcher la requête
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
