<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Models\MemberCard;
use App\Models\Player;
use App\Models\Space;
use App\Models\ActivityLog;

/**
 * Contrôleur des cartes de membre numériques.
 *
 * Accès aux actions de gestion (générer, désactiver, régénérer, supprimer) :
 *   - l'utilisateur connecté lié au joueur
 *   - un admin ou manager de l'espace
 *
 * La vérification publique (/cards/{ref}) est accessible sans authentification.
 */
class MemberCardController extends Controller
{
    private MemberCard $cardModel;
    private Player     $playerModel;
    private Space      $spaceModel;

    public function __construct()
    {
        $this->cardModel   = new MemberCard();
        $this->playerModel = new Player();
        $this->spaceModel  = new Space();
    }

    // ----------------------------------------------------------------
    // Affichage de la carte (ou formulaire de génération)
    // ----------------------------------------------------------------

    /**
     * GET /spaces/{id}/players/{pid}/card
     */
    public function show(string $id, string $pid): void
    {
        [$space, $player] = $this->resolveContext($id, $pid);

        $card = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        $signatureValid = $card ? MemberCard::verify($card) : null;

        $this->render('players/member_card', [
            'title'          => 'Carte de membre — ' . $player['name'],
            'currentSpace'   => $space,
            'spaceRole'      => $this->getSpaceRole((int) $id),
            'activeMenu'     => 'players',
            'player'         => $player,
            'card'           => $card,
            'signatureValid' => $signatureValid,
            'canManage'      => $this->canManage($player, (int) $id),
        ]);
    }

    // ----------------------------------------------------------------
    // Actions POST (protégées CSRF)
    // ----------------------------------------------------------------

    /**
     * POST /spaces/{id}/players/{pid}/card/generate
     */
    public function generate(string $id, string $pid): void
    {
        $this->validateCSRF();
        [$space, $player] = $this->resolveContext($id, $pid, mustManage: true);

        if ($this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id)) {
            $this->setFlash('warning', 'Une carte existe déjà pour ce joueur. Utilisez Régénérer.');
            $this->redirect("/spaces/{$id}/players/{$pid}/card");
        }

        $card = $this->cardModel->generate((int) $pid, (int) $id);

        ActivityLog::logSpace(
            (int) $id,
            'member_card.generate',
            $this->getCurrentUserId(),
            'player',
            (int) $pid,
            ['reference' => $card['reference']]
        );

