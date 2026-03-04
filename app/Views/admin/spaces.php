<div class="page-header">
    <h1>📦 Gestion des espaces</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
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
                                <a href="/spaces/<?= $space['id'] ?>" class="btn btn-sm btn-outline">Voir</a>
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
