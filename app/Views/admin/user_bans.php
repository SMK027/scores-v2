<div class="page-header">
    <h1>🚫 Bannissements de comptes</h1>
    <div class="d-flex gap-1">
        <a href="/admin" class="btn btn-outline">← Retour</a>
        <a href="/admin/bans/users/create" class="btn btn-primary">+ Bannir un compte</a>
    </div>
</div>

<!-- Filtres -->
<div class="d-flex gap-1 mb-3 flex-wrap">
    <a href="/admin/bans/users" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
    <a href="/admin/bans/users?filter=active" class="btn btn-sm <?= $filter === 'active' ? 'btn-primary' : 'btn-outline' ?>">Actifs</a>
    <a href="/admin/bans/users?filter=expired" class="btn btn-sm <?= $filter === 'expired' ? 'btn-primary' : 'btn-outline' ?>">Expirés</a>
    <a href="/admin/bans/users?filter=revoked" class="btn btn-sm <?= $filter === 'revoked' ? 'btn-primary' : 'btn-outline' ?>">Annulés</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($bans)): ?>
            <div class="empty-state">
                <div class="empty-icon">🚫</div>
                <p>Aucun bannissement trouvé.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Raison</th>
                            <th>Banni par</th>
                            <th>Date</th>
                            <th>Expiration</th>
                            <th>Statut</th>
                            <?php if (in_array(current_global_role(), ['admin', 'superadmin'])): ?>
                                <th class="text-right">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bans as $ban): ?>
                            <?php
                                $isExpired = $ban['expires_at'] && strtotime($ban['expires_at']) <= time();
                                $isActive = $ban['is_active'] && !$isExpired;
                                $isRevoked = !$ban['is_active'];
                            ?>
                            <tr style="<?= $isRevoked ? 'opacity:0.5;' : ($isExpired ? 'opacity:0.7;' : '') ?>">
                                <td>
                                    <strong><?= e($ban['user_username']) ?></strong>
                                    <div class="text-muted text-small"><?= e($ban['user_email']) ?></div>
                                </td>
                                <td style="max-width:250px;">
                                    <span title="<?= e($ban['reason']) ?>"><?= e(truncate($ban['reason'], 80)) ?></span>
                                </td>
                                <td class="text-muted">
                                    <?= $ban['banned_by_username'] ? e($ban['banned_by_username']) : '<em>Automatique</em>' ?>
                                </td>
                                <td class="text-muted text-small"><?= format_date($ban['created_at']) ?></td>
                                <td>
                                    <?php if ($ban['expires_at']): ?>
                                        <span class="text-small"><?= format_date($ban['expires_at']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="font-size:0.7em;">Permanent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isRevoked): ?>
                                        <span class="badge badge-secondary" style="font-size:0.7em;">Annulé</span>
                                        <?php if ($ban['revoked_by_username']): ?>
                                            <div class="text-muted text-small">par <?= e($ban['revoked_by_username']) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($isExpired): ?>
                                        <span class="badge badge-warning" style="font-size:0.7em;">Expiré</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="font-size:0.7em;">Actif</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (in_array(current_global_role(), ['admin', 'superadmin'])): ?>
                                    <td class="text-right">
                                        <?php if ($isActive): ?>
                                            <form method="POST" action="/admin/bans/users/<?= $ban['id'] ?>/revoke" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Annuler ce bannissement ?">Annuler</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted text-small">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($pagination['lastPage'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['lastPage']; $i++): ?>
            <?php if ($i === $pagination['page']): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="/admin/bans/users?page=<?= $i ?>&filter=<?= e($filter) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>
