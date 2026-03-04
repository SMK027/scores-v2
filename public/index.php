<?php

declare(strict_types=1);

/**
 * Point d'entrée de l'application (Front Controller).
 * Toutes les requêtes HTTP passent par ce fichier.
 */

// Charger l'autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

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

// Démarrer la session
Session::start();

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
// Administration
// ============================================================
$router->get('/admin', AdminController::class, 'dashboard');
$router->get('/admin/users', AdminController::class, 'users');
$router->post('/admin/users/{uid}/role', AdminController::class, 'updateUserRole');
$router->post('/admin/users/{uid}/delete', AdminController::class, 'deleteUser');
$router->get('/admin/spaces', AdminController::class, 'spaces');

// Dispatcher la requête
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
