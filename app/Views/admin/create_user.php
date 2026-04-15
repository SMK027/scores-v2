<div class="page-header">
    <h1>➕ Créer un compte</h1>
    <a href="/admin/users" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/admin/users/create" id="create-user-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username" class="form-label">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" class="form-control" required
                       minlength="3" maxlength="50" pattern="[a-zA-Z0-9_-]+"
                       placeholder="ex : jean_dupont">
                <span class="form-hint">3 à 50 caractères : lettres, chiffres, tirets, underscores.</span>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Adresse email</label>
                <input type="email" id="email" name="email" class="form-control" required
                       placeholder="ex : jean@exemple.fr">
            </div>

            <div class="form-group">
                <label for="global_role" class="form-label">Rôle global</label>
                <select id="global_role" name="global_role" class="form-control">
                    <option value="user">Utilisateur</option>
                    <option value="moderator">Modérateur</option>
                    <?php if (current_global_role() === 'superadmin'): ?>
                        <option value="admin">Administrateur</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <div class="d-flex gap-1" style="margin-bottom:.5rem;">
                    <label style="cursor:pointer;">
                        <input type="radio" name="password_mode" value="manual" checked onchange="togglePasswordMode()">
                        Définir manuellement
                    </label>
                    <label style="cursor:pointer;margin-left:1.5rem;">
                        <input type="radio" name="password_mode" value="email" onchange="togglePasswordMode()">
                        Envoyer un lien par email
                    </label>
                </div>
            </div>

            <div id="manual-password-fields">
                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control"
                           autocomplete="new-password" placeholder="Mot de passe">
                    <div id="passwordChecklist" style="font-size:0.85rem;margin-top:0.25rem;"></div>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                           autocomplete="new-password" placeholder="Confirmer">
                    <div id="passwordMatchHint" style="font-size:0.85rem;margin-top:0.25rem;"></div>
                </div>
            </div>

            <div id="email-password-fields" style="display:none;">
                <div class="form-group">
                    <label for="reset_duration" class="form-label">Durée de validité du lien</label>
                    <select id="reset_duration" name="reset_duration" class="form-control">
                        <option value="60">1 heure</option>
                        <option value="360">6 heures</option>
                        <option value="720">12 heures</option>
                        <option value="1440" selected>1 jour</option>
                        <option value="2880">2 jours</option>
                        <option value="4320">3 jours (maximum)</option>
                    </select>
                    <span class="form-hint">L'utilisateur devra définir son mot de passe avant l'expiration du lien.</span>
                </div>
            </div>

            <div class="form-group" style="margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary">Créer le compte</button>
            </div>
        </form>
    </div>
</div>

<script>
var __policy = <?= $policyJson ?? '{}' ?>;

function togglePasswordMode() {
    const mode = document.querySelector('input[name="password_mode"]:checked').value;
    const manualFields = document.getElementById('manual-password-fields');
    const emailFields = document.getElementById('email-password-fields');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirm');

    if (mode === 'manual') {
        manualFields.style.display = '';
        emailFields.style.display = 'none';
        passwordInput.required = true;
        confirmInput.required = true;
    } else {
        manualFields.style.display = 'none';
        emailFields.style.display = '';
        passwordInput.required = false;
        confirmInput.required = false;
    }
}
togglePasswordMode();

(function() {
    var policy = __policy;
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
