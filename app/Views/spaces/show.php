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
            <?php if (!empty($linkedPlayer)): ?>
                <a href="/spaces/<?= $currentSpace['id'] ?>/players/<?= $linkedPlayer['id'] ?>/card" class="btn btn-outline">🪪 Ma carte de membre</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Prochaine competition -->
<div class="card mb-3">
    <div class="card-header">
        <h3>🏆 Prochaine competition</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($nextCompetition)): ?>
            <div class="d-flex justify-between align-center flex-wrap" style="gap:0.75rem;">
                <div>
                    <p class="font-bold" style="margin:0;">
                        <?= e($nextCompetition['name']) ?>
                        <span class="badge badge-warning" style="margin-left:0.35rem;">Planifiee</span>
                    </p>
                    <p class="text-muted text-small" style="margin:0.35rem 0 0;">
                        Debut: <?= date('d/m/Y H:i', strtotime($nextCompetition['starts_at'])) ?>
                        <?php if (!empty($nextCompetition['ends_at'])): ?>
                            • Fin: <?= date('d/m/Y H:i', strtotime($nextCompetition['ends_at'])) ?>
                        <?php endif; ?>
                        • Sessions: <?= (int) ($nextCompetition['session_count'] ?? 0) ?>
                    </p>
                </div>
                <a href="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $nextCompetition['id'] ?>" class="btn btn-primary btn-sm">
                    Acceder rapidement
                </a>
            </div>
        <?php else: ?>
            <div class="d-flex justify-between align-center flex-wrap" style="gap:0.75rem;">
                <p class="text-muted" style="margin:0;">Aucune prochaine competition configuree pour le moment.</p>
                <a href="/spaces/<?= $currentSpace['id'] ?>/competitions" class="btn btn-outline btn-sm">Voir les competitions</a>
            </div>
        <?php endif; ?>
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
<?php if ($currentSpace['created_by'] != current_user_id()): ?>
<div class="card mt-2" style="border-color:var(--warning,#f0ad4e);">
    <div class="card-body d-flex justify-between align-center">
        <div>
            <strong>Quitter cet espace</strong>
            <p class="text-muted text-small" style="margin:0.25rem 0 0;">Vous ne pourrez plus accéder à cet espace ni à ses données.</p>
        </div>
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/leave">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-warning" data-confirm="Voulez-vous vraiment quitter cet espace ?">Quitter l'espace</button>
        </form>
    </div>
</div>
<?php endif; ?>