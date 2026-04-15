<div class="page-header">
    <h1>👥 Gestion des utilisateurs</h1>
    <div class="d-flex gap-1">
        <?php if (in_array(current_global_role(), ['admin', 'superadmin'], true)): ?>
            <a href="/admin/users/create" class="btn btn-primary">+ Créer un compte</a>
        <?php endif; ?>
        <a href="/admin" class="btn btn-outline">← Retour</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/admin/users" class="d-flex gap-1 flex-wrap align-center">
            <input type="text" name="username" class="form-control" placeholder="Filtrer par pseudo"
                   value="<?= e($filters['username'] ?? '') ?>" style="max-width:220px;">

            <input type="text" name="email" class="form-control" placeholder="Filtrer par email"
                   value="<?= e($filters['email'] ?? '') ?>" style="max-width:240px;">

            <input type="date" name="created_date" class="form-control"
                   value="<?= e($filters['created_date'] ?? '') ?>" style="max-width:190px;">

            <select name="global_role" class="form-control" style="max-width:180px;">
                <option value="">Tous les rôles</option>
                <option value="user" <?= ($filters['global_role'] ?? '') === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                <option value="moderator" <?= ($filters['global_role'] ?? '') === 'moderator' ? 'selected' : '' ?>>Modérateur</option>
                <option value="admin" <?= ($filters['global_role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="superadmin" <?= ($filters['global_role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>

            <button type="submit" class="btn btn-primary">Filtrer</button>

            <?php if (!empty($filters['username']) || !empty($filters['email']) || !empty($filters['created_date']) || !empty($filters['global_role'])): ?>
                <a href="/admin/users" class="btn btn-outline">Réinitialiser</a>
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
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle global</th>
                        <th>Inscrit le</th>
                        <?php if (in_array(current_global_role(), ['admin', 'superadmin'], true)): ?>
                            <th class="text-right">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="text-muted"><?= $user['id'] ?></td>
                            <td>
                                <strong><?= e($user['username']) ?></strong>
                            </td>
                            <td class="text-muted"><?= e($user['email']) ?></td>
                            <td>
                                <span class="badge <?= $user['global_role'] === 'superadmin' ? 'badge-danger' : ($user['global_role'] === 'admin' ? 'badge-warning' : ($user['global_role'] === 'moderator' ? 'badge-info' : '')) ?>">
                                    <?= global_role_label($user['global_role']) ?>
                                </span>
                            </td>
                            <td class="text-muted text-small"><?= format_date($user['created_at']) ?></td>
                            <?php if (in_array(current_global_role(), ['admin', 'superadmin'], true)): ?>
                                <td class="text-right">
                                    <div class="d-flex gap-1 justify-end" style="display:inline-flex;flex-wrap:wrap;">
                                        <?php if (in_array($user['global_role'], ['admin', 'superadmin'], true)): ?>
                                            <span class="text-muted text-small">Compte protégé</span>
                                        <?php else: ?>
                                            <a href="/admin/users/<?= $user['id'] ?>/restrictions" class="btn btn-sm btn-outline<?= !empty($user['restrictions']) ? '-danger' : '' ?>">
                                                <?= !empty($user['restrictions']) ? '🔒 Restreint' : '🔒 Restrictions' ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($user['id'] != current_user_id() && empty($user['is_anonymized'])): ?>
                                            <a href="/admin/users/<?= $user['id'] ?>/reset-password" class="btn btn-sm btn-outline-warning">🔑 Reset MDP</a>
                                        <?php endif; ?>

                                        <?php if (current_global_role() === 'superadmin' && $user['id'] != current_user_id()): ?>
                                            <button type="button" class="btn btn-sm btn-outline" onclick="openRoleModal(<?= $user['id'] ?>, '<?= e($user['username']) ?>', '<?= $user['global_role'] ?>')">👑 Rôle</button>
                                            <?php if ($user['global_role'] !== 'superadmin'): ?>
                                            <form method="POST" action="/admin/users/<?= $user['id'] ?>/impersonate" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Prendre le contrôle du compte de <?= e($user['username']) ?> ?')">🎭 Contrôler</button>
                                            </form>
                                            <?php endif; ?>
                                        <?php elseif ($user['id'] == current_user_id()): ?>
                                            <span class="text-muted text-small">Vous</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['lastPage'] > 1): ?>
    <?php
        $__paginationQuery = [
            'username' => $filters['username'] ?? '',
            'email' => $filters['email'] ?? '',
            'created_date' => $filters['created_date'] ?? '',
            'global_role' => $filters['global_role'] ?? '',
        ];
        $__paginationQuery = array_filter($__paginationQuery, static fn($v): bool => $v !== null && $v !== '');
    ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['lastPage']; $i++): ?>
            <?php if ($i === $pagination['page']): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="/admin/users?<?= e(http_build_query(array_merge($__paginationQuery, ['page' => $i]))) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php if (current_global_role() === 'superadmin'): ?>
<!-- Modale de modification de rôle -->
<div class="modal-overlay" id="roleModal">
    <div class="modal modal-md">
        <h3>👑 Modifier le rôle</h3>
        <p>Modifier le rôle global de <strong id="roleModalUsername"></strong> :</p>
        <form method="POST" id="roleModalForm">
            <?= csrf_field() ?>
            <div class="form-group">
                <select name="global_role" id="roleModalSelect" class="form-control">
                    <option value="user">Utilisateur</option>
                    <option value="moderator">Modérateur</option>
                    <option value="admin">Admin</option>
                    <option value="superadmin">Superadmin</option>
                </select>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline" onclick="closeRoleModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleModal(userId, username, currentRole) {
    document.getElementById('roleModalUsername').textContent = username;
    document.getElementById('roleModalForm').action = '/admin/users/' + userId + '/role';
    document.getElementById('roleModalSelect').value = currentRole;
    document.getElementById('roleModal').classList.add('active');
}
function closeRoleModal() {
    document.getElementById('roleModal').classList.remove('active');
}
document.getElementById('roleModal').addEventListener('click', function (e) {
    if (e.target === this) closeRoleModal();
});
</script>
<?php endif; ?>
