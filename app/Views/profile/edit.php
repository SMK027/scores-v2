<div class="page-header">
    <h1>Modifier mon profil</h1>
    <a href="/profile" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/profile/edit" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="avatarInput" class="form-label">Photo de profil</label>
                <div class="profile-header mb-2">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="Avatar" class="profile-avatar" id="avatarPreview">
                    <?php else: ?>
                        <img src="" alt="Avatar" class="profile-avatar" id="avatarPreview" style="display:none;">
                        <div class="profile-avatar" id="avatarPlaceholder">👤</div>
                    <?php endif; ?>
                </div>
                <input type="file" id="avatarInput" name="avatar" class="form-control"
                       accept="image/jpeg,image/png,image/gif,image/webp">
                <span class="form-hint">JPG, PNG, GIF ou WebP. Max 2 Mo.</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control"
                           value="<?= e($user['username']) ?>" required minlength="3" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($user['email']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="bio" class="form-label">Biographie</label>
                <textarea id="bio" name="bio" class="form-control" rows="4"
                          placeholder="Présentez-vous en quelques mots..."><?= e($user['bio'] ?? '') ?></textarea>
            </div>

            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--gray-light);">

            <h3>Changer le mot de passe</h3>
            <p class="text-muted text-small mb-2">Laissez vide si vous ne souhaitez pas changer votre mot de passe.</p>

            <div class="form-group">
                <label for="current_password" class="form-label">Mot de passe actuel</label>
                <input type="password" id="current_password" name="current_password" class="form-control"
                       placeholder="Votre mot de passe actuel">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password" class="form-control"
                           placeholder="Minimum 8 caractères" minlength="8">
                </div>
                <div class="form-group">
                    <label for="new_password_confirm" class="form-label">Confirmer</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control"
                           placeholder="Répétez le mot de passe" minlength="8">
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>
