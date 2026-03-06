<div class="page-header">
    <h1>Nouvelle partie</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/games" class="btn btn-outline">← Retour</a>
</div>

<div class="card" style="max-width:700px;">
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/create">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="game_type_search">Type de jeu *</label>
                <div class="autocomplete-wrapper" style="position:relative;">
                    <input type="text" id="game_type_search" class="form-control" placeholder="Rechercher un type de jeu..." autocomplete="off">
                    <input type="hidden" name="game_type_id" id="game_type_id" required>
                    <div id="game_type_list" class="autocomplete-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:300px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);pointer-events:auto;transition:opacity 0.2s;"></div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Joueurs *</label>
                <p id="player-count-info" class="text-muted text-small">Sélectionnez au moins 2 joueurs.</p>
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

<script>
const gameTypes = <?= json_encode(array_map(fn($gt) => [
    'id' => $gt['id'],
    'name' => $gt['name'],
    'win_condition' => win_condition_label($gt['win_condition']),
    'min_players' => $gt['min_players'] ?? 2,
    'max_players' => $gt['max_players']
], $gameTypes)) ?>;

const searchInput = document.getElementById('game_type_search');
const hiddenInput = document.getElementById('game_type_id');
const listContainer = document.getElementById('game_type_list');

function renderList(items) {
    listContainer.innerHTML = items.map(gt => `
        <div class="autocomplete-item" style="padding:0.75rem;border-bottom:1px solid var(--gray-light);cursor:pointer;" data-id="${gt.id}">
            <strong>${gt.name}</strong> <span class="text-muted text-small">(${gt.win_condition})</span>
        </div>
    `).join('');
    
    document.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('click', () => {
            const gt = gameTypes.find(g => g.id == item.dataset.id);
            searchInput.value = gt.name;
            hiddenInput.value = gt.id;
            hideList();
        });
    });
}

function showList() {
    listContainer.style.display = 'block';
    listContainer.style.opacity = '1';
}

function hideList() {
    listContainer.style.opacity = '0';
    setTimeout(() => listContainer.style.display = 'none', 200);
}

searchInput.addEventListener('input', (e) => {
    const query = e.target.value.toLowerCase();
    const filtered = query ? gameTypes.filter(gt => gt.name.toLowerCase().startsWith(query)) : gameTypes;
    
    if (filtered.length > 0) {
        renderList(filtered);
        showList();
    } else {
        hideList();
    }
});

searchInput.addEventListener('focus', () => {
    if (searchInput.value === '' && gameTypes.length > 0) {
        renderList(gameTypes);
        showList();
    }
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.autocomplete-wrapper')) {
        hideList();
    }
});
</script>
