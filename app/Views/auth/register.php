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
                           placeholder="Minimum 8 caractères" required minlength="8">
                    <span class="form-hint">Au moins 8 caractères</span>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                           placeholder="Répétez votre mot de passe" required minlength="8">
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
