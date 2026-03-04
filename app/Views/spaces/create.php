<div class="page-header">
    <h1>Créer un espace</h1>
    <a href="/spaces" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/create">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name" class="form-label">Nom de l'espace</label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Ex: Soirées jeux en famille" required maxlength="100" autofocus>
            </div>
            <div class="form-group">
                <label for="description" class="form-label">Description (optionnel)</label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="Décrivez votre espace..."></textarea>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Créer l'espace</button>
            </div>
        </form>
    </div>
</div>
