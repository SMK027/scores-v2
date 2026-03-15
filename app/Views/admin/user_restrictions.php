<div class="page-header">
    <h1>🔒 Restrictions du compte — <?= e($targetUser['username']) ?></h1>
    <a href="/admin/users" class="btn btn-outline">← Retour aux utilisateurs</a>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Informations utilisateur</h3>
    </div>
    <div class="card-body">
        <p><strong>ID :</strong> <?= (int) $targetUser['id'] ?></p>
        <p><strong>Nom :</strong> <?= e($targetUser['username']) ?></p>
        <p><strong>Email :</strong> <?= e($targetUser['email']) ?></p>
        <p><strong>Rôle global :</strong> <?= e(global_role_label($targetUser['global_role'])) ?></p>

        <?php if (!empty($targetUser['restricted_at'])): ?>
            <div class="alert alert-warning mt-2">
                <strong>Restrictions actives depuis le <?= format_date($targetUser['restricted_at'], 'd/m/Y à H:i') ?></strong>
                <?php if (!empty($targetUser['restriction_reason'])): ?>
                    <br>Motif : <?= e($targetUser['restriction_reason']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="/admin/users/<?= (int) $targetUser['id'] ?>/restrictions">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <div class="card-header">
            <h3>Fonctionnalités à restreindre</h3>
        </div>
        <div class="card-body">
            <p class="text-muted text-small mb-2">Cochez les actions à bloquer pour ce compte utilisateur.</p>
            <?php foreach ($restrictionKeys as $key => $label): ?>
                <label class="d-flex gap-1 align-center mb-1" style="cursor:pointer;">
                    <input type="checkbox" name="restrict_<?= e($key) ?>" value="1" <?= !empty($restrictions[$key]) ? 'checked' : '' ?>>
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
                <textarea name="reason" class="form-control" rows="3" placeholder="Motif de la restriction (obligatoire si au moins une restriction est active)"><?= e($targetUser['restriction_reason'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="/admin/users" class="btn btn-outline">Annuler</a>
    </div>
</form>
