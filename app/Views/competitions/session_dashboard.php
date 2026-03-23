<div class="page-header">
    <div>
        <h1>🏆 <?= e($session['competition_name']) ?> — Session #<?= (int) $session['session_number'] ?></h1>
        <p class="text-muted text-small">Arbitre : <?= e($session['referee_name']) ?></p>
        <div class="d-flex gap-1 flex-wrap" style="margin-top:0.5rem;">
            <form method="POST" action="/competition/session/pause" style="display:inline;">
                <?= csrf_field() ?>
                <div class="d-flex gap-1 align-center" style="flex-wrap:wrap;">
                    <input
                        type="number"
                        name="pause_minutes"
                        min="<?= (int) ($pauseMinMinutes ?? 5) ?>"
                        max="<?= (int) ($pauseMaxMinutes ?? 30) ?>"
                        value="<?= (int) ($pauseDurationMinutes ?? 15) ?>"
                        class="form-control form-control-sm"
                        style="width:105px;"
                        required
                    >
                    <button type="submit" class="btn btn-sm btn-info">⏸ Partir en pause</button>
                </div>
            </form>
            <form method="POST" action="/competition/session/close" style="display:inline;">
                <?= csrf_field() ?>
                <button
                    type="submit"
                    class="btn btn-sm btn-danger"
                    data-confirm="Fermer définitivement cette session ? Cette action est irréversible et vous devrez contacter l'équipe pour être réaffecté."
                >
                    ⛔ Fermer la session
                </button>
            </form>
        </div>
        <p class="text-muted text-small" style="margin-top:0.35rem;">Pause arbitre: entre 5 et 30 minutes. Au-dela, un membre de l'equipe doit suspendre la session.</p>
    </div>
</div>

<!-- Vérificateur de carte membre -->
<div class="card mb-3" id="member-card-verifier">
    <div class="card-header d-flex justify-between align-center">
        <h3>🪪 Vérificateur de carte membre</h3>
        <span class="text-muted text-small">Participants de la compétition</span>
    </div>
    <div class="card-body">
        <?php if (empty($participantCards ?? [])): ?>
            <p class="text-muted">Aucune carte disponible pour les compétiteurs inscrits.</p>
        <?php else: ?>
            <form method="POST" action="/competition/participants/verify-card" id="participant-card-verifier-form">
                <?= csrf_field() ?>
                <div class="d-flex gap-1 flex-wrap align-center" style="max-width:980px;">
                    <div class="autocomplete-wrapper" style="position:relative;flex:1;min-width:280px;">
                        <label class="text-small text-muted" for="participant_card_search">Compétiteur</label>
                        <input
                            type="text"
                            id="participant_card_search"
                            class="form-control form-control-sm"
                            placeholder="Rechercher un compétiteur inscrit..."
                            autocomplete="off"
                        >
                        <input type="hidden" name="player_id" id="participant_card_player_id" required>
                        <div id="participant_card_options" class="autocomplete-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:260px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                    </div>

                    <div class="autocomplete-wrapper" style="position:relative;flex:1;min-width:280px;">
                        <label class="text-small text-muted" for="participant_card_reference">Référence de carte présentée</label>
                        <input
                            type="text"
                            id="participant_card_reference"
                            name="reference"
                            class="form-control form-control-sm"
                            placeholder="Sélectionner la référence parmi les participants"
                            autocomplete="off"
                            required
                        >
                        <div id="participant_card_reference_options" class="autocomplete-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:260px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                    </div>

                    <div style="display:flex;flex-direction:column;justify-content:flex-end;min-width:170px;">
                        <button class="btn btn-sm btn-primary">Vérifier l'identité</button>
                    </div>
                </div>
                <p class="text-muted text-small" style="margin-top:0.6rem;">
                    Demandez au joueur de montrer sa carte, puis validez son identité via sa référence parmi les compétiteurs inscrits.
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Créer une partie -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Nouvelle partie</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/competition/games/create">
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
                <?php if (!empty($restrictedCompetitionPlayerIds)): ?>
                    <p class="text-warning text-small" style="margin:0.25rem 0 0.5rem;">
                        Les joueurs marqués "Restreint" restent visibles pour identification mais ne peuvent pas être sélectionnés.
                    </p>
                <?php endif; ?>
                <div id="selected-players" style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:0.5rem;"></div>
                <div class="autocomplete-wrapper" style="position:relative;">
                    <input type="text" id="player_search" class="form-control" placeholder="Rechercher un joueur..." autocomplete="off">
                    <div id="player_list" class="autocomplete-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:250px;overflow-y:auto;background:#fff;border:1px solid var(--gray-light);border-radius:var(--radius);margin-top:0.25rem;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);pointer-events:auto;transition:opacity 0.2s;"></div>
                </div>
                <?php if (empty($players)): ?>
                    <p class="text-muted">Aucun joueur disponible dans cet espace.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Notes (optionnel)</label>
                <input type="text" id="notes" name="notes" class="form-control" maxlength="500" placeholder="Notes sur la partie...">
            </div>

            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Créer la partie</button>
        </form>
    </div>
