<div class="auth-container">
    <div class="card">
        <div class="card-body">
            <h2>✉️ Vérification de votre email</h2>
            <p class="text-muted text-small mb-2">
                Un code à 6 chiffres a été envoyé à <strong><?= e($email) ?></strong>.<br>
                Saisissez-le ci-dessous pour activer votre compte. Il est valable <strong>15 minutes</strong>.
            </p>

            <form method="POST" action="/verify-email">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="code" class="form-label">Code de vérification</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="form-control"
                        placeholder="123456"
                        required
                        autofocus
                        maxlength="6"
                        minlength="6"
                        pattern="\d{6}"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        style="font-size: 1.5rem; letter-spacing: 0.5rem; text-align: center; font-family: monospace;"
                    >
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Vérifier mon email</button>
                </div>
            </form>

            <hr style="margin: 1.5rem 0;">

            <p class="text-center text-muted text-small">
                Vous n'avez pas reçu de code ?
            </p>
            <form method="POST" action="/verify-email/resend">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-block">Renvoyer un nouveau code</button>
            </form>

            <p class="text-center text-muted text-small mt-2">
                <a href="/login">← Retour à la connexion</a>
            </p>
        </div>
    </div>
</div>