        $this->setFlash('success', 'Carte de membre générée avec succès.');
        $this->redirect("/spaces/{$id}/players/{$pid}/card");
    }

    /**
     * POST /spaces/{id}/players/{pid}/card/toggle
     * Paramètre POST attendu : action = 'activate' | 'deactivate'
     */
    public function toggle(string $id, string $pid): void
    {
        $this->validateCSRF();
        [$space, $player] = $this->resolveContext($id, $pid, mustManage: true);

        $card = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        if (!$card) {
            $this->setFlash('danger', 'Aucune carte trouvée pour ce joueur.');
            $this->redirect("/spaces/{$id}/players/{$pid}/card");
        }

        $activate = ($_POST['action'] ?? '') === 'activate';
        $this->cardModel->setActive((int) $card['id'], $activate);

        ActivityLog::logSpace(
            (int) $id,
            $activate ? 'member_card.activate' : 'member_card.deactivate',
            $this->getCurrentUserId(),
            'player',
            (int) $pid,
            ['reference' => $card['reference']]
        );

        $label = $activate ? 'activée' : 'désactivée';
        $this->setFlash('success', "Carte {$label} avec succès.");
        $this->redirect("/spaces/{$id}/players/{$pid}/card");
    }

    /**
     * POST /spaces/{id}/players/{pid}/card/regenerate
     * Invalide l'ancienne carte et en crée une nouvelle.
     */
    public function regenerate(string $id, string $pid): void
    {
        $this->validateCSRF();
        [$space, $player] = $this->resolveContext($id, $pid, mustManage: true);

        $old = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        $card = $this->cardModel->regenerate((int) $pid, (int) $id);

        ActivityLog::logSpace(
            (int) $id,
            'member_card.regenerate',
            $this->getCurrentUserId(),
            'player',
            (int) $pid,
            [
                'old_reference' => $old ? $old['reference'] : null,
                'new_reference' => $card['reference'],
            ]
        );

        $this->setFlash('success', 'Carte régénérée. L\'ancienne référence est désormais invalide.');
        $this->redirect("/spaces/{$id}/players/{$pid}/card");
    }

    /**
     * POST /spaces/{id}/players/{pid}/card/delete
     */
    public function delete(string $id, string $pid): void
    {
        $this->validateCSRF();
        [$space, $player] = $this->resolveContext($id, $pid, mustManage: true);

        $card = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        if ($card) {
            $this->cardModel->delete((int) $card['id']);

            ActivityLog::logSpace(
                (int) $id,
                'member_card.delete',
                $this->getCurrentUserId(),
                'player',
                (int) $pid,
                ['reference' => $card['reference']]
            );
        }

        $this->setFlash('success', 'Carte de membre supprimée.');
        $this->redirect("/spaces/{$id}/players/{$pid}/card");
    }

    // ----------------------------------------------------------------
    // Vérification publique
    // ----------------------------------------------------------------

    /**
     * GET /cards
     * Page de vérification avec recherche par reference (?ref=...).
     * Sans paramètre, affiche le formulaire vide.
     */
    public function verifySearch(): void
    {
        $ref = trim($_GET['ref'] ?? '');
        if ($ref !== '') {
            $clean = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $ref));
            $this->redirect('/cards/' . rawurlencode($clean));
        }

        $this->render('cards/verify', [
            'title'          => 'Vérification de carte de membre',
            'card'           => null,
            'ref'            => '',
            'signatureValid' => null,
        ]);
    }

    /**
     * GET /cards/{ref}
     * Page de vérification publique d'une carte (sans authentification).
     */
    public function verifyPublic(string $ref): void
    {
        // Nettoyage de la référence (alphanumérique + tirets uniquement)
        $ref = preg_replace('/[^A-Z0-9\-]/i', '', strtoupper(trim($ref)));

        $card = $ref ? $this->cardModel->findByReference($ref) : null;
        $signatureValid = $card ? MemberCard::verify($card) : null;

        $this->render('cards/verify', [
            'title'          => 'Vérification de carte de membre',
            'card'           => $card,
            'ref'            => htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'),
            'signatureValid' => $signatureValid,
        ]);
    }

    // ----------------------------------------------------------------
    // Méthodes privées
    // ----------------------------------------------------------------

    /**
     * Résout l'espace et le joueur, vérifie les accès.
     * Si mustManage = true, l'utilisateur doit être admin/manager OU être
     * l'utilisateur lié au joueur.
     *
     * @return array{array, array}  [$space, $player]
     */
    private function resolveContext(string $spaceId, string $pid, bool $mustManage = false): array
    {
        $this->requireAuth();

        $space = $this->spaceModel->find((int) $spaceId);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
        }

        // Accès minimum : être membre de l'espace
        $member = Middleware::checkSpaceAccess(
            (int) $spaceId,
            $this->getCurrentUserId(),
            ['admin', 'manager', 'member', 'guest']
        );
        if (!$member) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/spaces');
        }

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $spaceId);
        if (!$player) {
            $this->setFlash('danger', 'Joueur introuvable.');
            $this->redirect("/spaces/{$spaceId}/players");
        }

        if ($mustManage && !$this->canManage($player, (int) $spaceId)) {
            $this->setFlash('danger', 'Vous n\'êtes pas autorisé à gérer cette carte.');
            $this->redirect("/spaces/{$spaceId}/players/{$pid}/card");
        }

        return [$space, $player];
    }

    /**
     * Vérifie si l'utilisateur courant peut gérer la carte d'un joueur.
     * Autorisé si : admin/manager de l'espace OU utilisateur lié au joueur.
     */
    private function canManage(array $player, int $spaceId): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        // Utilisateur directement lié au joueur
        if ((int) ($player['user_id'] ?? 0) === $userId) {
            return true;
        }

        // Admin ou manager de l'espace
        $member = Middleware::checkSpaceAccess($spaceId, $userId, ['admin', 'manager']);
        return $member !== null;
    }

    /**
     * Retourne le rôle de l'utilisateur courant dans l'espace (ou null).
     */
    private function getSpaceRole(int $spaceId): ?string
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return null;
        }
        $member = Middleware::checkSpaceAccess($spaceId, $userId, ['admin', 'manager', 'member', 'guest']);
        return $member ? $member['role'] : null;
    }
}
