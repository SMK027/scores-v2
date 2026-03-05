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
                <span class="form-hint">JPG, PNG, GIF ou WebP. Max 5 Mo.</span>
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
                <div class="password-wrapper">
                    <input type="password" id="current_password" name="current_password" class="form-control"
                           placeholder="Votre mot de passe actuel">
                    <button type="button" class="btn-toggle-password" data-target="current_password" title="Afficher le mot de passe">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" class="form-control"
                               placeholder="Saisissez un nouveau mot de passe">
                        <button type="button" class="btn-toggle-password" data-target="new_password" title="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <span class="form-hint"><?= e($policySummary ?? '') ?></span>
                    <div id="passwordChecklist" class="password-checklist" style="margin-top:0.5rem;font-size:0.85rem;"></div>
                </div>
                <div class="form-group">
                    <label for="new_password_confirm" class="form-label">Confirmer</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control"
                               placeholder="Répétez le mot de passe">
                        <button type="button" class="btn-toggle-password" data-target="new_password_confirm" title="Afficher le mot de passe">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchHint" style="font-size:0.85rem;margin-top:0.25rem;"></div>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var policy = <?= $policyJson ?? '{}' ?>;
    var pwField = document.getElementById('new_password');
    var confirmField = document.getElementById('new_password_confirm');
    var checklist = document.getElementById('passwordChecklist');
    var matchHint = document.getElementById('passwordMatchHint');

    function checkPolicy() {
        if (!pwField || !checklist) return;
        var pw = pwField.value;
        if (pw.length === 0) { checklist.innerHTML = ''; return; }
        var items = [];

        items.push({ok: pw.length >= (policy.min_length || 8), text: (policy.min_length || 8) + ' caractères minimum'});
        if (policy.require_lowercase) items.push({ok: /[a-z]/.test(pw), text: '1 minuscule'});
        if (policy.require_uppercase) items.push({ok: /[A-Z]/.test(pw), text: '1 majuscule'});
        if (policy.require_digit)     items.push({ok: /[0-9]/.test(pw), text: '1 chiffre'});
        if (policy.require_special)   items.push({ok: /[^a-zA-Z0-9]/.test(pw), text: '1 caractère spécial'});

        checklist.innerHTML = items.map(function(i) {
            var color = i.ok ? '#16a34a' : '#dc2626';
            var icon = i.ok ? '✓' : '✗';
            return '<div style="color:' + color + '">' + icon + ' ' + i.text + '</div>';
        }).join('');
    }

    function checkMatch() {
        if (!confirmField || !matchHint) return;
        var pw = pwField.value;
        var confirm = confirmField.value;
        if (confirm.length === 0 || pw.length === 0) {
            matchHint.innerHTML = '';
        } else if (pw === confirm) {
            matchHint.innerHTML = '<span style="color:#16a34a">✓ Les mots de passe correspondent</span>';
        } else {
            matchHint.innerHTML = '<span style="color:#dc2626">✗ Les mots de passe ne correspondent pas</span>';
        }
    }

    if (pwField) {
        pwField.addEventListener('input', function() { checkPolicy(); checkMatch(); });
        checkPolicy();
    }
    if (confirmField) {
        confirmField.addEventListener('input', checkMatch);
    }
})();
</script>
