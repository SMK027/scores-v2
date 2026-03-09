<div class="page-header">
    <h1>🔒 Restrictions — <?= e($space['name']) ?></h1>
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
        <?php if (!empty($space['restricted_at'])): ?>
            <div class="alert alert-warning mt-2">
                <strong>Restrictions actives depuis le <?= format_date($space['restricted_at'], 'd/m/Y à H:i') ?></strong>
                <?php if (!empty($space['restriction_reason'])): ?>
                    <br>Motif : <?= e($space['restriction_reason']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="/admin/spaces/<?= $space['id'] ?>/restrictions">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header">
            <h3>Fonctionnalités à restreindre</h3>
        </div>
        <div class="card-body">
            <p class="text-muted text-small mb-2">Cochez les fonctionnalités à bloquer pour cet espace.</p>
            <?php foreach ($restrictionKeys as $key => $label): ?>
                <label class="d-flex gap-1 align-center mb-1" style="cursor:pointer;">
                    <input type="checkbox" name="restrict_<?= $key ?>" value="1"
                        <?= !empty($restrictions[$key]) ? 'checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3>Motif</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <textarea name="reason" class="form-control" rows="3" placeholder="Motif de la restriction (obligatoire si au moins une restriction est active)"><?= e($space['restriction_reason'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="/admin/spaces" class="btn btn-outline">Annuler</a>
    </div>
</form>
