<div class="page-header">
    <h1>Joueurs</h1>
    <?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
        <a href="/spaces/<?= $currentSpace['id'] ?>/players/create" class="btn btn-primary">+ Ajouter un joueur</a>
    <?php endif; ?>
</div>

<?php if (empty($players)): ?>
    <div class="empty-state">
        <div class="empty-icon">👥</div>
        <p>Aucun joueur enregistré.<br>Ajoutez des joueurs pour commencer vos parties !</p>
        <?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
            <a href="/spaces/<?= $currentSpace['id'] ?>/players/create" class="btn btn-primary">Ajouter un joueur</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Compte lié</th>
                            <th>Manches jouées</th>
                            <th>Manches gagnées</th>
                            <th>Taux</th>
                            <?php if (in_array($spaceRole, ['admin', 'manager']) || is_authenticated()): ?>
                                <th class="text-right">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <td class="font-bold"><?= e($player['name']) ?></td>
                                <td class="text-muted">
                                    <?= $player['linked_username'] ? e($player['linked_username']) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td><?= $player['rounds_played'] ?></td>
                                <td><?= $player['rounds_won'] ?></td>
                                <td>
                                    <?php if ($player['rounds_played'] > 0): ?>
                                        <span class="badge badge-success">
                                            <?= round(($player['rounds_won'] / $player['rounds_played']) * 100) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array($spaceRole, ['admin', 'manager'])): ?>
                                    <td class="text-right">
                                        <div class="btn-group">
                                            <a href="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/card"
                                               class="btn btn-sm btn-outline" title="Carte de membre">🪪</a>
                                            <a href="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/edit"
                                               class="btn btn-sm btn-outline">Modifier</a>
                                            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/delete" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        data-confirm="Supprimer ce joueur ?">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php elseif (!empty($player['user_id']) && (int)$player['user_id'] === (int)current_user_id()): ?>
                                    <td class="text-right">
                                        <a href="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/card"
                                           class="btn btn-sm btn-outline" title="Ma carte de membre">🪪 Ma carte</a>
                                    </td>
                                <?php else: ?>
                                    <td></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
