<div style="max-width:600px;margin:4rem auto;text-align:center;">
    <div class="card">
        <div class="card-body">
            <h1 style="font-size:3rem;margin-bottom:0.5rem;">⏸️</h1>
            <h2>Compétition en pause</h2>
            <p class="text-muted">
                La compétition <strong><?= e($session['competition_name']) ?></strong> est actuellement en pause.
            </p>
            <p class="text-muted">
                La saisie des scores est temporairement désactivée. Veuillez patienter que l'organisateur reprenne la compétition.
            </p>
            <hr>
            <p class="text-small text-muted">
                Session #<?= (int) $session['session_number'] ?> — <?= e($session['referee_name']) ?>
            </p>
            <a href="/competition/dashboard" class="btn btn-outline btn-sm">Rafraîchir</a>
            <a href="/competition/logout" class="btn btn-outline btn-sm">Se déconnecter</a>
        </div>
    </div>
</div>
