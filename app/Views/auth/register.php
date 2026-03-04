<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2>Inscription</h2>
            <form method="POST" action="/register">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control"
                           placeholder="MonPseudo" required autofocus
                           minlength="3" maxlength="50" pattern="[a-zA-Z0-9_-]+">
                    <span class="form-hint">3 à 50 caractères (lettres, chiffres, tirets, underscores)</span>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="votre@email.com" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Saisissez votre mot de passe" required>
                    <span class="form-hint"><?= e($policySummary ?? '') ?></span>
                    <div id="passwordChecklist" class="password-checklist" style="margin-top:0.5rem;font-size:0.85rem;"></div>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                           placeholder="Répétez votre mot de passe" required>
                    <div id="passwordMatchHint" style="font-size:0.85rem;margin-top:0.25rem;"></div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Créer mon compte</button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                Déjà un compte ? <a href="/login">Se connecter</a>
            </p>
        </div>
    </div>
</div>

<script>
(function() {
    var policy = <?= $policyJson ?? '{}' ?>;
    var pwField = document.getElementById('password');
    var confirmField = document.getElementById('password_confirm');
    var checklist = document.getElementById('passwordChecklist');
    var matchHint = document.getElementById('passwordMatchHint');

    function checkPolicy() {
        if (!pwField || !checklist) return;
        var pw = pwField.value;
        var items = [];

        items.push({ok: pw.length >= (policy.min_length || 8), text: (policy.min_length || 8) + ' caractères minimum'});
        if (policy.require_lowercase) items.push({ok: /[a-z]/.test(pw), text: '1 minuscule'});
        if (policy.require_uppercase) items.push({ok: /[A-Z]/.test(pw), text: '1 majuscule'});
        if (policy.require_digit)     items.push({ok: /[0-9]/.test(pw), text: '1 chiffre'});
        if (policy.require_special)   items.push({ok: /[^a-zA-Z0-9]/.test(pw), text: '1 caractère spécial'});

        checklist.innerHTML = items.map(function(i) {
            var color = pw.length === 0 ? '#888' : (i.ok ? '#16a34a' : '#dc2626');
            var icon = pw.length === 0 ? '○' : (i.ok ? '✓' : '✗');
            return '<div style="color:' + color + '">' + icon + ' ' + i.text + '</div>';
        }).join('');
    }

    function checkMatch() {
        if (!confirmField || !matchHint) return;
        var pw = pwField.value;
        var confirm = confirmField.value;
        if (confirm.length === 0) {
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
