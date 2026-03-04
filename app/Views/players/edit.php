<div class="page-header">
    <h1>Modifier le joueur</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/players" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/players/<?= $player['id'] ?>/edit">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name" class="form-label">Nom du joueur</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($player['name']) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="user_id" class="form-label">Lier à un compte utilisateur (optionnel)</label>
                <input type="number" id="user_id" name="user_id" class="form-control"
                       value="<?= $player['user_id'] ?? '' ?>"
                       placeholder="ID utilisateur">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
