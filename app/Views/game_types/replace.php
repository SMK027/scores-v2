<div class="page-header">
    <h1>Remplacer un type de jeu</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/game-types" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-warning">
            <strong>Attention :</strong> cette action est irréversible. Le type de jeu local
            « <strong><?= e($localType['name']) ?></strong> » sera supprimé et toutes ses parties
            seront rattachées au type global sélectionné.
        </div>

        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/game-types/<?= $localType['id'] ?>/replace">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Type local à remplacer</label>
                <input type="text" class="form-control" value="<?= e($localType['name']) ?>" disabled>
            </div>

            <div class="form-group">
                <label for="global_game_type_id" class="form-label">Remplacer par le type global</label>
                <select id="global_game_type_id" name="global_game_type_id" class="form-control" required>
                    <option value="">— Sélectionnez un type global —</option>
                    <?php foreach ($globalTypes as $gt): ?>
                        <option value="<?= $gt['id'] ?>"><?= e($gt['name']) ?> (<?= win_condition_label($gt['win_condition']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-warning"
                        data-confirm="Êtes-vous sûr ? Cette action est irréversible.">Remplacer</button>
            </div>
        </form>
    </div>
</div>
