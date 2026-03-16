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
                <?php if (!empty($restrictedGamePlayerIds)): ?>
                    <p class="text-warning text-small" style="margin:0.25rem 0 0.5rem;">
                        Les joueurs marqués "Banni des parties" restent visibles pour identification mais ne peuvent pas être sélectionnés.
                    </p>
                <?php endif; ?>
                <div id="selected-players" style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.5rem;"></div>
                <div class="autocomplete-wrapper" style="position:relative;">
                    <input type="text" id="player_search" class="form-control" placeholder="Rechercher un joueur..." autocomplete="off">
                    <div id="player_list" class="autocomplete-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:250px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);pointer-events:auto;transition:opacity 0.2s;"></div>
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

const allPlayers = <?= json_encode(array_map(fn($p) => [
    'id' => $p['id'],
    'name' => $p['name'],
    'games_restricted' => in_array((int) $p['id'], array_map('intval', $restrictedGamePlayerIds ?? []), true)
], $players)) ?>;

const oldPlayerIds = <?= json_encode(isset($old['player_ids']) ? array_map('intval', $old['player_ids']) : []) ?>;
const restrictedGamePlayerIds = new Set(<?= json_encode(array_map('intval', $restrictedGamePlayerIds ?? [])) ?>);

// ==== Game type autocomplete ====
const searchInput = document.getElementById('game_type_search');
const hiddenInput = document.getElementById('game_type_id');
const listContainer = document.getElementById('game_type_list');

function renderList(items) {
    listContainer.innerHTML = items.map(gt => `
        <div class="autocomplete-item" style="padding:0.75rem;border-bottom:1px solid var(--gray-light);cursor:pointer;" data-id="${gt.id}">
            <strong>${gt.name}</strong> <span class="text-muted text-small">(${gt.win_condition})</span>
        </div>
    `).join('');
    
    document.querySelectorAll('#game_type_list .autocomplete-item').forEach(item => {
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
        hidePlayerList();
    }
});

// ==== Player multi-select autocomplete ====
const playerSearchInput = document.getElementById('player_search');
const playerListContainer = document.getElementById('player_list');
const selectedPlayersContainer = document.getElementById('selected-players');
let selectedPlayerIds = new Set(oldPlayerIds);
selectedPlayerIds = new Set([...selectedPlayerIds].filter(id => !restrictedGamePlayerIds.has(id)));

function renderSelectedPlayers() {
    selectedPlayersContainer.innerHTML = '';
    selectedPlayerIds.forEach(id => {
        const player = allPlayers.find(p => p.id === id);
        if (!player) return;

        const tag = document.createElement('span');
        tag.className = 'player-tag';
        tag.style.cssText = 'display:inline-flex;align-items:center;gap:0.3rem;padding:0.3rem 0.6rem;background:var(--primary);color:#fff;border-radius:20px;font-size:0.85rem;';
        tag.innerHTML = `${player.name}<input type="hidden" name="player_ids[]" value="${player.id}"><button type="button" class="player-tag-remove" style="background:none;border:none;color:#fff;cursor:pointer;font-size:1rem;line-height:1;padding:0 0.1rem;opacity:0.8;" data-id="${player.id}">&times;</button>`;
        selectedPlayersContainer.appendChild(tag);
    });
}

function renderPlayerList(items) {
    const available = items.filter(p => !selectedPlayerIds.has(p.id));
    if (available.length === 0) {
        playerListContainer.innerHTML = '<div style="padding:0.75rem;color:var(--gray);">Aucun joueur trouvé.</div>';
    } else {
        playerListContainer.innerHTML = available.map(p => `
            <div class="autocomplete-item ${p.games_restricted ? 'is-disabled' : ''}" style="padding:0.75rem;border-bottom:1px solid var(--gray-light);cursor:${p.games_restricted ? 'not-allowed' : 'pointer'};opacity:${p.games_restricted ? '0.65' : '1'};display:flex;justify-content:space-between;align-items:center;gap:0.5rem;" data-id="${p.id}">
                <span>${p.name}</span>
                ${p.games_restricted ? '<span class="badge badge-warning">Banni des parties</span>' : ''}
            </div>
        `).join('');
    }

    document.querySelectorAll('#player_list .autocomplete-item').forEach(item => {
        item.addEventListener('click', () => {
            const player = allPlayers.find(p => p.id == item.dataset.id);
            if (!player || player.games_restricted) {
                return;
            }
            selectedPlayerIds.add(parseInt(item.dataset.id));
            renderSelectedPlayers();
            playerSearchInput.value = '';
            renderPlayerList(allPlayers);
            playerSearchInput.focus();
        });
    });
}

function showPlayerList() {
    playerListContainer.style.display = 'block';
    playerListContainer.style.opacity = '1';
}

function hidePlayerList() {
    playerListContainer.style.opacity = '0';
    setTimeout(() => playerListContainer.style.display = 'none', 200);
}

playerSearchInput.addEventListener('input', (e) => {
    const query = e.target.value.toLowerCase().trim();
    const filtered = query ? allPlayers.filter(p => p.name.toLowerCase().includes(query)) : allPlayers;
    renderPlayerList(filtered);
    if (filtered.filter(p => !selectedPlayerIds.has(p.id)).length > 0 || query) {
        showPlayerList();
    } else {
        hidePlayerList();
    }
});

playerSearchInput.addEventListener('focus', () => {
    renderPlayerList(allPlayers);
    showPlayerList();
});

selectedPlayersContainer.addEventListener('click', (e) => {
    const btn = e.target.closest('.player-tag-remove');
    if (!btn) return;
    selectedPlayerIds.delete(parseInt(btn.dataset.id));
    renderSelectedPlayers();
    renderPlayerList(allPlayers);
});

// Initialize pre-selected players
if (selectedPlayerIds.size > 0) {
    renderSelectedPlayers();
}

// ==== Player count info based on game type ====
const playerCountInfo = document.getElementById('player-count-info');

function updatePlayerCountInfo() {
    const gtId = parseInt(hiddenInput.value);
    const gt = gameTypes.find(g => g.id === gtId);
    if (!gt) {
        playerCountInfo.textContent = 'Sélectionnez au moins 2 joueurs.';
        return;
    }
    const min = gt.min_players || 2;
    const max = gt.max_players;
    if (max !== null && min === max) {
        playerCountInfo.textContent = 'Ce type de jeu nécessite exactement ' + min + ' joueur' + (min > 1 ? 's' : '') + '.';
    } else if (max !== null) {
        playerCountInfo.textContent = 'Sélectionnez entre ' + min + ' et ' + max + ' joueurs.';
    } else {
        playerCountInfo.textContent = 'Sélectionnez au minimum ' + min + ' joueur' + (min > 1 ? 's' : '') + '.';
    }
}

// Override game type click to also update player count info
document.querySelectorAll('#game_type_list .autocomplete-item').forEach(() => {});
const origRenderList = renderList;
renderList = function(items) {
    origRenderList(items);
    document.querySelectorAll('#game_type_list .autocomplete-item').forEach(item => {
        item.addEventListener('click', () => updatePlayerCountInfo());
    });
};

updatePlayerCountInfo();
</script>
