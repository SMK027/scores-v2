<div class="page-header">
    <h1>🃏 Types de jeux globaux</h1>
    <div class="d-flex gap-1">
        <a href="/admin" class="btn btn-outline">← Retour</a>
        <a href="/admin/game-types/create" class="btn btn-primary">+ Nouveau type global</a>
    </div>
</div>

<p class="text-muted mb-2">
    Les types de jeux globaux sont disponibles dans <strong>tous les espaces</strong>.
    Seuls les administrateurs globaux peuvent les gérer.
</p>

<?php if (empty($gameTypes)): ?>
    <div class="empty-state">
        <div class="empty-icon">🃏</div>
        <p>Aucun type de jeu global défini.</p>
        <a href="/admin/game-types/create" class="btn btn-primary">Créer un type de jeu global</a>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($gameTypes as $gt): ?>
            <div class="card">
                <div class="card-body">
                    <h3><?= e($gt['name']) ?></h3>
                    <?php if ($gt['description']): ?>
                        <p class="text-muted text-small"><?= e(truncate($gt['description'], 100)) ?></p>
                    <?php endif; ?>
                    <div class="d-flex align-center gap-1 flex-wrap mt-1">
                        <span class="badge badge-warning">Global</span>
                        <span class="badge badge-info"><?= win_condition_label($gt['win_condition']) ?></span>
                        <span class="text-muted text-small"><?= $gt['min_players'] ?>-<?= $gt['max_players'] ?? '∞' ?> joueurs</span>
                        <span class="text-muted text-small">🎮 <?= $gt['game_count'] ?> partie(s)</span>
                        <?php if (!empty($gt['avg_round_duration'])): ?>
                            <span class="text-muted text-small">⏱️ ~<?= format_duration((int) $gt['avg_round_duration']) ?>/manche</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="/admin/game-types/<?= $gt['id'] ?>/edit" class="btn btn-sm btn-outline">Modifier</a>
                    <form method="POST" action="/admin/game-types/<?= $gt['id'] ?>/delete" style="display:inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Supprimer ce type de jeu global ? Il sera retiré de tous les espaces.">Supprimer</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
