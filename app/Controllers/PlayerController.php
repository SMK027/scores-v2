<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Config\Database;
use App\Models\Player;
use App\Models\Space;
use App\Models\SpaceMember;
use App\Models\User;
use App\Models\ActivityLog;

/**
 * Contrôleur des joueurs.
 */
class PlayerController extends Controller
{
    private Player $playerModel;
    private Space $spaceModel;
    private SpaceMember $spaceMemberModel;
    private \PDO $pdo;

    public function __construct()
    {
        $this->playerModel = new Player();
        $this->spaceModel = new Space();
        $this->spaceMemberModel = new SpaceMember();
        $this->pdo = Database::getInstance()->getConnection();
    }

    private function checkAccess(string $spaceId, array $roles = ['admin', 'manager', 'member', 'guest']): array
    {
        $this->requireAuth();
        $space = $this->spaceModel->find((int) $spaceId);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }
        $member = Middleware::checkSpaceAccess((int) $spaceId, $this->getCurrentUserId(), $roles);
        if (!$member) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
        }
        return ['space' => $space, 'member' => $member];
    }

    /**
     * Liste les joueurs.
     */
    public function index(string $id): void
    {
        $ctx = $this->checkAccess($id);
        $players = $this->playerModel->findBySpace((int) $id);

        // Calculer les stats par manches (rounds) pour chaque joueur
        $roundStats = $this->computeRoundStats((int) $id);
        foreach ($players as &$player) {
            $pid = (int) $player['id'];
            $player['rounds_played'] = $roundStats[$pid]['played'] ?? 0;
            $player['rounds_won']    = $roundStats[$pid]['won'] ?? 0;
        }
        unset($player);

        // Vérifier si l'utilisateur courant est déjà raccordé
        $currentUserId = $this->getCurrentUserId();
        $isLinked = $this->playerModel->isUserLinkedInSpace((int) $id, $currentUserId);

        $this->render('players/index', [
            'title'        => 'Joueurs',
            'currentSpace' => $ctx['space'],
            'spaceRole'    => $ctx['member']['role'],
            'activeMenu'   => 'players',
            'players'      => $players,
            'isLinked'     => $isLinked,
        ]);
    }

    /**
     * Calcule les manches jouées et gagnées par joueur dans l'espace.
     * Même logique que StatController::getTopPlayers() : le gagnant d'une
     * manche est celui qui a le meilleur score selon la win_condition du jeu.
     * En cas d'égalité au meilleur score, tous les ex-aequo sont gagnants.
     * Inclut toutes les manches terminées, y compris celles des parties en cours.
     *
     * @return array<int, array{played: int, won: int}> Indexé par player_id
     */
    private function computeRoundStats(int $spaceId): array
    {
        // Récupérer toutes les manches terminées de l'espace (parties en cours incluses)
        $stmt = $this->pdo->prepare("
            SELECT r.id AS round_id, gt.win_condition
            FROM rounds r
            JOIN games g ON g.id = r.game_id
            JOIN game_types gt ON gt.id = g.game_type_id
            WHERE g.space_id = :space_id
              AND r.status = 'completed'
        ");
        $stmt->execute(['space_id' => $spaceId]);
        $rounds = $stmt->fetchAll();

        $played = []; // player_id => int
        $won    = []; // player_id => int

        foreach ($rounds as $round) {
            $scoreStmt = $this->pdo->prepare("
                SELECT rs.player_id, rs.score
                FROM round_scores rs
                WHERE rs.round_id = :round_id
            ");
            $scoreStmt->execute(['round_id' => $round['round_id']]);
            $scores = $scoreStmt->fetchAll();

            if (empty($scores)) {
                continue;
            }

            // Compter la manche comme jouée pour chaque participant
            foreach ($scores as $s) {
                $pid = (int) $s['player_id'];
                $played[$pid] = ($played[$pid] ?? 0) + 1;
            }

            // Déterminer le meilleur score selon la condition de victoire
            $scoreValues = array_column($scores, 'score');
            if ($round['win_condition'] === 'ranking' || $round['win_condition'] === 'lowest_score') {
                $bestScore = min($scoreValues);
            } else {
                $bestScore = max($scoreValues);
            }

            // Tous les joueurs au meilleur score gagnent la manche
            foreach ($scores as $s) {
                if ((float) $s['score'] === (float) $bestScore) {
                    $pid = (int) $s['player_id'];
                    $won[$pid] = ($won[$pid] ?? 0) + 1;
                }
            }
        }

        $result = [];
        foreach ($played as $pid => $count) {
            $result[$pid] = [
                'played' => $count,
                'won'    => $won[$pid] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Formulaire de création.
     */
    public function createForm(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);

        $members = $this->spaceMemberModel->findBySpace((int) $id);
        $linkedUserIds = $this->playerModel->getLinkedUserIds((int) $id);

        $this->render('players/create', [
            'title'         => 'Ajouter un joueur',
            'currentSpace'  => $ctx['space'],
            'spaceRole'     => $ctx['member']['role'],
            'activeMenu'    => 'players',
            'members'       => $members,
            'linkedUserIds' => $linkedUserIds,
        ]);
    }

    /**
     * Traite la création.
     */
    public function create(string $id): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager', 'member']);
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'user_id']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom du joueur est requis.');
            $this->redirect("/spaces/{$id}/players/create");
        }

        $createData = [
            'space_id' => (int) $id,
            'name'     => $data['name'],
        ];

        if (!empty($data['user_id'])) {
            $userId = (int) $data['user_id'];
            if ($this->playerModel->isUserLinkedInSpace((int) $id, $userId)) {
                $this->setFlash('danger', 'Ce compte est déjà raccordé à un autre joueur dans cet espace.');
                $this->redirect("/spaces/{$id}/players/create");
                return;
            }
            $createData['user_id'] = $userId;
        }

        $this->playerModel->create($createData);

        ActivityLog::logSpace((int) $id, 'player.create', $this->getCurrentUserId(), 'player', null, ['name' => $data['name']]);

        $this->setFlash('success', 'Joueur ajouté.');
        $this->redirect("/spaces/{$id}/players");
    }

    /**
     * Formulaire d'édition.
     */
    public function editForm(string $id, string $pid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);

        if (!$player) {
            $this->setFlash('danger', 'Joueur introuvable.');
            $this->redirect("/spaces/{$id}/players");
        }

        $members = $this->spaceMemberModel->findBySpace((int) $id);
        $linkedUserIds = $this->playerModel->getLinkedUserIds((int) $id, (int) $pid);

        $this->render('players/edit', [
            'title'         => 'Modifier le joueur',
            'currentSpace'  => $ctx['space'],
            'spaceRole'     => $ctx['member']['role'],
            'activeMenu'    => 'players',
            'player'        => $player,
            'members'       => $members,
            'linkedUserIds' => $linkedUserIds,
        ]);
    }

    /**
     * Traite la modification.
     */
    public function update(string $id, string $pid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->validateCSRF();

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->setFlash('danger', 'Joueur introuvable.');
            $this->redirect("/spaces/{$id}/players");
            return;
        }

        $data = $this->getPostData(['name', 'user_id']);

        if (empty($data['name'])) {
            $this->setFlash('danger', 'Le nom est requis.');
            $this->redirect("/spaces/{$id}/players/{$pid}/edit");
        }

        $updateData = ['name' => $data['name']];
        if (!empty($data['user_id'])) {
            $userId = (int) $data['user_id'];
            if ($this->playerModel->isUserLinkedInSpace((int) $id, $userId, (int) $pid)) {
                $this->setFlash('danger', 'Ce compte est déjà raccordé à un autre joueur dans cet espace.');
                $this->redirect("/spaces/{$id}/players/{$pid}/edit");
                return;
            }
            $updateData['user_id'] = $userId;
        } else {
            $updateData['user_id'] = null;
        }

        $this->playerModel->update((int) $pid, $updateData);

        ActivityLog::logSpace((int) $id, 'player.update', $this->getCurrentUserId(), 'player', (int) $pid, ['name' => $data['name']]);

        $this->setFlash('success', 'Joueur mis à jour.');
        $this->redirect("/spaces/{$id}/players");
    }

    /**
     * Supprime un joueur.
     */
    public function delete(string $id, string $pid): void
    {
        $ctx = $this->checkAccess($id, ['admin', 'manager']);
        $this->validateCSRF();

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->setFlash('danger', 'Joueur introuvable.');
            $this->redirect("/spaces/{$id}/players");
            return;
        }

        ActivityLog::logSpace((int) $id, 'player.delete', $this->getCurrentUserId(), 'player', (int) $pid);

        $this->playerModel->softDelete((int) $pid);
        $this->setFlash('success', 'Joueur supprimé.');
        $this->redirect("/spaces/{$id}/players");
    }

    /**
     * Permet à un membre de se raccorder à un joueur existant non lié.
     */
    public function linkSelf(string $id, string $pid): void
    {
        $ctx = $this->checkAccess($id);
        $this->validateCSRF();

        $currentUserId = $this->getCurrentUserId();

        // Vérifier que l'utilisateur n'est pas déjà raccordé
        if ($this->playerModel->isUserLinkedInSpace((int) $id, $currentUserId)) {
            $this->setFlash('danger', 'Vous êtes déjà raccordé à un joueur dans cet espace.');
            $this->redirect("/spaces/{$id}/players");
            return;
        }

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->setFlash('danger', 'Joueur introuvable.');
            $this->redirect("/spaces/{$id}/players");
            return;
        }

        // Le joueur ne doit pas déjà être lié à un autre compte
        if (!empty($player['user_id'])) {
            $this->setFlash('danger', 'Ce joueur est déjà raccordé à un compte.');
            $this->redirect("/spaces/{$id}/players");
            return;
        }

        $this->playerModel->update((int) $pid, ['user_id' => $currentUserId]);

        ActivityLog::logSpace((int) $id, 'player.link_self', $currentUserId, 'player', (int) $pid, [
            'player_name' => $player['name'],
        ]);

        $this->setFlash('success', "Vous êtes maintenant raccordé au joueur « {$player['name']} ».");
        $this->redirect("/spaces/{$id}/players");
    }
}
