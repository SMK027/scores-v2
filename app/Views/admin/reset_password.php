<div class="page-header">
    <h1>🔑 Réinitialisation de mot de passe</h1>
    <a href="/admin/users" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-body">
        <p>Envoyer un lien de réinitialisation de mot de passe à <strong><?= e($targetUser['username']) ?></strong>
            (<?= e($targetUser['email']) ?>).</p>

        <form method="POST" action="/admin/users/<?= $targetUser['id'] ?>/reset-password">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="duration" class="form-label">Durée de validité du lien</label>
                <select id="duration" name="duration" class="form-control" required>
                    <option value="">— Sélectionnez —</option>
                    <option value="15">15 minutes</option>
                    <option value="30">30 minutes</option>
                    <option value="60">1 heure</option>
                    <option value="120">2 heures</option>
                    <option value="360">6 heures</option>
                    <option value="720">12 heures</option>
                    <option value="1440">1 jour</option>
                    <option value="2880">2 jours</option>
                    <option value="4320">3 jours (maximum)</option>
                </select>
                <span class="form-hint">Le lien expirera après la durée choisie. Maximum : 3 jours.</span>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-warning"
                        data-confirm="Envoyer un lien de réinitialisation à <?= e($targetUser['username']) ?> ?">
                    Envoyer le lien de réinitialisation
                </button>
            </div>
        </form>
    </div>
</div>
