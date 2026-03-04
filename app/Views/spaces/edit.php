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
                <button type="submit" class="btn btn-primary">Enregistrer</button>
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
