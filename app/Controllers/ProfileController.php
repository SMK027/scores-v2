<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Mailer;
use App\Models\User;
use App\Models\PasswordPolicy;
use App\Models\ActivityLog;
use App\Models\RememberToken;
use App\Config\Database;

/**
 * Contrôleur de profil utilisateur.
 */
class ProfileController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Affiche le profil de l'utilisateur connecté.
     */
    public function show(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $user   = $this->userModel->find($userId);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/');
        }

        $pdo = Database::getInstance()->getConnection();

        $this->render('profile/show', [
            'title'    => 'Mon profil',
            'user'     => $user,
            'winStats' => $this->computeGlobalWinRate($userId, $pdo),
        ]);
    }

    /**
     * Affiche le calendrier de l'utilisateur connecté avec historique filtrable.
     */
    public function calendar(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $pdo = Database::getInstance()->getConnection();

        $filters = [
            'space_id'     => isset($_GET['space_id']) && ctype_digit((string) $_GET['space_id']) ? (int) $_GET['space_id'] : null,
            'status'       => $_GET['status'] ?? '',
            'game_type_id' => isset($_GET['game_type_id']) && ctype_digit((string) $_GET['game_type_id']) ? (int) $_GET['game_type_id'] : null,
            'period'       => $_GET['period'] ?? '30d',
            'from'         => $_GET['from'] ?? '',
            'to'           => $_GET['to'] ?? '',
            'month'        => $_GET['month'] ?? date('Y-m'),
        ];

        $allowedStatuses = ['', 'pending', 'in_progress', 'paused', 'completed'];
        if (!in_array($filters['status'], $allowedStatuses, true)) {
            $filters['status'] = '';
        }

        $allowedPeriods = ['7d', '30d', '90d', '365d', 'custom', 'all'];
        if (!in_array($filters['period'], $allowedPeriods, true)) {
            $filters['period'] = '30d';
        }

        if (!preg_match('/^\d{4}-\d{2}$/', (string) $filters['month'])) {
            $filters['month'] = date('Y-m');
        }

        if (!$this->validateDateYmd($filters['from'])) {
            $filters['from'] = '';
        }
        if (!$this->validateDateYmd($filters['to'])) {
            $filters['to'] = '';
        }

        $spaces = $this->getUserSpaces($userId, $pdo);
        $spaceIds = array_map(static fn(array $s): int => (int) $s['id'], $spaces);

        if ($filters['space_id'] !== null && !in_array($filters['space_id'], $spaceIds, true)) {
            $filters['space_id'] = null;
        }

        $gameTypes = $this->getAvailableGameTypes($userId, $pdo, $filters['space_id']);
        $gameTypeIds = array_map(static fn(array $gt): int => (int) $gt['id'], $gameTypes);
        if ($filters['game_type_id'] !== null && !in_array($filters['game_type_id'], $gameTypeIds, true)) {
            $filters['game_type_id'] = null;
        }

        $page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max((int) $_GET['page'], 1) : 1;
        $perPage = 15;
        $history = $this->getUserGameHistory($userId, $pdo, $filters, $page, $perPage);
        $calendarDays = $this->getMonthActivity($userId, $pdo, $filters);

        $this->render('profile/calendar', [
            'title'      => 'Mon calendrier',
            'filters'    => $filters,
            'spaces'     => $spaces,
            'gameTypes'  => $gameTypes,
            'history'    => $history,
            'activeMenu' => 'profile-calendar',
        ]);
    }

    /**
     * Point d'entrée JSON pour FullCalendar — renvoie les parties de l'utilisateur
     * dans la plage de dates demandée par le widget.
     */
    public function calendarEvents(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $pdo    = Database::getInstance()->getConnection();

        // FullCalendar envoie start/end en ISO8601 (ex: 2026-03-01T00:00:00)
        $rawStart = $_GET['start'] ?? '';
        $rawEnd   = $_GET['end']   ?? '';

        $dateStart = preg_match('/^\d{4}-\d{2}-\d{2}/', $rawStart)
            ? substr($rawStart, 0, 10) . ' 00:00:00' : null;
        $dateEnd   = preg_match('/^\d{4}-\d{2}-\d{2}/', $rawEnd)
            ? substr($rawEnd, 0, 10) . ' 23:59:59' : null;

        // Filtres optionnels transmis depuis le formulaire de la page
        $spaceId = isset($_GET['space_id']) && ctype_digit((string) $_GET['space_id']) && (int) $_GET['space_id'] > 0
            ? (int) $_GET['space_id'] : null;
        $status  = in_array($_GET['status'] ?? '', ['', 'pending', 'in_progress', 'paused', 'completed'], true)
            ? ($_GET['status'] ?? '') : '';
        $gameTypeId = isset($_GET['game_type_id']) && ctype_digit((string) $_GET['game_type_id']) && (int) $_GET['game_type_id'] > 0
            ? (int) $_GET['game_type_id'] : null;

        // Vérification que l'espace demandé appartient bien à l'utilisateur
        if ($spaceId !== null) {
            $userSpaceIds = array_column($this->getUserSpaces($userId, $pdo), 'id');
            if (!in_array($spaceId, $userSpaceIds, true)) {
                $spaceId = null;
            }
        }

        $where  = ['sm.user_id = :uid_member', 'p_me.user_id = :uid_player'];
        $params = ['uid_member' => $userId, 'uid_player' => $userId];

        if ($spaceId !== null) {
            $where[]           = 'g.space_id = :space_id';
            $params['space_id'] = $spaceId;
        }
        if (!empty($status)) {
            $where[]         = 'g.status = :status';
            $params['status'] = $status;
        }
        if ($gameTypeId !== null) {
            $where[]               = 'g.game_type_id = :game_type_id';
            $params['game_type_id'] = $gameTypeId;
        }
        if ($dateStart !== null) {
            $where[]              = 'COALESCE(g.started_at, g.created_at) >= :date_start';
            $params['date_start'] = $dateStart;
        }
        if ($dateEnd !== null) {
            $where[]            = 'COALESCE(g.started_at, g.created_at) <= :date_end';
            $params['date_end'] = $dateEnd;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT
                    g.id, g.space_id, g.status,
                    g.started_at, g.ended_at, g.created_at,
                    s.name   AS space_name,
                    gt.name  AS game_type_name,
                    gp_me.is_winner AS my_is_winner,
                    gp_me.rank      AS my_rank,
                    (SELECT COUNT(*) FROM game_players gp_c WHERE gp_c.game_id = g.id) AS player_count
                FROM games g
                INNER JOIN spaces s          ON s.id  = g.space_id
                INNER JOIN space_members sm  ON sm.space_id = s.id
                INNER JOIN game_types gt     ON gt.id = g.game_type_id
                INNER JOIN game_players gp_me ON gp_me.game_id = g.id
                INNER JOIN players p_me      ON p_me.id = gp_me.player_id
                WHERE {$whereSql}
                GROUP BY g.id
                ORDER BY COALESCE(g.started_at, g.created_at) ASC
                LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $statusLabels = [
            'completed'   => 'Terminée',
            'in_progress' => 'En cours',
            'paused'      => 'En pause',
            'pending'     => 'En attente',
        ];

        $events = [];
        foreach ($rows as $g) {
            $startDt  = $g['started_at'] ?: $g['created_at'];
            $endDt    = $g['ended_at']   ?: null;
            $isWinner = (int) ($g['my_is_winner'] ?? 0) === 1;

            $title = $g['game_type_name'];
            if ($g['status'] === 'completed') {
                $title .= $isWinner ? ' 🏆' : ' ✗';
            } elseif ($g['status'] !== 'pending') {
                $title .= ' ⏳';
            }

            $cssClass = match ($g['status']) {
                'completed'   => $isWinner ? 'fc-ev-win' : 'fc-ev-loss',
                'in_progress' => 'fc-ev-ongoing',
                'paused'      => 'fc-ev-paused',
                default       => 'fc-ev-pending',
            };

            $events[] = [
                'id'         => (string) $g['id'],
                'title'      => $title,
                'start'      => $startDt,
                'end'        => $endDt,
                'url'        => '/spaces/' . $g['space_id'] . '/games/' . $g['id'],
                'classNames' => [$cssClass],
                'extendedProps' => [
                    'entry_type'   => 'game',
                    'space'        => $g['space_name'],
                    'game_type'    => $g['game_type_name'],
                    'status'       => $statusLabels[$g['status']] ?? $g['status'],
                    'is_winner'    => $isWinner,
                    'player_count' => (int) $g['player_count'],
                    'rank'         => $g['my_rank'],
                ],
            ];
        }

        $competitionWhere = ['sm.user_id = :uid_member'];
        $competitionParams = ['uid_member' => $userId, 'uid_referee' => $userId, 'uid_player' => $userId];

        if ($spaceId !== null) {
            $competitionWhere[] = 'c.space_id = :space_id';
            $competitionParams['space_id'] = $spaceId;
        }
        if ($dateStart !== null) {
            $competitionWhere[] = 'c.ends_at >= :date_start';
            $competitionParams['date_start'] = $dateStart;
        }
        if ($dateEnd !== null) {
            $competitionWhere[] = 'c.starts_at <= :date_end';
            $competitionParams['date_end'] = $dateEnd;
        }

        $competitionWhereSql = implode(' AND ', $competitionWhere);
        $competitionSql = "SELECT
                    c.id,
                    c.space_id,
                    c.name,
                    c.status,
                    c.starts_at,
                    c.ends_at,
                    s.name AS space_name,
                                        MAX(CASE WHEN p_me.id IS NOT NULL THEN 1 ELSE 0 END) AS as_player,
                    MAX(CASE WHEN cs.id IS NOT NULL THEN 1 ELSE 0 END) AS as_referee
                FROM competitions c
                INNER JOIN spaces s ON s.id = c.space_id
                INNER JOIN space_members sm ON sm.space_id = c.space_id
                                LEFT JOIN games g_cp ON g_cp.competition_id = c.id
                                LEFT JOIN game_players gp_me ON gp_me.game_id = g_cp.id
                                LEFT JOIN players p_me
                                        ON p_me.id = gp_me.player_id
                                        AND p_me.user_id = :uid_player
                LEFT JOIN competition_sessions cs
                    ON cs.competition_id = c.id
                    AND cs.referee_user_id = :uid_referee
                WHERE {$competitionWhereSql}
                                    AND (p_me.id IS NOT NULL OR cs.id IS NOT NULL)
                GROUP BY c.id
                ORDER BY c.starts_at ASC
                LIMIT 1000";

        $competitionStmt = $pdo->prepare($competitionSql);
        $competitionStmt->execute($competitionParams);
        $competitionRows = $competitionStmt->fetchAll(\PDO::FETCH_ASSOC);

        $competitionStatusLabels = [
            'planned' => 'Planifiée',
            'active'  => 'Active',
            'paused'  => 'En pause',
            'closed'  => 'Clôturée',
        ];

        foreach ($competitionRows as $c) {
            $asPlayer = (int) ($c['as_player'] ?? 0) === 1;
            $asReferee = (int) ($c['as_referee'] ?? 0) === 1;

            $roles = [];
            if ($asPlayer) {
                $roles[] = 'joueur';
            }
            if ($asReferee) {
                $roles[] = 'arbitre';
            }
            if (empty($roles)) {
                continue;
            }

            $roleLabel = implode(' + ', $roles);
            $cssClass = ($asPlayer && $asReferee)
                ? 'fc-ev-comp-both'
                : ($asReferee ? 'fc-ev-comp-referee' : 'fc-ev-comp-player');

            $events[] = [
                'id'    => 'competition-' . (int) $c['id'] . '-' . str_replace(' ', '-', $roleLabel),
                'title' => 'Competition: ' . $c['name'] . ' (' . $roleLabel . ')',
                'start' => $c['starts_at'],
                'end'   => $c['ends_at'],
                'url'   => '/spaces/' . $c['space_id'] . '/competitions/' . $c['id'],
                'classNames' => [$cssClass],
                'extendedProps' => [
                    'entry_type' => 'competition',
                    'space'      => $c['space_name'],
                    'status'     => $competitionStatusLabels[$c['status']] ?? $c['status'],
                    'role'       => $roleLabel,
                ],
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($events, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Formulaire de modification du profil.
     */
    public function editForm(): void
    {
        $this->requireAuth();

        $user = $this->userModel->find($this->getCurrentUserId());
        $policyModel = new PasswordPolicy();

        $this->render('profile/edit', [
            'title'         => 'Modifier mon profil',
            'user'          => $user,
            'policySummary' => $policyModel->getSummary(),
            'policyJson'    => $policyModel->toJson(),
        ]);
    }

    /**
     * Traite la modification du profil.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data = $this->getPostData(['username', 'email', 'bio', 'current_password', 'new_password', 'new_password_confirm']);
        // Checkbox : présente = 1, absente = 0
        $data['show_win_rate_public'] = isset($_POST['show_win_rate_public']) ? 1 : 0;

        $errors = [];
        $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';

        // Validation username
        if (empty($data['username'])) {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } elseif (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
            $errors[] = 'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères.';
        } else {
            $existing = $this->userModel->findByUsername($data['username']);
            if ($existing && $existing['id'] !== $userId) {
                $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
            }
        }

        // Validation email
        if (empty($data['email'])) {
            $errors[] = 'L\'email est requis.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        } else {
            $existing = $this->userModel->findByEmail($data['email']);
            if ($existing && $existing['id'] !== $userId) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }
        }

        // Gestion de l'avatar
        $avatarPath = null;
        if ($removeAvatar || (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK)) {
            $this->checkUserRestriction('profile_photo_manage', null, '/profile/edit');
        }

        if ($removeAvatar) {
            $avatarPath = '__REMOVE__';
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 Mo

            if (!in_array($file['type'], $allowedTypes, true)) {
                $errors[] = 'Le format d\'image n\'est pas supporté (JPG, PNG, GIF, WebP).';
            }
            if ($file['size'] > $maxSize) {
                $errors[] = 'L\'image ne doit pas dépasser 5 Mo.';
            }

            if (empty($errors)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                
                // Utiliser le chemin absolu depuis la racine du projet
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
                
                // Créer le dossier si nécessaire et forcer les permissions
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                @chmod($uploadDir, 0777);

                $targetPath = $uploadDir . $filename;
                
                // Tenter le move avec suppression d'erreur pour voir si ça passe
                $moved = @move_uploaded_file($file['tmp_name'], $targetPath);
                
                if ($moved) {
                    @chmod($targetPath, 0644);
                    $avatarPath = '/uploads/' . $filename;
                } else {
                    // Si ça échoue toujours, essayer avec copy + unlink
                    if (@copy($file['tmp_name'], $targetPath)) {
                        @unlink($file['tmp_name']);
                        @chmod($targetPath, 0644);
                        $avatarPath = '/uploads/' . $filename;
                    } else {
                        $errors[] = 'Erreur lors du téléchargement de l\'avatar.';
                    }
                }
            }
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Erreur d'upload
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
                UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale du formulaire.',
                UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement téléchargé.',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'enregistrer le fichier sur le disque.',
                UPLOAD_ERR_EXTENSION  => 'Une extension PHP a bloqué l\'upload.',
            ];
            $errors[] = $uploadErrors[$_FILES['avatar']['error']] ?? 'Erreur inconnue lors de l\'upload.';
        }

        // Changement de mot de passe
        if (!empty($data['new_password'])) {
            if (empty($data['current_password'])) {
                $errors[] = 'Le mot de passe actuel est requis pour changer de mot de passe.';
            } else {
                $user = $this->userModel->find($userId);
                if (!password_verify($data['current_password'], $user['password_hash'])) {
                    $errors[] = 'Le mot de passe actuel est incorrect.';
                }
            }

            // Valider contre la politique de mot de passe
            $policyModel = new PasswordPolicy();
            $policyErrors = $policyModel->validate($data['new_password']);
            $errors = array_merge($errors, $policyErrors);

            if ($data['new_password'] !== $data['new_password_confirm']) {
                $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect('/profile/edit');
        }

        // Mise à jour du profil
        $updateData = [
            'username'             => $data['username'],
            'email'                => $data['email'],
            'bio'                  => $data['bio'],
            'show_win_rate_public' => $data['show_win_rate_public'],
        ];

        if ($avatarPath === '__REMOVE__') {
            $updateData['avatar'] = null;
        } elseif ($avatarPath) {
            $updateData['avatar'] = $avatarPath;
        }

        $this->userModel->updateProfile($userId, $updateData);

        ActivityLog::logAuth('profile.update', $userId);

        // Mise à jour du mot de passe si demandé
        if (!empty($data['new_password'])) {
            $this->userModel->updatePassword($userId, $data['new_password']);
            ActivityLog::logAuth('password.change', $userId);
        }

        // Mettre à jour la session
        Session::set('username', $data['username']);
        if ($avatarPath === '__REMOVE__') {
            Session::set('avatar', '');
        } elseif ($avatarPath) {
            Session::set('avatar', $avatarPath);
        }

        $this->setFlash('success', 'Profil mis à jour avec succès.');
        $this->redirect('/profile');
    }

    /**
     * Démarre une demande de suppression de compte (grâce de 15 jours).
     */
    public function requestDeletion(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $currentPassword = trim((string) ($_POST['current_password_delete'] ?? ''));
        $confirm = (string) ($_POST['confirm_delete_account'] ?? '0');

        if ($confirm !== '1') {
            $this->setFlash('danger', 'Vous devez confirmer la demande de suppression de compte.');
            $this->redirect('/profile/edit');
        }

        if ($currentPassword === '') {
            $this->setFlash('danger', 'Le mot de passe actuel est requis pour supprimer le compte.');
            $this->redirect('/profile/edit');
        }

        $user = $this->userModel->find((int) $userId);
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/profile/edit');
        }

        $status = (string) ($user['account_status'] ?? User::ACCOUNT_STATUS_ACTIVE);
        if ($status !== User::ACCOUNT_STATUS_ACTIVE) {
            $this->setFlash('warning', 'Une demande de suppression est déjà en cours ou le compte est suspendu.');
            $this->redirect('/login');
        }

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            $this->setFlash('danger', 'Mot de passe actuel incorrect.');
            $this->redirect('/profile/edit');
        }

        $effectiveAt = $this->userModel->requestDeletion((int) $userId);
        if ($effectiveAt === null) {
            $this->setFlash('danger', 'Impossible de planifier la suppression du compte.');
            $this->redirect('/profile/edit');
        }

        ActivityLog::logAuth('account.deletion.requested', (int) $userId, [
            'effective_at' => $effectiveAt,
        ]);

        // Révoquer toutes les sessions persistantes immédiatement.
        (new RememberToken())->deleteByUser((int) $userId);

        try {
            $mailer = new Mailer();
            $mailer->send(
                (string) $user['email'],
                'Scores — Demande de suppression de compte enregistrée',
                $this->buildAccountDeletionRequestedEmail((string) $user['username'], (string) $effectiveAt)
            );
        } catch (\RuntimeException $e) {
            if (getenv('APP_DEBUG') === 'true') {
                error_log('Account deletion request mail error: ' . $e->getMessage());
            }
        }

        Session::destroy();
        Session::start();
        $this->setFlash(
            'info',
            'Votre demande de suppression a été enregistrée. Le compte est désactivé pendant 15 jours. '
            . 'Vous recevrez un email de confirmation une fois l\'anonymisation effective.'
        );
        $this->redirect('/login');
    }

    /**
     * Calcule le taux de victoire global d'un utilisateur, tous espaces confondus.
     * Une manche est comptée comme gagnée si le joueur a le meilleur score
     * selon la win_condition du type de jeu (ex-aequo inclus).
     * Seules les manches avec status = 'completed' sont prises en compte.
     */
    private function computeGlobalWinRate(int $userId, \PDO $pdo): ?array
    {
        // 1. Joueurs liés à l'utilisateur dans ses espaces
        $stmt = $pdo->prepare("
            SELECT p.id AS player_id, p.space_id, s.name AS space_name
            FROM players p
            JOIN spaces s ON s.id = p.space_id
            JOIN space_members sm ON sm.space_id = p.space_id AND sm.user_id = :member_user_id
            WHERE p.user_id = :player_user_id
        ");
        $stmt->execute([
            'member_user_id' => $userId,
            'player_user_id' => $userId,
        ]);
        $playerRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($playerRows)) {
            return null;
    }

        $playerIdToSpace = [];
        $spaceNames = [];
        foreach ($playerRows as $row) {
            $playerIdToSpace[(int) $row['player_id']] = (int) $row['space_id'];
            $spaceNames[(int) $row['space_id']] = $row['space_name'];
        }
        $playerIds = array_keys($playerIdToSpace);

        // 2. Manches terminées où ces joueurs ont un score
        $ph = implode(',', array_fill(0, count($playerIds), '?'));
        $stmt = $pdo->prepare("
            SELECT DISTINCT r.id AS round_id, gt.win_condition
            FROM round_scores rs
            JOIN rounds r ON r.id = rs.round_id AND r.status = 'completed'
            JOIN games g ON g.id = r.game_id
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE rs.player_id IN ($ph)
        ");
        $stmt->execute($playerIds);
        $rounds = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rounds)) {
            return null;
        }

        $roundIds = array_column($rounds, 'round_id');

        // 3. Tous les scores de ces manches en une seule requête
        $rph = implode(',', array_fill(0, count($roundIds), '?'));
        $stmt = $pdo->prepare("
            SELECT round_id, player_id, score
            FROM round_scores
            WHERE round_id IN ($rph)
        ");
        $stmt->execute($roundIds);
        $allScores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $scoresByRound = [];
        foreach ($allScores as $s) {
            $scoresByRound[(int) $s['round_id']][] = $s;
        }

        // 4. Comptabilisation par manche
        $spaceStats  = [];
        $totalPlayed = 0;
        $totalWon    = 0;

        foreach ($rounds as $round) {
            $roundId      = (int) $round['round_id'];
            $winCondition = $round['win_condition'];
            $scores       = $scoresByRound[$roundId] ?? [];

            if (empty($scores)) {
                continue;
            }

            $vals = array_map(fn($s) => (float) $s['score'], $scores);
            $best = ($winCondition === 'lowest_score' || $winCondition === 'ranking')
                ? min($vals)
                : max($vals);

            foreach ($scores as $s) {
                $pid = (int) $s['player_id'];
                if (!isset($playerIdToSpace[$pid])) {
                    continue;
                }
                $sid = $playerIdToSpace[$pid];

                $spaceStats[$sid] = $spaceStats[$sid] ?? ['played' => 0, 'won' => 0];
                $spaceStats[$sid]['played']++;
                $totalPlayed++;

                if ((float) $s['score'] === $best) {
                    $spaceStats[$sid]['won']++;
                    $totalWon++;
                }
            }
        }

        if ($totalPlayed === 0) {
            return null;
        }

        // 5. Détail par espace (espaces sans manche inclus)
        $breakdown = [];
        foreach ($spaceNames as $sid => $name) {
            $st = $spaceStats[$sid] ?? ['played' => 0, 'won' => 0];
            $breakdown[] = [
                'space_name' => $name,
                'played'     => $st['played'],
                'won'        => $st['won'],
                'rate'       => $st['played'] > 0
                    ? round($st['won'] * 100.0 / $st['played'], 1)
                    : null,
            ];
        }
        usort($breakdown, fn($a, $b) => $b['played'] <=> $a['played']);

        return [
            'rounds_played' => $totalPlayed,
            'rounds_won'    => $totalWon,
            'win_rate'      => round($totalWon * 100.0 / $totalPlayed, 2),
            'breakdown'     => $breakdown,
        ];
    }

    /**
     * Retourne la liste des espaces du user connecté.
     */
    private function getUserSpaces(int $userId, \PDO $pdo): array
    {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.name
             FROM spaces s
             INNER JOIN space_members sm ON sm.space_id = s.id
             WHERE sm.user_id = :user_id
             ORDER BY s.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les types de jeux disponibles pour le user (et éventuellement un espace).
     */
    private function getAvailableGameTypes(int $userId, \PDO $pdo, ?int $spaceId): array
    {
        $sql = 'SELECT gt.id, gt.name, gt.space_id, COALESCE(s.name, \'Global\') AS space_name
                FROM game_types gt
                LEFT JOIN spaces s ON s.id = gt.space_id
                LEFT JOIN space_members sm ON sm.space_id = s.id AND sm.user_id = :user_id
                WHERE (sm.user_id IS NOT NULL OR gt.is_global = 1)';
        $params = ['user_id' => $userId];

        if ($spaceId !== null) {
            $sql .= ' AND (gt.space_id = :space_id OR gt.is_global = 1)';
            $params['space_id'] = $spaceId;
        }

        $sql .= ' ORDER BY gt.is_global DESC, s.name ASC, gt.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Historique paginé des parties du joueur connecté.
     */
    private function getUserGameHistory(int $userId, \PDO $pdo, array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $where = [
            'sm.user_id = :user_id_member',
            'p_me.user_id = :user_id_player',
        ];
        $params = [
            'user_id_member' => $userId,
            'user_id_player' => $userId,
        ];

        if ($filters['space_id'] !== null) {
            $where[] = 'g.space_id = :space_id';
            $params['space_id'] = $filters['space_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'g.status = :status';
            $params['status'] = $filters['status'];
        }
        if ($filters['game_type_id'] !== null) {
            $where[] = 'g.game_type_id = :game_type_id';
            $params['game_type_id'] = $filters['game_type_id'];
        }

        $this->applyPeriodFilter($where, $params, $filters);

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(DISTINCT g.id)
                     FROM games g
                     INNER JOIN spaces s ON s.id = g.space_id
                     INNER JOIN space_members sm ON sm.space_id = s.id
                     INNER JOIN game_types gt ON gt.id = g.game_type_id
                     INNER JOIN game_players gp_me ON gp_me.game_id = g.id
                     INNER JOIN players p_me ON p_me.id = gp_me.player_id
                     WHERE {$whereSql}";

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataSql = "SELECT
                        g.id,
                        g.space_id,
                        g.status,
                        g.created_at,
                        g.started_at,
                        g.ended_at,
                        s.name AS space_name,
                        gt.name AS game_type_name,
                        u.username AS creator_name,
                        gp_me.total_score AS my_total_score,
                        gp_me.rank AS my_rank,
                        gp_me.is_winner AS my_is_winner,
                        p_me.name AS my_player_name,
                        (SELECT COUNT(*) FROM game_players gp_cnt WHERE gp_cnt.game_id = g.id) AS player_count,
                        (SELECT GROUP_CONCAT(pw.name SEPARATOR ', ')
                         FROM game_players gpw
                         INNER JOIN players pw ON pw.id = gpw.player_id
                         WHERE gpw.game_id = g.id AND gpw.is_winner = 1) AS winner_names
                    FROM games g
                    INNER JOIN spaces s ON s.id = g.space_id
                    INNER JOIN space_members sm ON sm.space_id = s.id
                    INNER JOIN game_types gt ON gt.id = g.game_type_id
                    LEFT JOIN users u ON u.id = g.created_by
                    INNER JOIN game_players gp_me ON gp_me.game_id = g.id
                    INNER JOIN players p_me ON p_me.id = gp_me.player_id
                    WHERE {$whereSql}
                    GROUP BY g.id
                    ORDER BY COALESCE(g.started_at, g.created_at) DESC, g.id DESC
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $pdo->prepare($dataSql);
        foreach ($params as $key => $value) {
            $dataStmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $dataStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'data'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Données agrégées par jour pour la vue calendrier mensuelle.
     */
    private function getMonthActivity(int $userId, \PDO $pdo, array $filters): array
    {
        $where = [
            'sm.user_id = :user_id_member',
            'p_me.user_id = :user_id_player',
        ];
        $params = [
            'user_id_member' => $userId,
            'user_id_player' => $userId,
        ];

        if ($filters['space_id'] !== null) {
            $where[] = 'g.space_id = :space_id';
            $params['space_id'] = $filters['space_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'g.status = :status';
            $params['status'] = $filters['status'];
        }
        if ($filters['game_type_id'] !== null) {
            $where[] = 'g.game_type_id = :game_type_id';
            $params['game_type_id'] = $filters['game_type_id'];
        }

        // Mois affiché sur la grille calendrier.
        $monthStart = $filters['month'] . '-01 00:00:00';
        $monthEnd = date('Y-m-t 23:59:59', strtotime($monthStart));
        $where[] = 'COALESCE(g.started_at, g.created_at) BETWEEN :month_start AND :month_end';
        $params['month_start'] = $monthStart;
        $params['month_end'] = $monthEnd;

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT
                    DATE(COALESCE(g.started_at, g.created_at)) AS day,
                    COUNT(DISTINCT g.id) AS game_count,
                    COUNT(DISTINCT CASE WHEN gp_me.is_winner = 1 THEN g.id END) AS win_count
                FROM games g
                INNER JOIN spaces s ON s.id = g.space_id
                INNER JOIN space_members sm ON sm.space_id = s.id
                INNER JOIN game_players gp_me ON gp_me.game_id = g.id
                INNER JOIN players p_me ON p_me.id = gp_me.player_id
                WHERE {$whereSql}
                GROUP BY DATE(COALESCE(g.started_at, g.created_at))
                ORDER BY day ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['day']] = [
                'game_count' => (int) $row['game_count'],
                'win_count'  => (int) $row['win_count'],
            ];
        }

        return $mapped;
    }

    /**
     * Applique le filtre temporel à la requête d'historique.
     */
    private function applyPeriodFilter(array &$where, array &$params, array $filters): void
    {
        $period = $filters['period'] ?? '30d';

        if ($period === 'custom') {
            if (!empty($filters['from'])) {
                $where[] = 'DATE(COALESCE(g.started_at, g.created_at)) >= :date_from';
                $params['date_from'] = $filters['from'];
            }
            if (!empty($filters['to'])) {
                $where[] = 'DATE(COALESCE(g.started_at, g.created_at)) <= :date_to';
                $params['date_to'] = $filters['to'];
            }
            return;
        }

        if ($period === 'all') {
            return;
        }

        $daysByPeriod = [
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '365d' => 365,
        ];

        if (isset($daysByPeriod[$period])) {
            $periodFrom = date('Y-m-d H:i:s', strtotime('-' . $daysByPeriod[$period] . ' days'));
            $where[] = 'COALESCE(g.started_at, g.created_at) >= :period_from';
            $params['period_from'] = $periodFrom;
        }
    }

    /**
     * Validation simple du format date (YYYY-MM-DD).
     */
    private function validateDateYmd(string $value): bool
    {
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    /**
     * Affiche le profil public d'un utilisateur (accessible à tous).
     */
    public function showPublic(string $username): void
    {
        $user = $this->userModel->findByUsername($username);
        $isHidden = !$user
            || ((string) ($user['account_status'] ?? User::ACCOUNT_STATUS_ACTIVE) !== User::ACCOUNT_STATUS_ACTIVE)
            || !empty($user['is_anonymized']);

        if ($isHidden) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/leaderboard');
        }

        $pdo = Database::getInstance()->getConnection();

        $this->render('profile/show_public', [
            'title'    => 'Profil de ' . $user['username'],
            'user'     => $user,
            'winStats' => $this->computeGlobalWinRate((int) $user['id'], $pdo),
        ]);
    }

    /**
     * Email envoyé lors de la demande de suppression de compte.
     */
    private function buildAccountDeletionRequestedEmail(string $username, string $effectiveAt): string
    {
        $effectiveDate = date('d/m/Y à H:i', strtotime($effectiveAt));
        $appName = 'Scores';

        return '<p>Bonjour ' . e($username) . ',</p>'
            . '<p>Votre demande de suppression de compte a bien été enregistrée.</p>'
            . '<p>Votre compte est désormais désactivé pendant 15 jours. '
            . 'La suppression effective (anonymisation) est prévue le <strong>' . e($effectiveDate) . '</strong>.</p>'
            . '<p>Conformément au droit à l\'oubli, vos données personnelles seront anonymisées '
            . 'tout en conservant les données de jeu nécessaires aux statistiques agrégées.</p>'
            . '<p>L\'équipe ' . e($appName) . '</p>';
    }
}
