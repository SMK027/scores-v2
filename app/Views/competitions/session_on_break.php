<div style="max-width:650px;margin:4rem auto;text-align:center;">
    <div class="card">
        <div class="card-body">
            <h1 style="font-size:3rem;margin-bottom:0.5rem;">⏸️</h1>
            <h2>Session en pause temporaire</h2>
            <p class="text-muted">
                Votre session d'arbitrage est momentanément désactivée et sera réactivée automatiquement.
            </p>
            <p>
                <strong>Reprise estimée :</strong>
                <?= !empty($pauseUntil) ? date('d/m/Y H:i:s', strtotime((string) $pauseUntil)) : 'bientôt' ?>
            </p>
            <p class="text-muted text-small">
                Temps restant approximatif : <?= max(1, (int) ceil(((int) ($remainingSeconds ?? 0)) / 60)) ?> minute(s)
            </p>
            <hr>
            <p class="text-small text-muted">
                Session #<?= (int) $session['session_number'] ?> — <?= e($session['referee_name']) ?>
            </p>
            <a href="/competition/dashboard" class="btn btn-outline btn-sm">Rafraichir</a>
            <a href="/competition/logout" class="btn btn-outline btn-sm">Se déconnecter</a>
        </div>
    </div>
</div>
