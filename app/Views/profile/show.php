<div class="page-header">
    <h1>Mon profil</h1>
    <a href="/profile/edit" class="btn btn-primary">Modifier</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="profile-header">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= e($user['avatar']) ?>" alt="Avatar" class="profile-avatar">
            <?php else: ?>
                <div class="profile-avatar">👤</div>
            <?php endif; ?>
            <div class="profile-info">
                <h2><?= e($user['username']) ?></h2>
                <p class="profile-role">
                    <span class="badge badge-primary"><?= global_role_label($user['global_role']) ?></span>
                </p>
                <p class="text-muted text-small">Membre depuis le <?= format_date($user['created_at'], 'd/m/Y') ?></p>
            </div>
        </div>

        <?php if (!empty($user['bio'])): ?>
            <div class="mt-2">
                <h3>Biographie</h3>
                <p><?= nl2br(e($user['bio'])) ?></p>
            </div>
        <?php endif; ?>

        <div class="mt-2">
            <h3>Informations</h3>
            <table class="table">
                <tr>
                    <td class="font-bold">Email</td>
                    <td><?= e($user['email']) ?></td>
                </tr>
                <tr>
                    <td class="font-bold">Nom d'utilisateur</td>
                    <td><?= e($user['username']) ?></td>
                </tr>
                <tr>
                    <td class="font-bold">Rôle</td>
                    <td><?= global_role_label($user['global_role']) ?></td>
                </tr>
                <tr>
                    <td class="font-bold">Inscrit le</td>
                    <td><?= format_date($user['created_at']) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
