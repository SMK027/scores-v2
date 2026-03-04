<div class="page-header">
    <h1>Nouveau type de jeu</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/game-types" class="btn btn-outline">Annuler</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/game-types/create">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nom du jeu</label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Ex: Tarot, Belotte, Yams..." required maxlength="100" autofocus>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description (optionnel)</label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="Décrivez les règles ou particularités..."></textarea>
            </div>

            <div class="form-group">
                <label for="win_condition" class="form-label">Condition de victoire</label>
                <select id="win_condition" name="win_condition" class="form-control">
                    <option value="highest_score">Score le plus élevé</option>
                    <option value="lowest_score">Score le plus bas</option>
                    <option value="win_loss">Victoire / Défaite</option>
                    <option value="ranking">Classement</option>
                </select>
                <span class="form-hint">Détermine comment le vainqueur est désigné.</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="min_players" class="form-label">Joueurs minimum</label>
                    <input type="number" id="min_players" name="min_players" class="form-control"
                           value="2" min="1" max="100">
                </div>
                <div class="form-group">
                    <label for="max_players" class="form-label">Joueurs maximum (optionnel)</label>
                    <input type="number" id="max_players" name="max_players" class="form-control"
                           placeholder="Illimité" min="1" max="100">
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Créer le type de jeu</button>
            </div>
        </form>
    </div>
</div>
