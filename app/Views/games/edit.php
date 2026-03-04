<div class="page-header">
    <h1>Modifier la partie</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>" class="btn btn-outline">← Retour</a>
</div>

<div class="card" style="max-width:700px;">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/edit">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="game_type_id">Type de jeu *</label>
                <select name="game_type_id" id="game_type_id" class="form-control" required>
                    <option value="">-- Choisir un type de jeu --</option>
                    <?php foreach ($gameTypes as $gt): ?>
                        <option value="<?= $gt['id'] ?>" <?= $game['game_type_id'] == $gt['id'] ? 'selected' : '' ?>>
                            <?= e($gt['name']) ?> (<?= win_condition_label($gt['win_condition']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Joueurs *</label>
                <p class="text-muted text-small">Sélectionnez au moins 2 joueurs.</p>
                <?php
                    $selectedPlayerIds = array_column($gamePlayers, 'player_id');
                ?>
                <div class="player-checkboxes" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.5rem;">
                    <?php foreach ($players as $player): ?>
                        <label class="checkbox-label" style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem;border:1px solid var(--gray-light);border-radius:var(--radius);cursor:pointer;">
                            <input type="checkbox" name="player_ids[]" value="<?= $player['id'] ?>"
                                <?= in_array($player['id'], $selectedPlayerIds) ? 'checked' : '' ?>>
                            <span><?= e($player['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Notes (optionnel)</label>
                <textarea name="notes" id="notes" class="form-control" rows="3"><?= e($game['notes'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </div>
</div>
