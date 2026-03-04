<div class="page-header">
    <h1>Tableau de bord</h1>
</div>

<!-- Statistiques rapides -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= $currentSpace['game_count'] ?? 0 ?></div>
        <div class="stat-label">Parties</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $currentSpace['player_count'] ?? 0 ?></div>
        <div class="stat-label">Joueurs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $currentSpace['game_type_count'] ?? 0 ?></div>
        <div class="stat-label">Types de jeux</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $currentSpace['member_count'] ?? 0 ?></div>
        <div class="stat-label">Membres</div>
    </div>
</div>

<!-- Actions rapides -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Actions rapides</h3>
    </div>
    <div class="card-body">
        <div class="btn-group">
            <a href="/spaces/<?= $currentSpace['id'] ?>/games/create" class="btn btn-primary">🎮 Nouvelle partie</a>
            <a href="/spaces/<?= $currentSpace['id'] ?>/players/create" class="btn btn-outline">👤 Ajouter un joueur</a>
            <a href="/spaces/<?= $currentSpace['id'] ?>/game-types/create" class="btn btn-outline">🃏 Nouveau type de jeu</a>
        </div>
    </div>
</div>

<!-- Dernières parties -->
<div class="card">
    <div class="card-header">
        <h3>Dernières parties</h3>
        <a href="/spaces/<?= $currentSpace['id'] ?>/games" class="btn btn-sm btn-outline">Voir tout</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentGames)): ?>
            <div class="empty-state">
                <p>Aucune partie pour le moment.</p>
                <a href="/spaces/<?= $currentSpace['id'] ?>/games/create" class="btn btn-primary btn-sm">Créer une partie</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type de jeu</th>
                            <th>Statut</th>
                            <th>Joueurs</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGames as $game): ?>
                            <tr>
                                <td>
                                    <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>">
                                        <?= e($game['game_type_name']) ?>
                                    </a>
                                </td>
                                <td><span class="badge <?= game_status_class($game['status']) ?>"><?= game_status_label($game['status']) ?></span></td>
                                <td><?= $game['player_count'] ?? 0 ?></td>
                                <td class="text-muted text-small"><?= time_ago($game['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($currentSpace['description']): ?>
<div class="card mt-2">
    <div class="card-header">
        <h3>À propos</h3>
    </div>
    <div class="card-body">
        <p><?= nl2br(e($currentSpace['description'])) ?></p>
        <p class="text-muted text-small">Créé par <?= e($currentSpace['creator_name'] ?? 'inconnu') ?> le <?= format_date($currentSpace['created_at'], 'd/m/Y') ?></p>
    </div>
</div>
<?php endif; ?>
