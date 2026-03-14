<div class="page-header">
    <h1>Vérifier mon adresse email</h1>
</div>

<?php if ($step === 'request'): ?>
<div class="card" style="max-width: 520px; margin: 0 auto;">
    <div class="card-body">
        <p class="text-muted text-small mb-2">
            Vérifiez votre adresse email <strong><?= e($email) ?></strong> pour renforcer la sécurité de votre compte.<br>
            La vérification n'est pas obligatoire pour votre compte existant, mais elle est recommandée.
        </p>
        <form method="POST" action="/account/verify-email/request">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block">
                Envoyer le code de vérification
            </button>
        </form>
        <p class="text-center text-muted text-small mt-2">
            <a href="/profile">← Retour au profil</a>
        </p>
    </div>
</div>

<?php elseif ($step === 'verify'): ?>
<div class="card" style="max-width: 520px; margin: 0 auto;">
    <div class="card-body">
        <p class="text-muted text-small mb-2">
            Un code à 6 chiffres a été envoyé à <strong><?= e($email) ?></strong>.<br>
            Il est valable <strong>15 minutes</strong>.
        </p>
        <form method="POST" action="/account/verify-email/confirm">
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
            <button type="submit" class="btn btn-primary btn-block">Confirmer</button>
        </form>
        <hr style="margin: 1.5rem 0;">
        <p class="text-center text-muted text-small">Code expiré ?</p>
        <form method="POST" action="/account/verify-email/request">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline btn-block">Renvoyer un nouveau code</button>
        </form>
        <p class="text-center text-muted text-small mt-2">
            <a href="/profile">← Retour au profil</a>
        </p>
    </div>
</div>
<?php endif; ?>
