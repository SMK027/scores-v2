<?php
/**
 * Vue : Carte de membre numérique d'un joueur.
 *
 * Variables disponibles :
 *   $player         array    Données du joueur
 *   $card           ?array   Données de la carte (null si inexistante)
 *   $signatureValid ?bool    true/false/null
 *   $canManage      bool     Peut gérer la carte
 *   $currentSpace   array
 *   $spaceRole      ?string
 */
?>

<div class="page-header">
    <div>
        <a href="/spaces/<?= $currentSpace['id'] ?>/players" class="btn btn-sm btn-outline" style="margin-bottom:0.5rem;">
            ← Joueurs
        </a>
        <h1>Carte de membre — <?= e($player['name']) ?></h1>
    </div>
</div>

<?php if (!$card): ?>
    <!-- ── État vide ──────────────────────────────────────────── -->
    <div class="empty-state">
        <div class="empty-icon">🪪</div>
        <p>Aucune carte de membre n'a encore été générée pour <strong><?= e($player['name']) ?></strong>.</p>
        <?php if ($canManage): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/card/generate">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Générer la carte</button>
            </form>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ── Carte numérique ────────────────────────────────────── -->
    <div style="max-width:520px; margin: 0 auto 2rem;">

        <!-- Rendu visuel de la carte -->
        <div class="member-card <?= $card['is_active'] ? 'member-card--active' : 'member-card--inactive' ?>">
            <div class="member-card__header">
                <span class="member-card__logo">🎲 Scores</span>
                <span class="member-card__space"><?= e($card['space_name'] ?? $currentSpace['name']) ?></span>
            </div>

            <div class="member-card__body">
                <div class="member-card__avatar">
                    <?= strtoupper(mb_substr($player['name'], 0, 1)) ?>
                </div>
                <div class="member-card__info">
                    <div class="member-card__name"><?= e($player['name']) ?></div>
                    <div class="member-card__role">
                        <?= e(App\Models\MemberCard::roleLabel($card['space_role'] ?? 'joueur')) ?>
                    </div>
                    <div class="member-card__joined">
                        Membre depuis le
                        <?= date('d/m/Y', strtotime($player['created_at'])) ?>
                    </div>
                </div>
            </div>

            <div class="member-card__footer">
                <div class="member-card__ref">
                    <span class="member-card__ref-label">Référence</span>
                    <span class="member-card__ref-value"><?= e($card['reference']) ?></span>
                </div>
                <?php if (!$card['is_active']): ?>
                    <span class="badge badge-danger" style="font-size:0.7rem;">DÉSACTIVÉE</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Signature & statut -->
        <div class="card" style="margin-top:1rem;">
            <div class="card-body">
                <h3 style="font-size:0.95rem; margin-bottom:0.75rem;">🔏 Signature numérique</h3>
                <div style="font-family:monospace; font-size:0.72rem; word-break:break-all;
                            background:var(--bg-secondary, #1a1a2e); padding:0.6rem 0.8rem;
                            border-radius:6px; color:var(--text-muted, #aaa); margin-bottom:0.6rem;">
                    <?= e($card['signature']) ?>
                </div>
                <?php if ($signatureValid === true): ?>
                    <span class="badge badge-success">✔ Signature valide</span>
                <?php elseif ($signatureValid === false): ?>
                    <span class="badge badge-danger">✖ Signature invalide — carte compromise</span>
                <?php endif; ?>

                <p style="font-size:0.8rem; color:var(--text-muted,#888); margin-top:0.75rem; margin-bottom:0;">
                    Générée le <?= date('d/m/Y à H:i', strtotime($card['created_at'])) ?>
                    &mdash; Dernière mise à jour le <?= date('d/m/Y à H:i', strtotime($card['updated_at'])) ?>
                </p>

                <p style="font-size:0.8rem; margin-top:0.5rem; margin-bottom:0;">
                    <a href="/cards/<?= urlencode($card['reference']) ?>" target="_blank">
                        🔗 Lien de vérification publique
                    </a>
                </p>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($canManage): ?>
            <div class="card" style="margin-top:1rem;">
                <div class="card-body">
                    <h3 style="font-size:0.95rem; margin-bottom:0.75rem;">Actions</h3>
                    <div class="btn-group" style="flex-wrap:wrap; gap:0.5rem;">

                        <?php if ($card['is_active']): ?>
                            <!-- Désactiver -->
                            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/card/toggle">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="btn btn-sm btn-outline"
                                        data-confirm="Désactiver cette carte ? Elle ne sera plus considérée comme valide.">
                                    ⏸ Désactiver
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Activer -->
                            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/card/toggle">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn btn-sm btn-outline">
                                    ▶ Activer
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Régénérer -->
                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/card/regenerate">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline"
                                    data-confirm="Régénérer la carte ? L'ancienne référence sera définitivement invalidée.">
                                🔄 Régénérer
                            </button>
                        </form>

                        <!-- Supprimer -->
                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/card/delete">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    data-confirm="Supprimer définitivement cette carte ?">
                                🗑 Supprimer
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<style>
/* ── Carte de membre ───────────────────────────────────────────────── */
.member-card {
    border-radius: 14px;
    padding: 1.5rem;
    background: linear-gradient(135deg, #1e3768 0%, #0d1f45 60%, #16213e 100%);
    color: #fff;
    box-shadow: 0 8px 32px rgba(0,0,0,0.45);
    position: relative;
    overflow: hidden;
}
.member-card::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
}
.member-card--inactive {
    filter: grayscale(60%);
    opacity: 0.75;
}
.member-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    font-size: 0.8rem;
    opacity: 0.8;
}
.member-card__logo { font-weight: 700; font-size: 1rem; }
.member-card__space { font-size: 0.75rem; }
.member-card__body {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.member-card__avatar {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; font-weight: 700;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.25);
}
.member-card__name {
    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.member-card__role {
    font-size: 0.8rem;
    opacity: 0.75;
    margin-top: 0.2rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.member-card__joined {
    font-size: 0.75rem;
    opacity: 0.65;
    margin-top: 0.3rem;
}
.member-card__footer {
    border-top: 1px solid rgba(255,255,255,0.12);
    padding-top: 0.85rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}
.member-card__ref { display: flex; flex-direction: column; gap: 0.15rem; }
.member-card__ref-label { font-size: 0.65rem; opacity: 0.55; text-transform: uppercase; letter-spacing: 0.1em; }
.member-card__ref-value { font-family: monospace; font-size: 0.9rem; font-weight: 600; letter-spacing: 0.05em; }
</style>
