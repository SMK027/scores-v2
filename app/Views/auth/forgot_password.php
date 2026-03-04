<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2>Mot de passe oublié</h2>
            <p class="text-muted text-small mb-2">
                Saisissez l'adresse email associée à votre compte. Vous recevrez un lien de réinitialisation.
            </p>
            <form method="POST" action="/forgot-password">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email" class="form-label">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="votre@email.com" required autofocus>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Envoyer le lien</button>
                </div>
            </form>
            <p class="text-center text-muted text-small mt-2">
                <a href="/login">← Retour à la connexion</a>
            </p>
        </div>
    </div>
</div>
