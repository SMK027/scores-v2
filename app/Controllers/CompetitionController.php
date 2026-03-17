<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Middleware;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\Space;
use App\Models\User;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\GameType;
use App\Models\Round;
use App\Models\RoundScore;
use App\Config\Database;
use App\Models\ActivityLog;

/**
 * Contrôleur des compétitions.
 * Accessible uniquement aux modérateurs, admins et super-admins globaux.
 */
class CompetitionController extends Controller
{
    private Competition $competition;
    private CompetitionSession $session;
    private Space $spaceModel;
    private Player $player;
    private \PDO $pdo;

    public function __construct()
    {
        $this->competition = new Competition();
        $this->session     = new CompetitionSession();
        $this->spaceModel  = new Space();
        $this->player      = new Player();
        $this->pdo         = Database::getInstance()->getConnection();
    }

    /**
     * Vérifie que l'utilisateur est staff global (moderator, admin ou superadmin).
     */
    private function requireStaff(): void
    {
        $this->requireGlobalRole(['moderator', 'admin', 'superadmin']);
        $this->checkUserRestriction('competitions_participation', null, '/spaces');
    }

    /**
     * Liste des compétitions d'un espace.
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('competitions_participation', null, '/spaces/' . $id);
        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }

        // Vérifier accès à l'espace (au minimum guest)
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());
        if (!$member) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
        }

        $competitions = $this->competition->findBySpace((int) $id);
        $isStaff = Middleware::isGlobalStaff();

        $this->render('competitions/index', [
            'title'        => 'Compétitions',
            'currentSpace' => $space,
            'spaceRole'    => $member['role'],
            'activeMenu'   => 'competitions',
            'competitions' => $competitions,
            'isStaff'      => $isStaff,
        ]);
    }

    /**
     * Formulaire de création d'une compétition.
     */
    public function createForm(string $id): void
    {
        $this->requireStaff();
        $this->checkSpaceRestriction((int) $id, 'competitions');

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());
        $gameTypes = (new GameType())->findBySpace((int) $id);
        $spaceMembers = $this->getSpaceMembers((int) $id);

