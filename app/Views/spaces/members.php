<div class="page-header">
    <h1>Membres de l'espace</h1>
</div>

<!-- Ajouter un membre -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Ajouter un membre</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/members/add" class="d-flex gap-1 flex-wrap align-center">
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
            <button type="submit" class="btn btn-primary">Ajouter</button>
        </form>
    </div>
</div>

<!-- Générer un lien d'invitation -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Lien d'invitation</h3>
    </div>
    <div class="card-body">
        <p class="text-muted text-small mb-1">Générez un lien pour inviter des personnes à rejoindre l'espace (valable 72h).</p>
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/invite">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline">Générer un lien d'invitation</button>
        </form>
    </div>
</div>

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
                                    <?php if ($m['avatar']): ?>
                                        <img src="<?= e($m['avatar']) ?>" alt="" style="width:28px;height:28px;border-radius:50%;">
                                    <?php endif; ?>
                                    <?= e($m['username']) ?>
                                </div>
                            </td>
                            <td class="text-muted"><?= e($m['email']) ?></td>
                            <td><span class="badge badge-primary"><?= space_role_label($m['role']) ?></span></td>
                            <td class="text-muted text-small"><?= format_date($m['created_at'], 'd/m/Y') ?></td>
                            <td class="text-right">
                                <?php if ($m['user_id'] != current_user_id() && $spaceRole === 'admin'): ?>
                                    <div class="d-flex gap-1 justify-between">
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
