<div class="page-header">
    <h1>🏆 Critères du leaderboard</h1>
    <a href="/admin" class="btn btn-outline">← Retour</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Configuration d'éligibilité au leaderboard global</h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">
            Ces critères sont appliqués immédiatement à la page leaderboard globale.
        </p>

        <form method="POST" action="/admin/leaderboard-criteria">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="min_rounds_played" class="form-label">Minimum de manches jouées</label>
                <input
                    type="number"
                    id="min_rounds_played"
                    name="min_rounds_played"
                    class="form-control"
                    min="1"
                    max="100000"
                    style="max-width:220px;"
                    value="<?= e((string) ($config['min_rounds_played'] ?? '5')) ?>"
                >
                <span class="form-hint">Ex: 5 signifie qu'un joueur avec 4 manches ne sera pas classé.</span>
            </div>

            <div class="form-group">
                <label for="min_spaces_played" class="form-label">Minimum d'espaces distincts</label>
                <input
                    type="number"
                    id="min_spaces_played"
                    name="min_spaces_played"
                    class="form-control"
                    min="1"
                    max="1000"
                    style="max-width:220px;"
                    value="<?= e((string) ($config['min_spaces_played'] ?? '2')) ?>"
                >
                <span class="form-hint">Ex: 2 signifie que l'activité doit couvrir au moins 2 espaces différents.</span>
            </div>

            <div class="card" style="background:rgba(255,255,255,.03);margin-bottom:1.5rem;border:1px solid var(--border,#2a3a5c);">
                <div class="card-body">
                    <h4 style="margin-top:0;">📋 Résumé</h4>
                    <p id="criteriaPreview" class="text-muted" style="margin-bottom:0;"></p>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">💾 Enregistrer les critères</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var rounds = document.getElementById('min_rounds_played');
    var spaces = document.getElementById('min_spaces_played');
    var preview = document.getElementById('criteriaPreview');

    function updatePreview() {
        preview.textContent = 'Pour être classé: au moins ' + rounds.value + ' manches jouées et ' + spaces.value + ' espace(s) distinct(s).';
    }

    rounds.addEventListener('input', updatePreview);
    spaces.addEventListener('input', updatePreview);
    updatePreview();
});
</script>
