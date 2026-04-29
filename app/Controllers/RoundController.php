<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\Round;
use App\Models\RoundScore;
use App\Models\RoundPause;
use App\Models\Game;
use App\Models\GameType;
use App\Models\Space;
use App\Models\ActivityLog;

class RoundController extends Controller
{
    private Round $round;
    private RoundScore $roundScore;
    private RoundPause $roundPause;
    private Game $game;
    private Space $spaceModel;

    public function __construct()
    {
        $this->round      = new Round();
        $this->roundScore = new RoundScore();
        $this->roundPause = new RoundPause();
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
     * Vérifie si la partie de compétition est protégée pour l'utilisateur courant.
     */
    private function isCompetitionProtected(array $game): bool
    {
        return !empty($game['competition_id']) && !Middleware::isGlobalStaff();
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

        if ($this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        if (!in_array($game['status'], ['in_progress', 'paused'])) {
            $this->setFlash('warning', 'Impossible d\'ajouter une manche à cette partie.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        if ($this->round->hasActiveRound((int) $gid)) {
            $this->setFlash('warning', 'Terminez la manche en cours avant d\'en créer une nouvelle.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->validateCSRF();

        $data = $this->getPostData(['notes']);
        try {
            $this->round->createForGame((int) $gid, $data['notes'] ?: null);
        } catch (\DomainException $e) {
            $this->setFlash('warning', $e->getMessage());
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        ActivityLog::logSpace((int) $id, 'round.create', $this->getCurrentUserId(), 'game', (int) $gid);

        $this->setFlash('success', 'Manche ajoutée avec succès.');
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Crée une manche déjà terminée dont la durée correspond à la moyenne
     * du type de jeu de la partie. Réservé aux administrateurs et gestionnaires.
     */
    public function createWithAvgDuration(string $id, string $gid): void
    {
        $this->checkAccess($id, ['admin', 'manager']);

        $game = $this->game->find((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
            return;
        }

        if ($this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        if (!in_array($game['status'], ['in_progress', 'paused'])) {
            $this->setFlash('warning', 'Impossible d\'ajouter une manche à cette partie.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        if ($this->round->hasActiveRound((int) $gid)) {
            $this->setFlash('warning', 'Terminez la manche en cours avant d\'en créer une nouvelle.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->validateCSRF();

        $gameType = new GameType();
        $avg = $gameType->getAverageRoundDuration((int) $game['game_type_id']);
        if ($avg === null || $avg <= 0) {
            $this->setFlash('warning', 'Aucune durée moyenne disponible pour ce type de jeu.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $data = $this->getPostData(['notes']);
        try {
            $newId = $this->round->createForGameWithDuration((int) $gid, $avg, $data['notes'] ?: null);
        } catch (\DomainException $e) {
            $this->setFlash('warning', $e->getMessage());
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->game->recalculateTotals((int) $gid);

        ActivityLog::logSpace((int) $id, 'round.create_avg_duration', $this->getCurrentUserId(), 'round', $newId, [
            'game_id'          => (int) $gid,
            'duration_seconds' => $avg,
        ]);

        $this->setFlash('success', sprintf('Manche créée avec la durée moyenne (%s).', format_duration($avg)));
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }

    /**
     * Enregistrer/mettre à jour les scores d'une manche.
     */
    public function updateScores(string $id, string $gid, string $rid): void
    {
        $ctx = $this->checkAccess($id);

        $game = $this->game->findWithDetails((int) $gid);
        if (!$game || $game['space_id'] != $id) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Partie introuvable.']);
            }
            $this->setFlash('danger', 'Partie introuvable.');
            $this->redirect("/spaces/{$id}/games");
            return;
        }

        if ($this->isCompetitionProtected($game)) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.']);
            }
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
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

        $isCompleted = $round['status'] === 'completed';
        $spaceRole = $ctx['member']['role'];

        // Seuls admin/manager peuvent modifier une manche terminée
        if ($isCompleted && !in_array($spaceRole, ['admin', 'manager'])) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Cette manche est déjà terminée.']);
            }
            $this->setFlash('warning', 'Cette manche est déjà terminée.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        $this->validateCSRF();

        $scores = $_POST['scores'] ?? [];
        if (!empty($scores) || $game['win_condition'] === 'win_loss') {
            try {
                $this->roundScore->saveScores((int) $rid, $scores, $game['win_condition'], (int) $gid);

                // Marquer la manche comme terminée si elle ne l'est pas déjà
                if (!$isCompleted) {
                    $this->roundPause->endAllOpenPauses((int) $rid);
                    $this->round->updateStatus((int) $rid, 'completed');
                }

                $this->game->recalculateTotals((int) $gid);
            } catch (\Exception $e) {
                if ($this->isAjax()) {
                    $this->jsonResponse(['success' => false, 'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()]);
                }
                $this->setFlash('danger', 'Erreur lors de l\'enregistrement des scores.');
                $this->redirect("/spaces/{$id}/games/{$gid}");
                return;
            }
        }

        $msg = $isCompleted ? 'Scores corrigés.' : 'Scores enregistrés, manche terminée.';

        ActivityLog::logSpace((int) $id, $isCompleted ? 'round.scores_corrected' : 'round.scores_saved', $this->getCurrentUserId(), 'round', (int) $rid, ['game_id' => (int) $gid]);

        if ($this->isAjax()) {
            $this->jsonResponse(['success' => true, 'message' => $msg]);
        }

        $this->setFlash('success', $msg);
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

        if ($this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être modifiées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
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
        $allowed = ['in_progress', 'paused', 'completed'];

        if (!in_array($status, $allowed)) {
            $this->setFlash('danger', 'Statut invalide.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
            return;
        }

        // Gestion des pauses
        if ($status === 'paused' && $round['status'] === 'in_progress') {
            // Mettre en pause : créer un enregistrement de pause
            $this->roundPause->startPause((int) $rid);
        } elseif ($status === 'in_progress' && $round['status'] === 'paused') {
            // Reprendre : fermer la pause en cours
            $this->roundPause->endPause((int) $rid);
        } elseif ($status === 'completed') {
            // Terminer : fermer toutes les pauses ouvertes
            $this->roundPause->endAllOpenPauses((int) $rid);
        }

        $this->round->updateStatus((int) $rid, $status);

        if ($status === 'completed') {
            $this->game->recalculateTotals((int) $gid);
        }

        ActivityLog::logSpace((int) $id, 'round.status_change', $this->getCurrentUserId(), 'round', (int) $rid, ['status' => $status, 'game_id' => (int) $gid]);

        $labels = [
            'in_progress' => 'reprise',
            'paused'      => 'mise en pause',
            'completed'   => 'terminée',
        ];
        $label = $labels[$status] ?? $status;
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

        if ($this->isCompetitionProtected($game)) {
            $this->setFlash('danger', 'Les parties de compétition ne peuvent être supprimées que par l\'équipe de modération globale.');
            $this->redirect("/spaces/{$id}/games/{$gid}");
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
        $this->round->renumberRounds((int) $gid);
        $this->game->recalculateTotals((int) $gid);

        ActivityLog::logSpace((int) $id, 'round.delete', $this->getCurrentUserId(), 'round', (int) $rid, ['game_id' => (int) $gid]);

        $this->setFlash('success', 'Manche supprimée.');
        $this->redirect("/spaces/{$id}/games/{$gid}");
    }
}
