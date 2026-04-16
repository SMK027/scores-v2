<div class="page-header">
    <div>
        <h1>🏠 Salons de jeu</h1>
        <p class="text-muted text-small">Créez ou rejoignez un salon pour jouer avec d'autres membres.</p>
    </div>
    <div class="d-flex gap-1">
        <a href="/spaces/<?= $currentSpace['id'] ?>/play" class="btn btn-outline btn-sm">← Jeux en ligne</a>
    </div>
</div>

<?php if (!empty($invitations)): ?>
<div class="card mb-3" style="border-left:4px solid var(--warning,#f59e0b);">
    <div class="card-header"><h3>📩 Invitations reçues</h3></div>
    <div class="card-body">
        <?php foreach ($invitations as $inv): ?>
        <div style="display:flex;align-items:center;gap:1rem;padding:.5rem 0;border-bottom:1px solid var(--border,#e5e7eb);">
            <div style="flex:1;">
                <strong><?= e($inv['lobby_name']) ?></strong>
                <span class="text-muted text-small">
                    — <?= e($games[$inv['game_key']]['name'] ?? $inv['game_key']) ?>
                    — invité par <?= e($inv['invited_by_name']) ?>
                </span>
            </div>
            <div class="d-flex gap-1">
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/invitations/<?= $inv['id'] ?>/accept" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success btn-sm">✓ Accepter</button>
                </form>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/invitations/<?= $inv['id'] ?>/decline" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm">✕ Décliner</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Créer un salon -->
<div class="card mb-3">
    <div class="card-header"><h3>➕ Créer un salon</h3></div>
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/create" class="lobby-create-form">
            <?= csrf_field() ?>
            <div class="lobby-create-fields">
                <div>
                    <label class="text-small">Nom du salon</label>
                    <input type="text" name="name" required maxlength="100" placeholder="Mon salon" class="form-control" style="padding:.35rem .5rem;">
                </div>
                <div>
                    <label class="text-small">Jeu</label>
                    <select name="game_key" id="lobby-game-select" class="form-control" style="padding:.35rem .5rem;">
                        <?php foreach ($games as $key => $g): ?>
                        <option value="<?= $key ?>" data-min="<?= $g['min_players'] ?>" data-max="<?= $g['max_players'] ?>"><?= e($g['icon'] . ' ' . $g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-small">Visibilité</label>
                    <select name="visibility" class="form-control" style="padding:.35rem .5rem;">
                        <option value="public">🌐 Public</option>
                        <option value="private">🔒 Privé</option>
                    </select>
                </div>
                <div id="lobby-max-players-wrap">
                    <label class="text-small">Joueurs max</label>
                    <select name="max_players" id="lobby-max-players" class="form-control" style="padding:.35rem .5rem;">
                    </select>
                </div>
                <div id="lobby-grid-wrap" style="display:none;">
                    <label class="text-small">Grille</label>
                    <select name="grid_size" id="lobby-grid-size" class="form-control" style="padding:.35rem .5rem;">
                        <?php foreach ($grids as $sz => $info): ?>
                        <option value="<?= $sz ?>" data-aligns="<?= e(json_encode($info['aligns'])) ?>"><?= e($info['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="lobby-align-wrap" style="display:none;">
                    <label class="text-small">Alignement</label>
                    <select name="align_count" id="lobby-align-count" class="form-control" style="padding:.35rem .5rem;"></select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des salons -->
<div class="card">
    <div class="card-header"><h3>🏠 Salons ouverts</h3></div>
    <div class="card-body">
        <?php if (empty($lobbies)): ?>
            <p class="text-muted">Aucun salon actif. Créez-en un !</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Salon</th>
                        <th>Jeu</th>
                        <th>Hôte</th>
                        <th>Joueurs</th>
                        <th>Visibilité</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lobbies as $l):
                        $isHost = ((int) $l['created_by'] === $currentUserId);
                        $isMember = false;
                        // Quick check (member count only in list, no full member list)
                    ?>
                    <tr>
                        <td><strong><?= e($l['name']) ?></strong></td>
                        <td>
                            <?= $games[$l['game_key']]['icon'] ?? '' ?>
                            <?= e($games[$l['game_key']]['name'] ?? $l['game_key']) ?>
                        </td>
                        <td><?= e($l['creator_name']) ?></td>
                        <td><?= (int) $l['member_count'] ?>/<?= (int) ($l['game_config']['max_players'] ?? '?') ?></td>
                        <td>
                            <?php if ($l['visibility'] === 'public'): ?>
                                <span class="badge badge-success">🌐 Public</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">🔒 Privé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($l['status'] === 'open'): ?>
                                <span class="badge badge-success">Ouvert</span>
                            <?php else: ?>
                                <span class="badge badge-warning">🎮 En jeu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/spaces/<?= $currentSpace['id'] ?>/lobbies/<?= $l['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
                            <?php if (!$isHost && $l['visibility'] === 'public' && $l['status'] === 'open'): ?>
                                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/lobbies/<?= $l['id'] ?>/join" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-success btn-sm">Rejoindre</button>
                                </form>
                            <?php endif; ?>
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
(function() {
    const gameSelect = document.getElementById('lobby-game-select');
    const maxPlayersSelect = document.getElementById('lobby-max-players');
    const maxPlayersWrap = document.getElementById('lobby-max-players-wrap');
    const gridWrap = document.getElementById('lobby-grid-wrap');
    const gridSelect = document.getElementById('lobby-grid-size');
    const alignWrap = document.getElementById('lobby-align-wrap');
    const alignSelect = document.getElementById('lobby-align-count');

    function updateGameOptions() {
        const opt = gameSelect.options[gameSelect.selectedIndex];
        const min = parseInt(opt.dataset.min);
        const max = parseInt(opt.dataset.max);
        const key = gameSelect.value;

        // Max players
        maxPlayersSelect.innerHTML = '';
        if (min === max) {
            maxPlayersSelect.innerHTML = '<option value="' + max + '">' + max + '</option>';
            maxPlayersWrap.style.display = 'none';
        } else {
            maxPlayersWrap.style.display = '';
            for (let i = min; i <= max; i++) {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = i + ' joueurs';
                if (i === 2) o.selected = true;
                maxPlayersSelect.appendChild(o);
            }
        }

        // Morpion grid options
        if (key === 'morpion') {
            gridWrap.style.display = '';
            updateAlignOptions();
        } else {
            gridWrap.style.display = 'none';
            alignWrap.style.display = 'none';
        }
    }

    function updateAlignOptions() {
        const opt = gridSelect.options[gridSelect.selectedIndex];
        const aligns = JSON.parse(opt.dataset.aligns || '[]');
        alignSelect.innerHTML = '';
        aligns.forEach(function(a) {
            const o = document.createElement('option');
            o.value = a;
            o.textContent = a + ' symboles';
            alignSelect.appendChild(o);
        });
        alignWrap.style.display = aligns.length > 1 ? '' : 'none';
    }

    gameSelect.addEventListener('change', updateGameOptions);
    gridSelect.addEventListener('change', updateAlignOptions);
    updateGameOptions();
})();
</script>
