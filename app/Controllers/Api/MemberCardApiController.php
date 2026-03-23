<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\MemberCard;
use App\Models\Player;
use App\Models\ActivityLog;

/**
 * API REST pour les cartes de membre numériques.
 *
 * Accès : être membre de l'espace, ET soit être l'utilisateur lié au
 * joueur soit être admin/manager de l'espace.
 */
class MemberCardApiController extends ApiController
{
    private MemberCard $cardModel;
    private Player     $playerModel;

    public function __construct()
    {
        $this->cardModel   = new MemberCard();
        $this->playerModel = new Player();
    }

    // ---------------------------------------------------------------
    // GET /api/spaces/{id}/players/{pid}/card
    // ---------------------------------------------------------------
    public function show(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->error('Joueur introuvable.', 404);
        }

        $this->assertCanManageCard($player, (int) $id);

        $card = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        if (!$card) {
            $this->json(['success' => true, 'card' => null]);
        }

        $card['signature_valid'] = MemberCard::verify($card);

        $this->json(['success' => true, 'card' => $card]);
    }

    // ---------------------------------------------------------------
    // POST /api/spaces/{id}/players/{pid}/card
    // Génère une nouvelle carte (erreur si déjà existante).
    // ---------------------------------------------------------------
    public function generate(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->error('Joueur introuvable.', 404);
        }

        $this->assertCanManageCard($player, (int) $id);

        if ($this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id)) {
            $this->error('Une carte existe déjà. Utilisez l\'action régénérer.', 409);
        }

        $card = $this->cardModel->generate((int) $pid, (int) $id);
        $card['signature_valid'] = MemberCard::verify($card);

        ActivityLog::logSpace(
            (int) $id,
            'member_card.generate',
            $this->userId,
            'player',
            (int) $pid,
            ['reference' => $card['reference'], 'via' => 'api']
        );

        $this->json(['success' => true, 'card' => $card], 201);
    }

    // ---------------------------------------------------------------
    // POST /api/spaces/{id}/players/{pid}/card/regenerate
    // Invalide l'ancienne carte et en crée une nouvelle.
    // ---------------------------------------------------------------
    public function regenerate(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->error('Joueur introuvable.', 404);
        }

        $this->assertCanManageCard($player, (int) $id);

        $old  = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        $card = $this->cardModel->regenerate((int) $pid, (int) $id);
        $card['signature_valid'] = MemberCard::verify($card);

        ActivityLog::logSpace(
            (int) $id,
            'member_card.regenerate',
            $this->userId,
            'player',
            (int) $pid,
            [
                'old_reference' => $old ? $old['reference'] : null,
                'new_reference' => $card['reference'],
                'via' => 'api',
            ]
        );

        $this->json(['success' => true, 'card' => $card]);
    }

    // ---------------------------------------------------------------
    // PUT /api/spaces/{id}/players/{pid}/card
    // Active ou désactive la carte.
    // Body JSON : { "active": true|false }
    // ---------------------------------------------------------------
    public function toggle(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->error('Joueur introuvable.', 404);
        }

        $this->assertCanManageCard($player, (int) $id);

        $card = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        if (!$card) {
            $this->error('Aucune carte trouvée pour ce joueur.', 404);
        }

        $body   = $this->getJsonBody();
        $active = isset($body['active']) ? (bool) $body['active'] : true;
        $this->cardModel->setActive((int) $card['id'], $active);

        ActivityLog::logSpace(
            (int) $id,
            $active ? 'member_card.activate' : 'member_card.deactivate',
            $this->userId,
            'player',
            (int) $pid,
            ['reference' => $card['reference'], 'via' => 'api']
        );

        $updated = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        $updated['signature_valid'] = MemberCard::verify($updated);

        $this->json(['success' => true, 'card' => $updated]);
    }

    // ---------------------------------------------------------------
    // DELETE /api/spaces/{id}/players/{pid}/card
    // ---------------------------------------------------------------
    public function delete(string $id, string $pid): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $player = $this->playerModel->findActiveByIdInSpace((int) $pid, (int) $id);
        if (!$player) {
            $this->error('Joueur introuvable.', 404);
        }

        $this->assertCanManageCard($player, (int) $id);

        $card = $this->cardModel->findByPlayerAndSpace((int) $pid, (int) $id);
        if ($card) {
            $this->cardModel->delete((int) $card['id']);

            ActivityLog::logSpace(
                (int) $id,
                'member_card.delete',
                $this->userId,
                'player',
                (int) $pid,
                ['reference' => $card['reference'], 'via' => 'api']
            );
        }

        $this->json(['success' => true, 'message' => 'Carte supprimée.']);
    }

    // ---------------------------------------------------------------
    // Privé
    // ---------------------------------------------------------------

    /**
     * Vérifie que l'utilisateur authentifié peut gérer la carte du joueur.
     * Autorisé si : utilisateur lié au joueur OU admin/manager de l'espace.
     */
    private function assertCanManageCard(array $player, int $spaceId): void
    {
        if ((int) ($player['user_id'] ?? 0) === $this->userId) {
            return;
        }

        \App\Core\Session::set('user_id', $this->userId);
        \App\Core\Session::set('global_role', $this->userPayload['global_role'] ?? 'user');

        $member = \App\Core\Middleware::checkSpaceAccess($spaceId, $this->userId, ['admin', 'manager']);
        if (!$member) {
            $this->error('Vous n\'êtes pas autorisé à gérer cette carte.', 403);
        }
    }
}
