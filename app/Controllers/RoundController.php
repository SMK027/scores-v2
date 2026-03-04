<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Round;
use App\Models\RoundScore;
use App\Models\Game;
use App\Models\Space;

class RoundController extends Controller
{
    private Round $round;
    private RoundScore $roundScore;
    private Game $game;
    private Space $spaceModel;

    public function __construct()
    {
        $this->round      = new Round();
        $this->roundScore = new RoundScore();
        $this->game       = new Game();
        $this->spaceModel = new Space();
    }

    /**
     * Vérifie l'accès à l'espace et retourne les infos.
     */
    private function checkAccess(string $id, array $roles = ['admin', 'manager', 'member']): array
    {
        $this->requireAuth();
        $space = $this->spaceModel->find((int) $id);
        if (!$space) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Espace introuvable.']);
            }
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            exit;
        }
        $member = Middleware::checkSpaceAccess((int) $id, $this->getCurrentUserId(), $roles);
        if (!$member) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Accès non autorisé.']);
            }
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
            exit;
        }
        return ['space' => $space, 'member' => $member];
    }

    /**
     * Créer une nouvelle manche.
     */
    public function create(string $id, string $gid): void
    {
        $this->checkAccess($id);

        $game = $this->game->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
            return;
        }

        if (!in_array($game['status'], ['in_progress', 'paused'])) {
            $this->setFlash('warning', 'Impossible d\'ajouter une manche à cette partie.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->validateCSRF();

        $data = $this->getPostData(['notes']);
        $this->round->createForGame((int) $gid, $data['notes'] ?: null);

        $this->setFlash('success', 'Manche ajoutée avec succès.');
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Enregistrer/mettre à jour les scores d'une manche.
     */
    public function updateScores(string $id, string $gid, string $rid): void
    {
        $this->checkAccess($id);

        $game = $this->game->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Partie introuvable.']);
            }
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
            return;
        }

        $round = $this->round->find((int) $rid);
        if (!$round || $round['game_id'] != $gid) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Manche introuvable.']);
            }
            $this->setFlash('danger', 'Manche introuvable.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        if ($round['status'] === 'completed') {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Cette manche est déjà terminée.']);
            }
            $this->setFlash('warning', 'Cette manche est déjà terminée.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->validateCSRF();

        $scores = $_POST['scores'] ?? [];
        if (!empty($scores)) {
            $this->roundScore->saveScores((int) $rid, $scores);
            $this->game->recalculateTotals((int) $gid);
        }

        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => 'Scores enregistrés.']);
        }

        $this->setFlash('success', 'Scores enregistrés.');
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Changer le statut d'une manche.
     */
    public function updateStatus(string $id, string $gid, string $rid): void
    {
        $this->checkAccess($id);

        $game = $this->game->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
            return;
        }

        $round = $this->round->find((int) $rid);
        if (!$round || $round['game_id'] != $gid) {
            $this->setFlash('danger', 'Manche introuvable.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->validateCSRF();

        $data = $this->getPostData(['status']);
        $status = $data['status'];
        $allowed = ['in_progress', 'completed'];

        if (!in_array($status, $allowed)) {
            $this->setFlash('danger', 'Statut invalide.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->round->updateStatus((int) $rid, $status);

        if ($status === 'completed') {
            $this->game->recalculateTotals((int) $gid);
        }

        $label = $status === 'completed' ? 'terminée' : 'reprise';
        $this->setFlash('success', "Manche {$label}.");
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Supprimer une manche.
     */
    public function delete(string $id, string $gid, string $rid): void
    {
        $this->checkAccess($id, ['admin', 'manager']);

        $game = $this->game->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
            return;
        }

        $round = $this->round->find((int) $rid);
        if (!$round || $round['game_id'] != $gid) {
            $this->setFlash('danger', 'Manche introuvable.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->validateCSRF();

        $this->round->deleteWithScores((int) $rid);
        $this->game->recalculateTotals((int) $gid);

        $this->setFlash('success', 'Manche supprimée.');
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }
}
