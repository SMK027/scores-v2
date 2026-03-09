<div class="page-header">
    <h1>📦 Gestion des espaces</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/spaces" class="d-flex gap-1 flex-wrap align-center">
            <input type="text" name="search" class="form-control" placeholder="Rechercher par nom ou créateur…"
                   value="<?= e($search ?? '') ?>" style="max-width:250px;">
            <select name="status" class="form-control" style="max-width:200px;">
                <option value="">Tous les statuts</option>
                <option value="restricted" <?= ($status ?? '') === 'restricted' ? 'selected' : '' ?>>🔒 Restreints</option>
                <option value="deletion" <?= ($status ?? '') === 'deletion' ? 'selected' : '' ?>>💣 Suppression prévue</option>
                <option value="clean" <?= ($status ?? '') === 'clean' ? 'selected' : '' ?>>✅ Sans restriction</option>
            </select>
            <select name="sort" class="form-control" style="max-width:200px;">
                <option value="">Tri par défaut</option>
                <option value="name" <?= ($sort ?? '') === 'name' ? 'selected' : '' ?>>Nom (A→Z)</option>
                <option value="members_desc" <?= ($sort ?? '') === 'members_desc' ? 'selected' : '' ?>>Membres ↓</option>
                <option value="members_asc" <?= ($sort ?? '') === 'members_asc' ? 'selected' : '' ?>>Membres ↑</option>
                <option value="games_desc" <?= ($sort ?? '') === 'games_desc' ? 'selected' : '' ?>>Parties ↓</option>
                <option value="games_asc" <?= ($sort ?? '') === 'games_asc' ? 'selected' : '' ?>>Parties ↑</option>
                <option value="oldest" <?= ($sort ?? '') === 'oldest' ? 'selected' : '' ?>>Plus ancien</option>
            </select>
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <?php if (!empty($search) || !empty($status) || !empty($sort)): ?>
                <a href="/admin/spaces" class="btn btn-outline">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Espace</th>
                        <th>Créateur</th>
                        <th class="text-right">Membres</th>
                        <th class="text-right">Parties</th>
                        <th>Créé le</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spaces as $space): ?>
                        <tr>
                            <td class="text-muted"><?= $space['id'] ?></td>
                            <td>
                                <a href="/spaces/<?= $space['id'] ?>"><strong><?= e($space['name']) ?></strong></a>
                                <?php if (!empty($space['description'])): ?>
                                    <div class="text-muted text-small"><?= e(truncate($space['description'], 60)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= e($space['owner_name']) ?></td>
                            <td class="text-right"><?= $space['member_count'] ?></td>
                            <td class="text-right"><?= $space['game_count'] ?></td>
                            <td class="text-muted text-small"><?= format_date($space['created_at']) ?></td>
                            <td class="text-right">
                                <div class="d-flex gap-1 justify-end">
                                    <a href="/spaces/<?= $space['id'] ?>" class="btn btn-sm btn-outline">Voir</a>
                                    <a href="/admin/spaces/<?= $space['id'] ?>/restrictions" class="btn btn-sm btn-outline<?= !empty($space['restrictions']) ? '-danger' : '' ?>">
                                        <?= !empty($space['restrictions']) ? '🔒 Restreint' : '🔒 Restrictions' ?>
                                    </a>
                                    <a href="/admin/spaces/<?= $space['id'] ?>/schedule-deletion" class="btn btn-sm btn-outline<?= !empty($space['scheduled_deletion_at']) ? '-danger' : '' ?>">
                                        <?= !empty($space['scheduled_deletion_at']) ? '💣 Suppression prévue' : '💣 Suppression' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (empty($spaces)): ?>
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <p>Aucun espace créé.</p>
    </div>
<?php endif; ?>
