<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Middleware;
use App\Models\Competition;
use App\Models\CompetitionSession;
use App\Models\Space;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\GameType;
use App\Models\Round;
use App\Models\RoundScore;
use App\Config\Database;

/**
 * Contrôleur des compétitions.
 * Accessible uniquement aux modérateurs, admins et super-admins globaux.
 */
class CompetitionController extends Controller
{
    private Competition $competition;
    private CompetitionSession $session;
    private Space $spaceModel;
    private \PDO $pdo;

    public function __construct()
    {
        $this->competition = new Competition();
        $this->session     = new CompetitionSession();
        $this->spaceModel  = new Space();
        $this->pdo         = Database::getInstance()->getConnection();
    }

    /**
     * Vérifie que l'utilisateur est staff global (moderator, admin ou superadmin).
     */
    private function requireStaff(): void
    {
        $this->requireGlobalRole(['moderator', 'admin', 'superadmin']);
    }

    /**
     * Liste des compétitions d'un espace.
     */
    public function index(string $id): void
    {
        $this->requireAuth();
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

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId());

        $this->render('competitions/create', [
            'title'        => 'Nouvelle compétition',
            'currentSpace' => $space,
            'spaceRole'    => $member['role'] ?? 'admin',
            'activeMenu'   => 'competitions',
        ]);
    }

    /**
     * Traite la création d'une compétition.
     */
    public function create(string $id): void
    {
        $this->requireStaff();
        $this->validateCSRF();

        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }

        $data = $this->getPostData(['name', 'description', 'starts_at', 'ends_at']);
        $refereeNames  = $_POST['referee_names'] ?? [];
        $refereeEmails = $_POST['referee_emails'] ?? [];

        if (empty($data['name']) || empty($data['starts_at']) || empty($data['ends_at'])) {
            $this->setFlash('danger', 'Le nom, la date de début et la date de fin sont requis.');
            $this->redirect("/spaces/{$id}/competitions/create");
            return;
        }

        if (empty($refereeNames) || count(array_filter($refereeNames, fn($n) => trim($n) !== '')) === 0) {
            $this->setFlash('danger', 'Vous devez ajouter au moins une session avec un nom d\'arbitre.');
            $this->redirect("/spaces/{$id}/competitions/create");
            return;
        }

        // Construire le tableau de referees avec noms et emails
        $referees = [];
        foreach ($refereeNames as $i => $name) {
            $name = trim($name);
            if ($name === '') continue;
            $referees[] = [
                'name'  => $name,
                'email' => trim($refereeEmails[$i] ?? ''),
            ];
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

        // Créer les sessions
        $created = $this->session->createBatch($competitionId, $referees);

        // Envoyer les emails aux arbitres
        $this->sendSessionEmails($created, $data['name'], $competitionId);

        $this->setFlash('success', 'Compétition créée avec ' . count($referees) . ' session(s).');
        $this->redirect("/spaces/{$id}/competitions/{$competitionId}");
    }

    /**
     * Affiche le détail d'une compétition.
     */
    public function show(string $id, string $cid): void
    {
        $this->requireAuth();

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

        $this->render('competitions/show', [
            'title'        => $competition['name'],
            'currentSpace' => $space,
            'spaceRole'    => $member['role'],
            'activeMenu'   => 'competitions',
            'competition'  => $competition,
            'sessions'     => $sessions,
            'games'        => $games,
            'rankings'     => $rankings,
            'isStaff'      => $isStaff,
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

        $this->render('competitions/edit', [
            'title'        => 'Modifier la compétition',
            'currentSpace' => $space,
            'spaceRole'    => $member['role'] ?? 'admin',
            'activeMenu'   => 'competitions',
            'competition'  => $competition,
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

        if (empty($data['name']) || empty($data['starts_at']) || empty($data['ends_at'])) {
            $this->setFlash('danger', 'Le nom, la date de début et la date de fin sont requis.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}/edit");
            return;
        }

        $this->competition->update((int) $cid, [
            'name'        => $data['name'],
            'description' => $data['description'] ?: null,
            'starts_at'   => $data['starts_at'],
            'ends_at'     => $data['ends_at'],
        ]);

        $this->setFlash('success', 'Compétition mise à jour.');
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

        $refereeName  = trim($_POST['referee_name'] ?? '');
        $refereeEmail = trim($_POST['referee_email'] ?? '');
        if (empty($refereeName)) {
            $this->setFlash('danger', 'Le nom de l\'arbitre est requis.');
            $this->redirect("/spaces/{$id}/competitions/{$cid}");
            return;
        }

        $created = $this->session->createBatch((int) $cid, [['name' => $refereeName, 'email' => $refereeEmail]]);
        $this->sendSessionEmails($created, $competition['name'], (int) $cid);
        $this->setFlash('success', 'Session ajoutée.');
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

        // Envoyer l'email si l'arbitre a un email
        if (!empty($session['referee_email'])) {
            $this->sendSessionEmails([
                [
                    'referee_name'   => $session['referee_name'],
                    'referee_email'  => $session['referee_email'],
                    'session_number' => $session['session_number'],
                    'password'       => $newPassword,
                ],
            ], $competition['name'], (int) $cid);
        }

        $this->setFlash('success', 'Mot de passe réinitialisé pour la session #' . $session['session_number'] . '. Nouveau : ' . $newPassword);
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
    private function sendSessionEmails(array $sessions, string $competitionName, int $competitionId): void
    {
        $mailer = new Mailer();
        foreach ($sessions as $s) {
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
                . "</ul>";

            $loginUrl = rtrim(getenv('APP_URL') ?: ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . '/competition/login';

            $body .= "<p>Connectez-vous ici pour accéder à votre interface de saisie : <a href=\"" . htmlspecialchars($loginUrl) . "\">" . htmlspecialchars($loginUrl) . "</a></p>"
                . "<p>— L'équipe Scores</p>";

            try {
                $mailer->send($email, "Scores — Vos identifiants d'arbitrage", $body);
            } catch (\RuntimeException $e) {
                // Ne pas bloquer la création si l'email échoue
                if (getenv('APP_DEBUG') === 'true') {
                    error_log("Email arbitre échoué ({$email}): " . $e->getMessage());
                }
            }
        }
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
}