</div>

<!-- Liste des parties -->
<div class="card">
    <div class="card-header">
        <h3>Parties de cette session (<?= count($games) ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($games)): ?>
            <p class="text-muted text-center">Aucune partie pour le moment. Créez-en une ci-dessus.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type de jeu</th>
                            <th>Joueurs</th>
                            <th>Manches</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                        <tr>
                            <td><strong><?= e($g['game_type_name']) ?></strong></td>
                            <td><?= (int) $g['player_count'] ?></td>
                            <td><?= (int) $g['round_count'] ?></td>
                            <td>
                                <?php
                                $gStatus = match ($g['status']) {
                                    'pending'     => ['Attente', 'badge-secondary'],
                                    'in_progress' => ['En cours', 'badge-primary'],
                                    'paused'      => ['Pause', 'badge-warning'],
                                    'completed'   => ['Terminée', 'badge-success'],
                                    default       => [$g['status'], ''],
                                };
                                ?>
                                <span class="badge <?= $gStatus[1] ?>"><?= $gStatus[0] ?></span>
                            </td>
                            <td>
                                <a href="/competition/games/<?= $g['id'] ?>" class="btn btn-sm btn-outline" title="Gérer">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
    'competition_restricted' => in_array((int) $p['id'], array_map('intval', $restrictedCompetitionPlayerIds ?? []), true)
], $players)) ?>;

