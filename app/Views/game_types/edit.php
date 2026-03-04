<div class="page-header">
    <h1>Modifier le type de jeu</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/game-types" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/game-types/<?= $gameType['id'] ?>/edit">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nom du jeu</label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($gameType['name']) ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?= e($gameType['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="win_condition" class="form-label">Condition de victoire</label>
                <select id="win_condition" name="win_condition" class="form-control">
                    <option value="highest_score" <?= $gameType['win_condition'] === 'highest_score' ? 'selected' : '' ?>>Score le plus élevé</option>
                    <option value="lowest_score" <?= $gameType['win_condition'] === 'lowest_score' ? 'selected' : '' ?>>Score le plus bas</option>
                    <option value="win_loss" <?= $gameType['win_condition'] === 'win_loss' ? 'selected' : '' ?>>Victoire / Défaite</option>
                    <option value="ranking" <?= $gameType['win_condition'] === 'ranking' ? 'selected' : '' ?>>Classement</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="min_players" class="form-label">Joueurs minimum</label>
                    <input type="number" id="min_players" name="min_players" class="form-control"
                           value="<?= $gameType['min_players'] ?>" min="1" max="100">
                </div>
                <div class="form-group">
                    <label for="max_players" class="form-label">Joueurs maximum</label>
                    <input type="number" id="max_players" name="max_players" class="form-control"
                           value="<?= $gameType['max_players'] ?? '' ?>" placeholder="Illimité" min="1" max="100">
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
