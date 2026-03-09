<div class="page-header">
    <h1>💣 Suppression programmée — <?= e($space['name']) ?></h1>
    <a href="/admin/spaces" class="btn btn-outline">← Retour aux espaces</a>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Informations de l'espace</h3>
    </div>
    <div class="card-body">
        <p><strong>ID :</strong> <?= $space['id'] ?></p>
        <p><strong>Nom :</strong> <?= e($space['name']) ?></p>
        <?php if (!empty($space['description'])): ?>
            <p><strong>Description :</strong> <?= e($space['description']) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($space['scheduled_deletion_at'])): ?>
    <?php
        $paris = new DateTimeZone('Europe/Paris');
        $deletionDt = new DateTimeImmutable($space['scheduled_deletion_at'], $paris);
        $nowParis = new DateTimeImmutable('now', $paris);
        $isPast = $deletionDt <= $nowParis;
    ?>
    <div class="alert alert-danger" style="border-left:4px solid var(--danger,#dc3545);">
        <div>
            <strong>⚠️ Suppression programmée le <?= $deletionDt->format('d/m/Y à H:i') ?> (heure de Paris)</strong>
            <?php if (!empty($space['deletion_reason'])): ?>
                <br>Motif : <?= e($space['deletion_reason']) ?>
            <?php endif; ?>
            <?php if ($isPast): ?>
                <br><em class="text-muted">La date est dépassée — l'espace sera supprimé lors de la prochaine exécution du script de purge.</em>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" action="/admin/spaces/<?= $space['id'] ?>/cancel-deletion" class="mb-3">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-success" onclick="return confirm('Annuler la suppression programmée de cet espace ?')">
            ✅ Annuler la suppression programmée
        </button>
    </form>

    <hr style="margin:1.5rem 0;">
<?php endif; ?>

<form method="POST" action="/admin/spaces/<?= $space['id'] ?>/schedule-deletion">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header">
            <h3><?= !empty($space['scheduled_deletion_at']) ? 'Modifier la planification' : 'Planifier la suppression' ?></h3>
        </div>
        <div class="card-body">
            <div class="alert alert-warning" style="border-left:4px solid var(--warning-dark,#e6b84d);margin-bottom:1rem;">
                <strong>⚠️ Mesure de dernier recours.</strong><br>
                Un compte à rebours sera affiché à tous les membres de l'espace.
                Si les gestionnaires ne corrigent pas leurs infractions aux CGU avant l'échéance,
                l'espace sera <strong>définitivement supprimé</strong>.
            </div>

            <div class="form-group mb-2">
                <label for="scheduled_at"><strong>Date et heure de suppression (heure de Paris) :</strong></label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="form-control" required
                    value="<?= !empty($space['scheduled_deletion_at']) ? (new DateTimeImmutable($space['scheduled_deletion_at'], new DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i') : '' ?>">
            </div>

            <div class="form-group">
                <label for="deletion_reason"><strong>Motif de la suppression :</strong></label>
                <textarea id="deletion_reason" name="deletion_reason" class="form-control" rows="3" required
                    placeholder="Décrivez les infractions aux CGU et les avertissements préalables..."><?= e($space['deletion_reason'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-danger" onclick="return confirm('Confirmer la planification de suppression de cet espace ?')">
            💣 <?= !empty($space['scheduled_deletion_at']) ? 'Modifier la planification' : 'Programmer la suppression' ?>
        </button>
        <a href="/admin/spaces" class="btn btn-outline">Annuler</a>
    </div>
</form>
