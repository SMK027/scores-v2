<div class="page-header">
    <h1><?= $gameType ? 'Modifier le type de jeu global' : 'Nouveau type de jeu global' ?></h1>
    <a href="/admin/game-types" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $gameType ? '/admin/game-types/' . $gameType['id'] . '/edit' : '/admin/game-types/create' ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nom du jeu</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($gameType['name'] ?? '') ?>"
                       placeholder="Ex: Tarot, Belotte, Yams..." required maxlength="100" autofocus>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description (optionnel)</label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="Décrivez les règles ou particularités..."><?= e($gameType['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="win_condition" class="form-label">Condition de victoire</label>
                <select id="win_condition" name="win_condition" class="form-control">
                    <option value="highest_score" <?= ($gameType['win_condition'] ?? '') === 'highest_score' ? 'selected' : '' ?>>Score le plus élevé</option>
                    <option value="lowest_score" <?= ($gameType['win_condition'] ?? '') === 'lowest_score' ? 'selected' : '' ?>>Score le plus bas</option>
                    <option value="win_loss" <?= ($gameType['win_condition'] ?? '') === 'win_loss' ? 'selected' : '' ?>>Victoire / Défaite</option>
                    <option value="ranking" <?= ($gameType['win_condition'] ?? '') === 'ranking' ? 'selected' : '' ?>>Classement</option>
                </select>
                <span class="form-hint">Détermine comment le vainqueur est désigné.</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="min_players" class="form-label">Joueurs minimum</label>
                    <input type="number" id="min_players" name="min_players" class="form-control"
                           value="<?= $gameType['min_players'] ?? 2 ?>" min="1" max="100">
                </div>
                <div class="form-group">
                    <label for="max_players" class="form-label">Joueurs maximum (optionnel)</label>
                    <input type="number" id="max_players" name="max_players" class="form-control"
                           value="<?= $gameType['max_players'] ?? '' ?>" placeholder="Illimité" min="1" max="100">
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?= $gameType ? 'Enregistrer' : 'Créer le type de jeu global' ?>
                </button>
            </div>
        </form>
    </div>
</div>
