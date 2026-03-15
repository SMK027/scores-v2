<div class="page-header">
    <h1>Paramètres de l'espace</h1>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Informations générales</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/edit">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name" class="form-label">Nom de l'espace</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($currentSpace['name']) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= e($currentSpace['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="color" class="form-label">Couleur de l'espace</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="color" id="color" name="color"
                           value="<?= e(!empty($currentSpace['color']) ? $currentSpace['color'] : '#4361ee') ?>"
                           style="width:56px;height:36px;padding:2px 4px;border:1px solid var(--border-color);border-radius:var(--border-radius);cursor:pointer;">
                    <button type="button" class="btn btn-outline btn-sm"
                            onclick="document.getElementById('color').value='#4361ee'">Réinitialiser</button>
                </div>
                <span class="form-hint">Couleur utilisée pour distinguer cet espace dans la liste.</span>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3>Export / Import des données</h3>
    </div>
    <div class="card-body">
        <p class="text-muted">Exportez un fichier JSON complet de l'espace avec checksum SHA-256. Lors d'un import, la checksum est vérifiée avant toute modification.</p>

        <div class="d-flex gap-1 flex-wrap mb-2">
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/export" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline">⬇️ Exporter l'espace</button>
            </form>
        </div>

        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/import" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="space_import" class="form-label">Fichier d'import (.json)</label>
                <input type="file" id="space_import" name="space_import" class="form-control" accept="application/json,.json" required>
            </div>
            <p class="text-danger text-small">
                ⚠️ L'import écrase toutes les données de l'espace (types de jeu, joueurs, compétitions, parties, manches, scores, commentaires),
                mais conserve les membres.
            </p>
            <div class="form-group">
                <button type="submit" class="btn btn-warning" data-confirm="Confirmer l'import ? Toutes les données actuelles de l'espace seront remplacées (sauf membres).">
                    ⬆️ Importer et écraser les données
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="text-danger">Zone dangereuse</h3>
    </div>
    <div class="card-body">
        <p>La suppression de l'espace est définitive. Toutes les données (parties, joueurs, scores) seront perdues.</p>
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/delete">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" data-confirm="Êtes-vous sûr de vouloir supprimer cet espace ? Cette action est irréversible.">
                Supprimer l'espace
            </button>
        </form>
    </div>
</div>
