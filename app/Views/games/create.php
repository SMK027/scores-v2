<div class="page-header">
    <h1>Nouvelle partie</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/games" class="btn btn-outline">← Retour</a>
</div>

<div class="card" style="max-width:700px;">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/create">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="game_type_id">Type de jeu *</label>
                <select name="game_type_id" id="game_type_id" class="form-control" required>
                    <option value="">-- Choisir un type de jeu --</option>
                    <?php foreach ($gameTypes as $gt): ?>
                        <option value="<?= $gt['id'] ?>" <?= (isset($old['game_type_id']) && $old['game_type_id'] == $gt['id']) ? 'selected' : '' ?>>
                            <?= e($gt['name']) ?> (<?= win_condition_label($gt['win_condition']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Joueurs *</label>
                <p class="text-muted text-small">Sélectionnez au moins 2 joueurs.</p>
                <div class="player-checkboxes" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.5rem;">
                    <?php foreach ($players as $player): ?>
                        <label class="checkbox-label" style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem;border:1px solid var(--gray-light);border-radius:var(--radius);cursor:pointer;">
                            <input type="checkbox" name="player_ids[]" value="<?= $player['id'] ?>"
                                <?= (isset($old['player_ids']) && in_array($player['id'], $old['player_ids'])) ? 'checked' : '' ?>>
                            <span><?= e($player['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($players)): ?>
                    <p class="text-muted">Aucun joueur disponible. <a href="/spaces/<?= $currentSpace['id'] ?>/players/create">Créer un joueur</a></p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Notes (optionnel)</label>
                <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Notes sur la partie..."><?= e($old['notes'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary">Créer la partie</button>
                <a href="/spaces/<?= $currentSpace['id'] ?>/games" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </div>
</div>
