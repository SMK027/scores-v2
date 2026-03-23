<?php
/**
 * Vue publique : Vérification d'une carte de membre.
 * Accessible sans authentification via GET /cards/{ref}
 *
 * Variables disponibles :
 *   $card           ?array   Données de la carte + joueur + espace (null si non trouvée)
 *   $ref            string   Référence saisie / passée dans l'URL (échappée)
 *   $signatureValid ?bool
 */
?>

<div class="page-header" style="justify-content:center; text-align:center;">
    <div>
        <h1>🪪 Vérification de carte de membre</h1>
        <p class="text-muted" style="margin:0;">Vérifiez l'authenticité d'une carte de membre Scores.</p>
    </div>
</div>

<!-- Formulaire de recherche par référence -->
<div style="max-width:520px; margin: 0 auto 2rem;">
    <form method="GET" action="/cards" class="card">
        <div class="card-body">
            <label for="ref_input" class="form-label">Référence de la carte</label>
            <div style="display:flex; gap:0.5rem;">
                <input type="text" id="ref_input" name="ref"
                       class="form-control" placeholder="SC-20260323-A3F91CB2"
                       value="<?= $ref ?>"
                       style="flex:1; font-family:monospace; text-transform:uppercase;">
                <button type="submit" class="btn btn-primary">Vérifier</button>
            </div>
        </div>
    </form>
</div>

<?php if ($ref !== '' && !$card): ?>
    <!-- Aucune carte trouvée -->
    <div style="max-width:520px; margin:0 auto;">
        <div class="empty-state">
            <div class="empty-icon">❓</div>
            <p>Aucune carte trouvée pour la référence <strong><?= $ref ?></strong>.</p>
            <p class="text-muted" style="font-size:0.85rem;">
                La carte n'existe pas, a été supprimée, ou la référence est incorrecte.
            </p>
        </div>
    </div>

<?php elseif ($card): ?>
    <!-- Résultat de vérification -->
    <div style="max-width:520px; margin:0 auto;">

        <!-- Statut de vérification -->
        <div class="card" style="margin-bottom:1rem; border-left: 4px solid <?= $signatureValid ? '#22c55e' : '#ef4444' ?>;">
            <div class="card-body">
                <?php if ($signatureValid && $card['is_active']): ?>
                    <div style="color:#22c55e; font-weight:700; font-size:1.05rem;">✔ Carte valide et active</div>
                    <p style="margin:0.4rem 0 0; font-size:0.85rem; color:var(--text-muted,#aaa);">
                        La signature numérique est authentique. Cette carte n'a pas été modifiée.
                    </p>
                <?php elseif ($signatureValid && !$card['is_active']): ?>
                    <div style="color:#f59e0b; font-weight:700; font-size:1.05rem;">⚠ Carte valide mais désactivée</div>
                    <p style="margin:0.4rem 0 0; font-size:0.85rem; color:var(--text-muted,#aaa);">
                        La signature est authentique, mais cette carte a été désactivée par son propriétaire ou un administrateur.
                    </p>
                <?php else: ?>
                    <div style="color:#ef4444; font-weight:700; font-size:1.05rem;">✖ Signature invalide</div>
                    <p style="margin:0.4rem 0 0; font-size:0.85rem; color:var(--text-muted,#aaa);">
                        La signature numérique ne correspond pas. Cette carte a peut-être été falsifiée ou corrompue.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rendu visuel -->
        <div class="member-card <?= $card['is_active'] ? 'member-card--active' : 'member-card--inactive' ?>">
            <div class="member-card__header">
                <span class="member-card__logo">🎲 Scores</span>
                <span class="member-card__space"><?= e($card['space_name']) ?></span>
            </div>

            <div class="member-card__body">
                <div class="member-card__avatar">
                    <?= strtoupper(mb_substr($card['player_name'], 0, 1)) ?>
                </div>
                <div class="member-card__info">
                    <div class="member-card__name"><?= e($card['player_name']) ?></div>
                    <div class="member-card__role">
                        <?= e(App\Models\MemberCard::roleLabel($card['space_role'])) ?>
                    </div>
                    <div class="member-card__joined">
                        Membre depuis le
                        <?= date('d/m/Y', strtotime($card['player_joined_at'])) ?>
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
                <?php elseif ($signatureValid): ?>
                    <span class="badge badge-success" style="font-size:0.7rem;">✔ VALIDE</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Signature -->
        <div class="card" style="margin-top:1rem;">
            <div class="card-body">
                <h3 style="font-size:0.9rem; margin-bottom:0.6rem;">🔏 Empreinte cryptographique</h3>
                <div style="font-family:monospace; font-size:0.72rem; word-break:break-all;
                            background:var(--bg-secondary,#1a1a2e); padding:0.6rem 0.8rem;
                            border-radius:6px; color:var(--text-muted,#aaa);">
                    <?= e($card['signature']) ?>
                </div>
                <p style="font-size:0.78rem; color:var(--text-muted,#888); margin-top:0.6rem; margin-bottom:0;">
                    Générée le <?= date('d/m/Y à H:i', strtotime($card['created_at'])) ?>
                </p>
            </div>
        </div>

    </div>
<?php endif; ?>

<style>
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
.member-card--inactive { filter: grayscale(60%); opacity: 0.75; }
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
.member-card__joined { font-size: 0.75rem; opacity: 0.65; margin-top: 0.3rem; }
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
