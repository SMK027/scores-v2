<div class="page-header">
    <h1>Membres de l'espace</h1>
</div>

<!-- Inviter un membre -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Inviter un membre</h3>
    </div>
    <div class="card-body">
        <p class="text-muted text-small mb-2">L'utilisateur recevra une invitation qu'il pourra accepter ou refuser.</p>
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/members/add" class="d-flex gap-1 flex-wrap align-center space-invite-form">
            <?= csrf_field() ?>
            <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                <input type="text" name="username" class="form-control" placeholder="Nom d'utilisateur" required>
            </div>
            <div class="form-group" style="width:160px;margin-bottom:0;">
                <select name="role" class="form-control">
                    <option value="member">Membre</option>
                    <option value="manager">Gestionnaire</option>
                    <option value="guest">Invité</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Inviter</button>
        </form>
    </div>
</div>

<!-- Générer un lien d'invitation -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Liens d'invitation</h3>
    </div>
    <div class="card-body">
        <p class="text-muted text-small mb-2">Générez un lien pour inviter des personnes à rejoindre l'espace (valable 72h).</p>
        <?php
        $inviteLink = \App\Core\Session::get('invite_link');
        if ($inviteLink):
            \App\Core\Session::remove('invite_link');
        ?>
            <div class="d-flex gap-1 align-center mb-2">
                <input type="text" id="inviteLink" class="form-control" value="<?= e($inviteLink) ?>" readonly style="flex:1;">
                <button type="button" id="copyInviteLink" class="btn btn-outline">Copier</button>
            </div>
        <?php endif; ?>
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/invite" class="mb-3">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline">Générer un nouveau lien</button>
        </form>

        <?php if (!empty($activeInvites)): ?>
            <h4 class="text-small mb-1">Invitations actives (<?= count($activeInvites) ?>)</h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Créé par</th>
                            <th>Créé le</th>
                            <th>Expire le</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeInvites as $invite): ?>
                            <tr>
                                <td><?= e($invite['creator_name']) ?></td>
                                <td class="text-muted text-small"><?= format_date($invite['created_at'], 'd/m/Y H:i') ?></td>
                                <td class="text-muted text-small"><?= format_date($invite['expires_at'], 'd/m/Y H:i') ?></td>
                                <td class="text-right">
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/invite/<?= $invite['id'] ?>/revoke" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Désactiver cette invitation ?">Désactiver</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-small">Aucune invitation active.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Quitter l'espace -->
<?php if ($currentSpace['created_by'] != current_user_id()): ?>
<div class="card mb-3" style="border-color:var(--warning,#f0ad4e);">
    <div class="card-body d-flex justify-between align-center space-leave-card">
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

<!-- Invitations en attente -->
<?php if (!empty($pendingInvitations)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h3>📩 Invitations en attente (<?= count($pendingInvitations) ?>)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle proposé</th>
                        <th>Invité par</th>
                        <th>Date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingInvitations as $inv): ?>
                        <tr>
                            <td><strong><?= e($inv['invited_username']) ?></strong></td>
                            <td><span class="badge badge-primary"><?= space_role_label($inv['role']) ?></span></td>
                            <td class="text-muted"><?= e($inv['invited_by_name']) ?></td>
                            <td class="text-muted text-small"><?= format_date($inv['created_at'], 'd/m/Y H:i') ?></td>
                            <td class="text-right">
                                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/invitations/<?= $inv['id'] ?>/cancel" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Annuler cette invitation ?">Annuler</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des membres -->
<div class="card">
    <div class="card-header">
        <h3>Membres (<?= count($members) ?>)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Membre depuis</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-center gap-1">
                                    <?php if (!empty($m['avatar'])): ?>
                                        <img src="<?= e($m['avatar']) ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                                    <?php else: ?>
                                        <span style="width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--primary-light);color:var(--white);font-size:0.75rem;font-weight:600;flex-shrink:0;"><?= strtoupper(substr($m['username'], 0, 1)) ?></span>
                                    <?php endif; ?>
                                    <?= e($m['username']) ?>
                                </div>
                            </td>
                            <td class="text-muted"><?= e($m['email']) ?></td>
                            <td><span class="badge badge-primary"><?= space_role_label($m['role']) ?></span></td>
                            <td class="text-muted text-small"><?= format_date($m['created_at'], 'd/m/Y') ?></td>
                            <td class="text-right">
                                <?php if ($m['user_id'] != current_user_id() && $m['user_id'] != $currentSpace['created_by'] && $spaceRole === 'admin'): ?>
                                    <div class="d-flex gap-1 justify-between member-actions-cell">
                                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/members/<?= $m['id'] ?>/role"
                                              class="d-flex gap-1 align-center">
                                            <?= csrf_field() ?>
                                            <select name="role" class="form-control" style="width:auto;padding:0.25rem 0.5rem;font-size:0.8rem;">
                                                <option value="admin" <?= $m['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                <option value="manager" <?= $m['role'] === 'manager' ? 'selected' : '' ?>>Gestionnaire</option>
                                                <option value="member" <?= $m['role'] === 'member' ? 'selected' : '' ?>>Membre</option>
                                                <option value="guest" <?= $m['role'] === 'guest' ? 'selected' : '' ?>>Invité</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline">Modifier</button>
                                        </form>
                                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/members/<?= $m['id'] ?>/remove">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    data-confirm="Retirer ce membre de l'espace ?">Retirer</button>
                                        </form>
                                    </div>
                                <?php elseif ($m['user_id'] == $currentSpace['created_by']): ?>
                                    <span class="badge badge-success" style="font-size:0.7em;">Créateur</span>
                                <?php else: ?>
                                    <span class="text-muted text-small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
