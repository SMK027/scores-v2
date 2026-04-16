<div class="page-header">
    <h1>🌐 Bannissements IP</h1>
    <a href="/admin/bans/ips/create" class="btn btn-danger">+ Bannir une IP</a>
</div>

<div class="d-flex gap-1 flex-wrap mb-2">
    <a href="/admin/bans/ips" class="btn <?= empty($filter) || $filter === 'all' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Tous</a>
    <a href="/admin/bans/ips?filter=active" class="btn <?= $filter === 'active' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Actifs</a>
    <a href="/admin/bans/ips?filter=expired" class="btn <?= $filter === 'expired' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Expirés</a>
    <a href="/admin/bans/ips?filter=revoked" class="btn <?= $filter === 'revoked' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Annulés</a>
</div>

<?php if (empty($bans)): ?>
    <div class="alert alert-info">Aucun bannissement IP trouvé.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-mobile-cards">
            <thead>
                <tr>
                    <th>Adresse IP</th>
                    <th>Raison</th>
                    <th>Banni par</th>
                    <th>Date</th>
                    <th>Expiration</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bans as $ban): ?>
                    <?php
                        $isExpired = $ban['expires_at'] && strtotime($ban['expires_at']) < time();
                        $isRevoked = !$ban['is_active'] && $ban['revoked_by'];
                        $isActive  = $ban['is_active'] && !$isExpired;
                    ?>
                    <tr style="<?= ($isRevoked || $isExpired) ? 'opacity:0.6;' : '' ?>">
                        <td data-label="IP"><strong><?= e($ban['ip_address']) ?></strong></td>
                        <td data-label="Raison"><?= e(mb_strimwidth($ban['reason'], 0, 60, '…')) ?></td>
                        <td data-label="Banni par"><?= $ban['banned_by_username'] ? e($ban['banned_by_username']) : '<em>Automatique</em>' ?></td>
                        <td data-label="Date"><?= date('d/m/Y H:i', strtotime($ban['created_at'])) ?></td>
                        <td data-label="Expiration">
                            <?php if ($ban['expires_at'] === null): ?>
                                <span class="badge badge-danger">Permanent</span>
                            <?php else: ?>
                                <?= date('d/m/Y H:i', strtotime($ban['expires_at'])) ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Statut">
                            <?php if ($isRevoked): ?>
                                <span class="badge badge-secondary">Annulé</span>
                            <?php elseif ($isExpired): ?>
                                <span class="badge badge-warning">Expiré</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Actif</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-actions">
                            <?php if ($isActive && in_array(current_global_role(), ['admin', 'superadmin'])): ?>
                                <form method="POST" action="/admin/bans/ips/<?= $ban['id'] ?>/revoke" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline" data-confirm="Annuler ce bannissement IP ?">Révoquer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['lastPage'] > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $pagination['lastPage']; $p++): ?>
                <a href="/admin/bans/ips?page=<?= $p ?>&filter=<?= e($filter) ?>"
                   class="btn btn-sm <?= $p == $pagination['page'] ? 'btn-primary' : 'btn-outline' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