const participantCards = <?= json_encode(array_map(static fn($c) => [
    'player_id' => (int) ($c['player_id'] ?? 0),
    'player_name' => (string) ($c['player_name'] ?? ''),
    'linked_username' => $c['linked_username'] ?? null,
    'reference' => (string) ($c['reference'] ?? ''),
    'is_active' => (int) ($c['is_active'] ?? 0),
    'signature_valid' => !empty($c['signature_valid']),
], $participantCards ?? []), JSON_UNESCAPED_UNICODE) ?>;

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
            updatePlayerCountInfo();
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
let selectedPlayerIds = new Set();

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
            <div class="autocomplete-item ${p.competition_restricted ? 'is-disabled' : ''}" style="padding:0.75rem;border-bottom:1px solid var(--gray-light);cursor:${p.competition_restricted ? 'not-allowed' : 'pointer'};opacity:${p.competition_restricted ? '0.65' : '1'};display:flex;justify-content:space-between;align-items:center;gap:0.5rem;" data-id="${p.id}">
                <span>${p.name}</span>
                ${p.competition_restricted ? '<span class="badge badge-warning">Restreint</span>' : ''}
            </div>
        `).join('');
    }

    document.querySelectorAll('#player_list .autocomplete-item').forEach(item => {
        item.addEventListener('click', () => {
            const player = allPlayers.find(p => p.id == item.dataset.id);
            if (!player || player.competition_restricted) {
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

updatePlayerCountInfo();

// ==== Vérificateur carte membre (participants uniquement) ====
const participantCardInput = document.getElementById('participant_card_search');
const participantCardHidden = document.getElementById('participant_card_player_id');
const participantCardOptions = document.getElementById('participant_card_options');
const participantCardReferenceInput = document.getElementById('participant_card_reference');
const participantCardReferenceOptions = document.getElementById('participant_card_reference_options');
const participantCardForm = document.getElementById('participant-card-verifier-form');

function hideParticipantCardOptions() {
    if (participantCardOptions) {
        participantCardOptions.style.display = 'none';
    }
}

function hideParticipantCardReferenceOptions() {
    if (participantCardReferenceOptions) {
        participantCardReferenceOptions.style.display = 'none';
    }
}

if (
    participantCardInput
    && participantCardHidden
    && participantCardOptions
    && participantCardReferenceInput
    && participantCardReferenceOptions
    && participantCardForm
) {
    function renderParticipantCardOptions(items) {
        if (items.length === 0) {
            participantCardOptions.innerHTML = '<div style="padding:0.65rem;color:var(--gray);">Aucun participant correspondant.</div>';
            participantCardOptions.style.display = 'block';
            return;
        }

        participantCardOptions.innerHTML = items.map((card) => (
            '<div class="participant-card-option" data-player-id="' + card.player_id + '" data-player-name="' + card.player_name.replace(/"/g, '&quot;') + '" style="padding:0.65rem;border-bottom:1px solid var(--gray-light);cursor:pointer;display:flex;justify-content:space-between;gap:0.5rem;">'
            + '<span>' + card.player_name + '</span>'
            + (card.linked_username ? '<span class="text-muted text-small">@' + card.linked_username + '</span>' : '<span class="text-muted text-small">sans compte</span>')
            + '</div>'
        )).join('');

        participantCardOptions.querySelectorAll('.participant-card-option').forEach((el) => {
            el.addEventListener('click', () => {
                participantCardHidden.value = el.dataset.playerId;
                participantCardInput.value = el.dataset.playerName;
                hideParticipantCardOptions();
            });
        });

        participantCardOptions.style.display = 'block';
    }

    function renderParticipantCardReferenceOptions(items) {
        if (items.length === 0) {
            participantCardReferenceOptions.innerHTML = '<div style="padding:0.65rem;color:var(--gray);">Aucune carte correspondante.</div>';
            participantCardReferenceOptions.style.display = 'block';
            return;
        }

        participantCardReferenceOptions.innerHTML = items.map((card) => {
            const badges = [];
            badges.push(card.is_active ? 'active' : 'inactive');
            badges.push(card.signature_valid ? 'signature ok' : 'signature invalide');

            return '<div class="participant-reference-option" data-reference="' + card.reference.replace(/"/g, '&quot;') + '" style="padding:0.65rem;border-bottom:1px solid var(--gray-light);cursor:pointer;display:flex;justify-content:space-between;gap:0.5rem;">'
                + '<span><code>' + card.reference + '</code></span>'
                + '<span class="text-muted text-small">' + badges.join(' • ') + '</span>'
                + '</div>';
        }).join('');

        participantCardReferenceOptions.querySelectorAll('.participant-reference-option').forEach((el) => {
            el.addEventListener('click', () => {
                participantCardReferenceInput.value = el.dataset.reference;
                hideParticipantCardReferenceOptions();
            });
        });

        participantCardReferenceOptions.style.display = 'block';
    }

    participantCardInput.addEventListener('focus', () => {
        renderParticipantCardOptions(participantCards);
    });

    participantCardInput.addEventListener('input', () => {
        const query = participantCardInput.value.trim().toLowerCase();
        participantCardHidden.value = '';
        const filtered = query === ''
            ? participantCards
            : participantCards.filter((card) =>
                card.player_name.toLowerCase().includes(query)
                || (card.linked_username && card.linked_username.toLowerCase().includes(query))
            );
        renderParticipantCardOptions(filtered);
    });

    participantCardReferenceInput.addEventListener('focus', () => {
        const playerId = parseInt(participantCardHidden.value || '0', 10);
        const source = playerId > 0
            ? participantCards.filter((card) => card.player_id === playerId)
            : participantCards;
        renderParticipantCardReferenceOptions(source);
    });

    participantCardReferenceInput.addEventListener('input', () => {
        const query = participantCardReferenceInput.value.trim().toUpperCase();
        const playerId = parseInt(participantCardHidden.value || '0', 10);
        const source = playerId > 0
            ? participantCards.filter((card) => card.player_id === playerId)
            : participantCards;
        const filtered = query === ''
            ? source
            : source.filter((card) => card.reference.toUpperCase().includes(query));
        renderParticipantCardReferenceOptions(filtered);
    });

    participantCardForm.addEventListener('submit', (event) => {
        if (!participantCardHidden.value) {
            event.preventDefault();
            alert('Veuillez sélectionner un compétiteur inscrit.');
            return;
        }
        if (!participantCardReferenceInput.value.trim()) {
            event.preventDefault();
            alert('Veuillez sélectionner une référence de carte.');
        }
    });
}

document.addEventListener('click', (event) => {
    if (!event.target.closest('#member-card-verifier .autocomplete-wrapper')) {
        hideParticipantCardOptions();
        hideParticipantCardReferenceOptions();
    }
});
</script>