        $this->render('competitions/create', [
            'title'        => 'Nouvelle compétition',
            'currentSpace' => $space,
            'spaceRole'    => $member['role'] ?? 'admin',
            'activeMenu'   => 'competitions',
            'gameTypes'    => $gameTypes,
            'spaceMembers' => $spaceMembers,
        ]);
    }

    /**
     * Traite la création d'une compétition.
     */
    public function create(string $id): void
    {
        $this->requireStaff();
        $this->checkSpaceRestriction((int) $id, 'competitions');
        $this->validateCSRF();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }

        $data = $this->getPostData(['name', 'description', 'starts_at', 'ends_at']);
        $allowedGameTypeIds = array_values(array_unique(array_map('intval', (array) ($_POST['allowed_game_type_ids'] ?? []))));
        $refereeNames  = $_POST['referee_names'] ?? [];
        $refereeEmails = $_POST['referee_emails'] ?? [];
        $refereeUserIds = $_POST['referee_user_ids'] ?? [];

        if (empty($data['name']) || empty($data['starts_at']) || empty($data['ends_at'])) {
            $this->setFlash('danger', 'Le nom, la date de début et la date de fin sont requis.');
            $this->redirect("/spaces/{$id}/competitions/create");
            return;
        }

        if (empty($allowedGameTypeIds)) {
            $this->setFlash('danger', 'Vous devez sélectionner au moins un type de jeu autorisé.');
            $this->redirect("/spaces/{$id}/competitions/create");
            return;
        }

        // Construire le tableau de sessions arbitres: membre lié OU nom/email libre
        $membersById = $this->getSpaceMembersById((int) $id);
        $referees = [];
        $rowCount = max(count((array) $refereeNames), count((array) $refereeUserIds), count((array) $refereeEmails));
        for ($i = 0; $i < $rowCount; $i++) {
            $userId = (int) ($refereeUserIds[$i] ?? 0);
            if ($userId > 0 && isset($membersById[$userId])) {
                $memberUser = $membersById[$userId];
                $referees[] = [
                    'user_id' => (int) $memberUser['id'],
                    'name' => (string) $memberUser['username'],
                    'email' => (string) ($memberUser['email'] ?? ''),
                ];
                continue;
            }

            $name = trim((string) ($refereeNames[$i] ?? ''));
            if ($name === '') {
                continue;
            }

            $referees[] = [
                'user_id' => 0,
                'name' => $name,
                'email' => trim((string) ($refereeEmails[$i] ?? '')),
            ];
        }

        if (empty($referees)) {
            $this->setFlash('danger', 'Vous devez ajouter au moins une session avec un arbitre (membre ou nom/email).');
            $this->redirect("/spaces/{$id}/competitions/create");
            return;
        }

        $competitionId = $this->competition->create([
            'space_id'    => (int) $id,
            'name'        => $data['name'],
            'description' => $data['description'] ?: null,
            'status'      => 'planned',
            'starts_at'   => $data['starts_at'],
            'ends_at'     => $data['ends_at'],
            'created_by'  => $this->getCurrentUserId(),
        ]);

        try {
            $this->competition->syncAllowedGameTypes($competitionId, (int) $id, $allowedGameTypeIds);
        } catch (\InvalidArgumentException $e) {
            $this->competition->delete((int) $competitionId);
            $this->setFlash('danger', $e->getMessage());
            $this->redirect("/spaces/{$id}/competitions/create");
            return;
        }

        // Créer les sessions
        $created = $this->session->createBatch($competitionId, $referees);

        ActivityLog::logCompetition($competitionId, 'competition.create', $this->getCurrentUserId(), 'competition', $competitionId, null, ['name' => $data['name'], 'sessions' => count($referees)]);

        // Envoyer les emails aux arbitres
        $emailResult = $this->sendSessionEmails($created, $data['name'], $competitionId);

        $msg = 'Compétition créée avec ' . count($referees) . ' session(s).';
        if ($emailResult['sent'] > 0) {
            $msg .= ' ' . $emailResult['sent'] . ' email(s) envoyé(s).';
        }
        if (!empty($emailResult['failed'])) {
            $msg .= ' ⚠ Échec d\'envoi pour : ' . implode(', ', $emailResult['failed']) . '.';
            $this->setFlash('warning', $msg);
        } else {
            $this->setFlash('success', $msg);
        }
        $this->redirect("/spaces/{$id}/competitions/{$competitionId}");
    }

    /**
     * Affiche le détail d'une compétition.
     */
    public function show(string $id, string $cid): void
    {
        $this->requireAuth();
        $this->checkUserRestriction('competitions_participation', null, '/spaces/' . $id);

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());
        if (!$member) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
        }

        $competition = $this->competition->findWithDetails((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
        }

        $sessions = $this->session->findByCompetition((int) $cid);
        $isStaff = Middleware::isGlobalStaff();
        $allowedGameTypes = $this->competition->getAllowedGameTypes((int) $cid);
        $registeredPlayers = $this->competition->getRegisteredPlayers((int) $cid);
        $allSpacePlayers = $this->player->findBySpace((int) $id);
        $spaceMembers = $this->getSpaceMembers((int) $id);

        $currentUserId = (int) ($this->getCurrentUserId() ?? 0);
        $linkedPlayerId = $this->findLinkedPlayerIdInSpace((int) $id, $currentUserId);
        $canSelfRegister = $linkedPlayerId !== null;
        $isSelfRegistered = $linkedPlayerId !== null
            ? $this->competition->isPlayerRegistered((int) $cid, $linkedPlayerId)
            : false;
        $assignedArbitrationSession = $currentUserId > 0
            ? $this->session->findAssignedSession((int) $cid, $currentUserId)
            : null;

        // Parties de la compétition
        $stmt = $this->pdo->prepare("
            SELECT g.*, gt.name AS game_type_name,
                   cs.session_number, cs.referee_name,
                   (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) AS player_count
            FROM games g
            JOIN game_types gt ON gt.id = g.game_type_id
            LEFT JOIN competition_sessions cs ON cs.id = g.session_id
            WHERE g.competition_id = :cid
            ORDER BY g.created_at DESC
        ");
        $stmt->execute(['cid' => (int) $cid]);
        $games = $stmt->fetchAll();

        // Classement de la compétition (manches gagnées)
        $rankings = $this->computeCompetitionRankings((int) $cid, (int) $id);
        $competitionSummary = $competition['status'] === 'closed'
            ? $this->computeCompetitionSummary((int) $cid, $rankings)
            : null;

        $this->render('competitions/show', [
            'title'        => $competition['name'],
            'currentSpace' => $space,
            'spaceRole'    => $member['role'],
            'activeMenu'   => 'competitions',
            'competition'  => $competition,
            'sessions'     => $sessions,
            'games'        => $games,
            'rankings'     => $rankings,
            'competitionSummary' => $competitionSummary,
            'isStaff'      => $isStaff,
            'allowedGameTypes' => $allowedGameTypes,
            'registeredPlayers' => $registeredPlayers,
            'allSpacePlayers' => $allSpacePlayers,
            'spaceMembers' => $spaceMembers,
            'canSelfRegister' => $canSelfRegister,
            'linkedPlayerId' => $linkedPlayerId,
            'isSelfRegistered' => $isSelfRegistered,
            'assignedArbitrationSession' => $assignedArbitrationSession,
        ]);
    }

    /**
     * Formulaire de modification d'une compétition.
     */
    public function editForm(string $id, string $cid): void
    {
        $this->requireStaff();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $gameTypes = (new GameType())->findBySpace((int) $id);
        $selectedGameTypeIds = $this->competition->getAllowedGameTypeIds((int) $cid);

        $this->render('competitions/edit', [
            'title'        => 'Modifier la compétition',
            'currentSpace' => $space,
            'spaceRole'    => $member['role'] ?? 'admin',
            'activeMenu'   => 'competitions',
            'competition'  => $competition,
            'gameTypes'    => $gameTypes,
            'selectedGameTypeIds' => $selectedGameTypeIds,
        ]);
    }

    /**
     * Traite la modification d'une compétition.
     */
    public function update(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $data = $this->getPostData(['name', 'description', 'starts_at', 'ends_at']);
        $allowedGameTypeIds = array_values(array_unique(array_map('intval', (array) ($_POST['allowed_game_type_ids'] ?? []))));

        if (empty($data['name']) || empty($data['starts_at']) || empty($data['ends_at'])) {
            $this->setFlash('danger', 'Le nom, la date de début et la date de fin sont requis.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}/edit");
            return;
        }

        if (empty($allowedGameTypeIds)) {
            $this->setFlash('danger', 'Vous devez sélectionner au moins un type de jeu autorisé.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}/edit");
            return;
        }

        $this->competition->update((int) $cid, [
            'name'        => $data['name'],
            'description' => $data['description'] ?: null,
            'starts_at'   => $data['starts_at'],
            'ends_at'     => $data['ends_at'],
        ]);

        try {
            $this->competition->syncAllowedGameTypes((int) $cid, (int) $id, $allowedGameTypeIds);
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('danger', $e->getMessage());
            $this->redirect("/spaces/{$id}/competitions/{$cid}/edit");
            return;
        }

        ActivityLog::logCompetition((int) $cid, 'competition.update', $this->getCurrentUserId(), 'competition', (int) $cid, null, ['name' => $data['name']]);

        $this->setFlash('success', 'Compétition mise à jour.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Inscription volontaire de l'utilisateur connecté à une compétition.
     */
    public function registerSelf(string $id, string $cid): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $this->checkUserRestriction('competitions_participation', null, "/spaces/{$id}/competitions/{$cid}");

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        if ((string) $competition['status'] === 'closed') {
            $this->setFlash('danger', 'Cette compétition est clôturée.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $playerId = $this->findLinkedPlayerIdInSpace((int) $id, (int) $this->getCurrentUserId());
        if ($playerId === null) {
            $this->setFlash('danger', 'Aucun joueur lié à votre compte n\'est disponible dans cet espace.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $this->competition->registerPlayer((int) $cid, (int) $playerId, (int) $this->getCurrentUserId());

        ActivityLog::logCompetition((int) $cid, 'competition.player_register_self', (int) $this->getCurrentUserId(), 'player', (int) $playerId, null);

        $this->setFlash('success', 'Vous êtes inscrit à la compétition.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Inscription d'un joueur par un membre de l'équipe de gestion.
     */
    public function addParticipant(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $playerId = (int) ($_POST['player_id'] ?? 0);
        if ($playerId <= 0) {
            $this->setFlash('danger', 'Sélection de joueur invalide.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $player = $this->player->find($playerId);
        if (!$player || (int) $player['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Joueur introuvable dans cet espace.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        if (!empty($player['user_id'])) {
            $linkedUserId = (int) $player['user_id'];
            $userModel = new User();
            if ($userModel->isRestricted($linkedUserId, 'competitions_participation') || $userModel->isRestricted($linkedUserId, 'games_participation')) {
                $this->setFlash('danger', 'Ce joueur lié à un compte est restreint pour la participation aux compétitions/parties.');
                $this->redirect("/spaces/{$id}/competitions/{$cid}");
                return;
            }
        }

        $this->competition->registerPlayer((int) $cid, $playerId, (int) $this->getCurrentUserId());

        ActivityLog::logCompetition((int) $cid, 'competition.player_register_staff', (int) $this->getCurrentUserId(), 'player', $playerId, null);

        $this->setFlash('success', 'Joueur inscrit à la compétition.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Désinscription d'un joueur de la compétition.
     */
    public function removeParticipant(string $id, string $cid, string $pid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $this->competition->unregisterPlayer((int) $cid, (int) $pid);

        ActivityLog::logCompetition((int) $cid, 'competition.player_unregister', (int) $this->getCurrentUserId(), 'player', (int) $pid, null);

        $this->setFlash('success', 'Joueur désinscrit de la compétition.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Active une compétition.
     */
    public function activate(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $this->competition->activate((int) $cid);

        ActivityLog::logCompetition((int) $cid, 'competition.activate', $this->getCurrentUserId(), 'competition', (int) $cid);

        $this->setFlash('success', 'Compétition activée.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Met en pause une compétition active.
     */
    public function pause(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        if ($competition['status'] !== 'active') {
            $this->setFlash('danger', 'Seule une compétition active peut être mise en pause.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $this->competition->pause((int) $cid);

        ActivityLog::logCompetition((int) $cid, 'competition.pause', $this->getCurrentUserId(), 'competition', (int) $cid);

        $this->setFlash('success', 'Compétition mise en pause.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Reprend une compétition en pause.
     */
    public function resume(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        if ($competition['status'] !== 'paused') {
            $this->setFlash('danger', 'Seule une compétition en pause peut être reprise.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $this->competition->resume((int) $cid);

        ActivityLog::logCompetition((int) $cid, 'competition.resume', $this->getCurrentUserId(), 'competition', (int) $cid);

        $this->setFlash('success', 'Compétition reprise.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Clôture toutes les sessions et la compétition.
     */
    public function close(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $this->competition->closeCompetition((int) $cid);

        ActivityLog::logCompetition((int) $cid, 'competition.close', $this->getCurrentUserId(), 'competition', (int) $cid);

        $this->setFlash('success', 'Compétition clôturée. Toutes les sessions sont désactivées.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Ajoute une session supplémentaire.
     */
    public function addSession(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $refereeUserId = (int) ($_POST['referee_user_id'] ?? 0);
        $refereeName  = trim((string) ($_POST['referee_name'] ?? ''));
        $refereeEmail = trim((string) ($_POST['referee_email'] ?? ''));

        $refereePayload = null;
        if ($refereeUserId > 0) {
            $membersById = $this->getSpaceMembersById((int) $id);
            if (!isset($membersById[$refereeUserId])) {
                $this->setFlash('danger', 'Le membre sélectionné ne fait pas partie de cet espace.');
                $this->redirect("/spaces/{$id}/competitions/{$cid}");
                return;
            }

            $memberUser = $membersById[$refereeUserId];
            $refereePayload = [
                'user_id' => (int) $memberUser['id'],
                'name' => (string) $memberUser['username'],
                'email' => (string) ($memberUser['email'] ?? ''),
            ];
        } else {
            if (empty($refereeName)) {
                $this->setFlash('danger', 'Le nom de l\'arbitre est requis si aucun membre n\'est sélectionné.');
                $this->redirect("/spaces/{$id}/competitions/{$cid}");
                return;
            }
            $refereePayload = [
                'user_id' => 0,
                'name' => $refereeName,
                'email' => $refereeEmail,
            ];
        }

        $created = $this->session->createBatch((int) $cid, [$refereePayload]);

        ActivityLog::logCompetition((int) $cid, 'session.add', $this->getCurrentUserId(), 'competition_session', null, null, ['referee' => $refereeName]);

        $emailResult = $this->sendSessionEmails($created, $competition['name'], (int) $cid);

        $msg = 'Session ajoutée.';
        if ($emailResult['sent'] > 0) {
            $msg .= ' Email envoyé.';
        } elseif (!empty($emailResult['failed'])) {
            $msg .= ' ⚠ Échec d\'envoi de l\'email.';
        }
        $this->setFlash(!empty($emailResult['failed']) ? 'warning' : 'success', $msg);
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Réinitialise le mot de passe d'une session et envoie un email.
     */
    public function resetSessionPassword(string $id, string $cid, string $sid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $session = $this->session->find((int) $sid);
        if (!$session || (int) $session['competition_id'] !== (int) $cid) {
            $this->setFlash('danger', 'Session introuvable.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $newPassword = $this->session->resetPassword((int) $sid);

        ActivityLog::logCompetition((int) $cid, 'session.password_reset', $this->getCurrentUserId(), 'competition_session', (int) $sid, (int) $sid, ['session_number' => $session['session_number']]);

        // Envoyer l'email si l'arbitre a un email
        $msg = 'Mot de passe réinitialisé pour la session #' . $session['session_number'] . '. Nouveau : ' . $newPassword;
        if (!empty($session['referee_email'])) {
            $emailResult = $this->sendSessionEmails([
                [
                    'referee_name'   => $session['referee_name'],
                    'referee_email'  => $session['referee_email'],
                    'session_number' => $session['session_number'],
                    'password'       => $newPassword,
                ],
            ], $competition['name'], (int) $cid);

            if ($emailResult['sent'] > 0) {
                $msg .= ' Email envoyé.';
            } elseif (!empty($emailResult['failed'])) {
                $msg .= ' ⚠ Échec d\'envoi de l\'email.';
            }
        }

        $this->setFlash('success', $msg);
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Interrompt (désactive) une session à distance.
     */
    public function deactivateSession(string $id, string $cid, string $sid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $session = $this->session->find((int) $sid);
        if (!$session || (int) $session['competition_id'] !== (int) $cid) {
            $this->setFlash('danger', 'Session introuvable.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $this->session->deactivate((int) $sid);

        ActivityLog::logCompetition((int) $cid, 'session.deactivate', $this->getCurrentUserId(), 'competition_session', (int) $sid, (int) $sid, ['session_number' => $session['session_number']]);

        $this->setFlash('success', 'Session #' . $session['session_number'] . ' interrompue.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Réactive une session interrompue.
     */
    public function reactivateSession(string $id, string $cid, string $sid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        $session = $this->session->find((int) $sid);
        if (!$session || (int) $session['competition_id'] !== (int) $cid) {
            $this->setFlash('danger', 'Session introuvable.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $this->session->reactivate((int) $sid);

        ActivityLog::logCompetition((int) $cid, 'session.reactivate', $this->getCurrentUserId(), 'competition_session', (int) $sid, (int) $sid, ['session_number' => $session['session_number']]);

        $this->setFlash('success', 'Session #' . $session['session_number'] . ' réactivée.');
        $this->redirect("/spaces/{$id}/competitions/{$cid}");
    }

    /**
     * Supprime une compétition (et ses sessions en cascade).
     */
    public function delete(string $id, string $cid): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $competition = $this->competition->find((int) $cid);
        if (!$competition || (int) $competition['space_id'] !== (int) $id) {
            $this->setFlash('danger', 'Compétition introuvable.');
            $this->redirect("/spaces/{$id}/competitions");
            return;
        }

        ActivityLog::logCompetition((int) $cid, 'competition.delete', $this->getCurrentUserId(), 'competition', (int) $cid, null, ['name' => $competition['name']]);

        // Détacher les parties de la compétition (ne pas les supprimer, elles font partie de l'espace)
        $this->pdo->prepare("UPDATE games SET competition_id = NULL, session_id = NULL WHERE competition_id = :cid")
            ->execute(['cid' => (int) $cid]);

        $this->competition->delete((int) $cid);
        $this->setFlash('success', 'Compétition supprimée.');
        $this->redirect("/spaces/{$id}/competitions");
    }

    /**
     * Envoie un email de connexion à chaque arbitre ayant un email.
     */
    /**
     * @return array{sent: int, failed: string[]}
     */
    private function sendSessionEmails(array $sessions, string $competitionName, int $competitionId): array
    {
        $mailer = new Mailer();
        $sent   = 0;
        $failed = [];

        $loginUrl = rtrim(getenv('APP_URL') ?: ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . '/competition/login';

        foreach ($sessions as $i => $s) {
            $email = $s['referee_email'] ?? '';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $body = "<h2>Scores — Compétition : " . htmlspecialchars($competitionName) . "</h2>"
                . "<p>Bonjour <strong>" . htmlspecialchars($s['referee_name']) . "</strong>,</p>"
                . "<p>Voici vos identifiants de connexion pour la session d'arbitrage :</p>"
                . "<ul>"
                . "<li><strong>ID de la compétition :</strong> " . $competitionId . "</li>"
                . "<li><strong>Numéro de session :</strong> " . (int) $s['session_number'] . "</li>"
                . "<li><strong>Mot de passe :</strong> " . htmlspecialchars($s['password']) . "</li>"
                . "</ul>"
                . "<p>Connectez-vous ici pour accéder à votre interface de saisie : <a href=\"" . htmlspecialchars($loginUrl) . "\">" . htmlspecialchars($loginUrl) . "</a></p>"
                . "<p>— L'équipe Scores</p>";

            // Pause entre les envois pour éviter le rate-limiting SMTP
            if ($i > 0) {
                usleep(500000); // 500 ms
            }

            try {
                $mailer->send($email, "Scores — Vos identifiants d'arbitrage", $body);
                $sent++;
            } catch (\RuntimeException $e) {
                error_log("Email arbitre échoué ({$email}): " . $e->getMessage());
                $failed[] = $s['referee_name'] . ' (' . $email . ')';
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Calcule le classement de la compétition par manches gagnées.
     */
    private function computeCompetitionRankings(int $competitionId, int $spaceId): array
    {
        // Manches terminées des parties de la compétition
        $stmt = $this->pdo->prepare("
            SELECT r.id AS round_id, gt.win_condition
            FROM rounds r
            JOIN games g ON g.id = r.game_id
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.competition_id = :cid
              AND r.status = 'completed'
        ");
        $stmt->execute(['cid' => $competitionId]);
        $rounds = $stmt->fetchAll();

        $played = [];
        $won    = [];

        foreach ($rounds as $round) {
            $scoreStmt = $this->pdo->prepare("
                SELECT rs.player_id, rs.score FROM round_scores rs WHERE rs.round_id = :rid
            ");
            $scoreStmt->execute(['rid' => $round['round_id']]);
            $scores = $scoreStmt->fetchAll();

            if (empty($scores)) continue;

            foreach ($scores as $s) {
                $pid = (int) $s['player_id'];
                $played[$pid] = ($played[$pid] ?? 0) + 1;
            }

            $scoreValues = array_column($scores, 'score');
            $best = ($round['win_condition'] === 'ranking' || $round['win_condition'] === 'lowest_score')
                ? min($scoreValues) : max($scoreValues);

            foreach ($scores as $s) {
                if ((float) $s['score'] === (float) $best) {
                    $pid = (int) $s['player_id'];
                    $won[$pid] = ($won[$pid] ?? 0) + 1;
                }
            }
        }

        // Récupérer les noms
        $playerStmt = $this->pdo->prepare("SELECT id, name FROM players WHERE space_id = :sid");
        $playerStmt->execute(['sid' => $spaceId]);
        $players = $playerStmt->fetchAll();

        $result = [];
        foreach ($players as $p) {
            $pid = (int) $p['id'];
            if (!isset($played[$pid])) continue;
            $result[] = [
                'name'          => $p['name'],
                'rounds_played' => $played[$pid],
                'rounds_won'    => $won[$pid] ?? 0,
                'win_rate'      => round(($won[$pid] ?? 0) * 100.0 / $played[$pid], 1),
            ];
        }

        usort($result, fn($a, $b) => $b['rounds_won'] !== $a['rounds_won']
            ? $b['rounds_won'] - $a['rounds_won']
            : $b['win_rate'] <=> $a['win_rate']
        );

        return $result;
    }

    /**
     * Calcule un bilan global pour une compétition clôturée.
     */
    private function computeCompetitionSummary(int $competitionId, array $rankings): array
    {
        $gameStmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total_games,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_games,
                COUNT(DISTINCT session_id) AS sessions_used,
                COUNT(DISTINCT game_type_id) AS game_types_used
             FROM games
             WHERE competition_id = :cid"
        );
        $gameStmt->execute(['cid' => $competitionId]);
        $gameStats = $gameStmt->fetch() ?: [];

        $roundStmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total_rounds,
                COALESCE(SUM(
                    GREATEST(
                        0,
                        TIMESTAMPDIFF(SECOND, r.started_at, COALESCE(r.ended_at, NOW())) - COALESCE(rp.pause_seconds, 0)
                    )
                ), 0) AS total_play_seconds
             FROM rounds r
             INNER JOIN games g ON g.id = r.game_id
             LEFT JOIN (
                SELECT
                    round_id,
                    COALESCE(SUM(
                        CASE
                            WHEN duration_seconds IS NOT NULL THEN duration_seconds
                            WHEN resumed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, paused_at, resumed_at)
                            ELSE TIMESTAMPDIFF(SECOND, paused_at, NOW())
                        END
                    ), 0) AS pause_seconds
                FROM round_pauses
                GROUP BY round_id
             ) rp ON rp.round_id = r.id
             WHERE g.competition_id = :cid
               AND r.status = 'completed'"
        );
        $roundStmt->execute(['cid' => $competitionId]);
        $roundStats = $roundStmt->fetch() ?: [];

        $playerCount = count($rankings);
        $roundsPlayedTotal = 0;
        $winsTotal = 0;
        $winRateTotal = 0.0;
        foreach ($rankings as $row) {
            $roundsPlayedTotal += (int) ($row['rounds_played'] ?? 0);
            $winsTotal += (int) ($row['rounds_won'] ?? 0);
            $winRateTotal += (float) ($row['win_rate'] ?? 0);
        }

        return [
            'total_games' => (int) ($gameStats['total_games'] ?? 0),
            'completed_games' => (int) ($gameStats['completed_games'] ?? 0),
            'sessions_used' => (int) ($gameStats['sessions_used'] ?? 0),
            'game_types_used' => (int) ($gameStats['game_types_used'] ?? 0),
            'total_rounds' => (int) ($roundStats['total_rounds'] ?? 0),
            'total_play_seconds' => (int) ($roundStats['total_play_seconds'] ?? 0),
            'player_count' => $playerCount,
            'avg_rounds_per_player' => $playerCount > 0 ? round($roundsPlayedTotal / $playerCount, 2) : 0.0,
            'avg_win_rate' => $playerCount > 0 ? round($winRateTotal / $playerCount, 1) : 0.0,
            'avg_round_wins_per_player' => $playerCount > 0 ? round($winsTotal / $playerCount, 2) : 0.0,
        ];
    }

    /**
     * Retourne l'ID du joueur lié à un utilisateur dans un espace, ou null.
     */
    private function findLinkedPlayerIdInSpace(int $spaceId, int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id
             FROM players
             WHERE space_id = :space_id
               AND user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute([
            'space_id' => $spaceId,
            'user_id' => $userId,
        ]);

        $value = $stmt->fetchColumn();
        return $value !== false ? (int) $value : null;
    }

    /**
     * Liste des membres d'un espace (utilisateurs) pour assignation arbitre.
     */
    private function getSpaceMembers(int $spaceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.username, u.email
             FROM space_members sm
             INNER JOIN users u ON u.id = sm.user_id
             WHERE sm.space_id = :space_id
             ORDER BY u.username ASC"
        );
        $stmt->execute(['space_id' => $spaceId]);
        return $stmt->fetchAll();
    }

    /**
     * Indexe les membres d'un espace par id utilisateur.
     */
    private function getSpaceMembersById(int $spaceId): array
    {
        $rows = $this->getSpaceMembers($spaceId);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }
        return $indexed;
    }
}
