<div class="page-header">
    <h1>Types de jeux</h1>
    <?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
        <a href="/spaces/<?= $currentSpace['id'] ?>/game-types/create" class="btn btn-primary">+ Nouveau type</a>
    <?php endif; ?>
</div>

<?php if (empty($gameTypes)): ?>
    <div class="empty-state">
        <div class="empty-icon">🃏</div>
        <p>Aucun type de jeu défini.<br>Ajoutez vos premiers jeux (Tarot, Belotte, Yams, etc.) !</p>
        <?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
            <a href="/spaces/<?= $currentSpace['id'] ?>/game-types/create" class="btn btn-primary">Créer un type de jeu</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($gameTypes as $gt): ?>
            <?php $isGlobal = !empty($gt['is_global']); ?>
            <div class="card">
                <div class="card-body">
                    <h3><?= e($gt['name']) ?></h3>
                    <?php if ($gt['description']): ?>
                        <p class="text-muted text-small"><?= e(truncate($gt['description'], 100)) ?></p>
                    <?php endif; ?>
                    <div class="d-flex align-center gap-1 flex-wrap mt-1">
                        <?php if ($isGlobal): ?>
                            <span class="badge badge-warning">Global</span>
                        <?php endif; ?>
                        <span class="badge badge-info"><?= win_condition_label($gt['win_condition']) ?></span>
                        <span class="text-muted text-small"><?= $gt['min_players'] ?>-<?= $gt['max_players'] ?? '∞' ?> joueurs</span>
                        <span class="text-muted text-small">🎮 <?= $gt['game_count'] ?> partie(s)</span>
                        <?php if (!empty($gt['avg_round_duration'])): ?>
                            <span class="text-muted text-small">⏱️ ~<?= format_duration((int) $gt['avg_round_duration']) ?>/manche</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$isGlobal && in_array($spaceRole, ['admin', 'manager'])): ?>
                    <div class="card-footer">
                        <a href="/spaces/<?= $currentSpace['id'] ?>/game-types/<?= $gt['id'] ?>/edit" class="btn btn-sm btn-outline">Modifier</a>
                        <a href="/spaces/<?= $currentSpace['id'] ?>/game-types/<?= $gt['id'] ?>/replace" class="btn btn-sm btn-outline-warning">Remplacer</a>
                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/game-types/<?= $gt['id'] ?>/delete" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    data-confirm="Supprimer ce type de jeu ?">Supprimer</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
