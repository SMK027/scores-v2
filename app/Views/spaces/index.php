<div class="page-header">
    <h1>Mes espaces</h1>
    <a href="/spaces/create" class="btn btn-primary">+ Créer un espace</a>
</div>

<?php if (!empty($pendingInvitations)): ?>
    <div class="card mb-3" style="border-color:var(--primary);">
        <div class="card-header">
            <h3>📩 Invitations reçues (<?= count($pendingInvitations) ?>)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Espace</th>
                            <th>Invité par</th>
                            <th>Rôle proposé</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingInvitations as $inv): ?>
                            <tr>
                                <td><strong><?= e($inv['space_name']) ?></strong></td>
                                <td><?= e($inv['invited_by_name']) ?></td>
                                <td><span class="badge badge-primary"><?= space_role_label($inv['role']) ?></span></td>
                                <td class="text-muted text-small"><?= format_date($inv['created_at'], 'd/m/Y H:i') ?></td>
                                <td class="text-right">
                                    <div class="d-flex gap-1 justify-end">
                                        <form method="POST" action="/invitations/<?= $inv['id'] ?>/accept">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-primary">Accepter</button>
                                        </form>
                                        <form method="POST" action="/invitations/<?= $inv['id'] ?>/decline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Refuser cette invitation ?">Refuser</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($spaces)): ?>
    <div class="empty-state">
        <div class="empty-icon">🏠</div>
        <p>Vous n'avez pas encore d'espace.<br>Créez-en un pour commencer à enregistrer vos parties !</p>
        <a href="/spaces/create" class="btn btn-primary">Créer mon premier espace</a>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($spaces as $space): ?>
            <a href="/spaces/<?= $space['id'] ?>" class="card-link">
                <div class="card">
                    <div class="card-body">
                        <h3><?= e($space['name']) ?></h3>
                        <?php if ($space['description']): ?>
                            <p class="text-muted text-small"><?= e(truncate($space['description'], 120)) ?></p>
                        <?php endif; ?>
                        <div class="d-flex align-center gap-1 flex-wrap mt-1">
                            <span class="badge badge-primary"><?= space_role_label($space['user_role']) ?></span>
                            <span class="text-muted text-small">👥 <?= $space['member_count'] ?> membre(s)</span>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
