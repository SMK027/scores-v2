<div class="page-header">
    <h1>Parties</h1>
    <?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
        <a href="/spaces/<?= $currentSpace['id'] ?>/games/create" class="btn btn-primary">+ Nouvelle partie</a>
    <?php endif; ?>
</div>

<!-- Filtres -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/spaces/<?= $currentSpace['id'] ?>/games" class="d-flex gap-1 flex-wrap align-center">
            <select name="status" class="form-control" style="width:auto;">
                <option value="">Tous les statuts</option>
                <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                <option value="paused" <?= ($filters['status'] ?? '') === 'paused' ? 'selected' : '' ?>>En pause</option>
                <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Terminées</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
            </select>
            <select name="game_type_id" class="form-control" style="width:auto;">
                <option value="">Tous les jeux</option>
                <?php foreach ($gameTypes as $gt): ?>
                    <option value="<?= $gt['id'] ?>" <?= ($filters['game_type_id'] ?? '') == $gt['id'] ? 'selected' : '' ?>>
                        <?= e($gt['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Filtrer</button>
            <a href="/spaces/<?= $currentSpace['id'] ?>/games" class="btn btn-sm" style="color:var(--gray);">Réinitialiser</a>
        </form>
    </div>
</div>

<?php if (empty($games)): ?>
    <div class="empty-state">
        <div class="empty-icon">🎮</div>
        <p>Aucune partie trouvée.</p>
        <?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
            <a href="/spaces/<?= $currentSpace['id'] ?>/games/create" class="btn btn-primary">Créer une partie</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type de jeu</th>
                            <th>Statut</th>
                            <th>Joueurs</th>
                            <th>Créée par</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $game): ?>
                            <tr>
                                <td>
                                    <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>" class="font-bold">
                                        <?= e($game['game_type_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge <?= game_status_class($game['status']) ?>">
                                        <?= game_status_label($game['status']) ?>
                                    </span>
                                </td>
                                <td><?= $game['player_count'] ?></td>
                                <td class="text-muted"><?= e($game['creator_name'] ?? '') ?></td>
                                <td class="text-muted text-small"><?= time_ago($game['created_at']) ?></td>
                                <td class="text-right">
                                    <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>"
                                       class="btn btn-sm btn-outline">Voir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['lastPage'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pagination['lastPage']; $i++): ?>
                <?php
                    $queryParams = $_GET;
                    $queryParams['page'] = $i;
                    $queryString = http_build_query($queryParams);
                ?>
                <?php if ($i === $pagination['page']): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="/spaces/<?= $currentSpace['id'] ?>/games?<?= $queryString ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
