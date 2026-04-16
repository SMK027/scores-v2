<div class="page-header">
    <h1>🕹️ Jeux en ligne</h1>
    <a href="/spaces/<?= $currentSpace['id'] ?>/lobbies" class="btn btn-outline btn-sm">🏠 Salons de jeu</a>
</div>

<?php
// Vérifier si le joueur a une partie active
$hasActive = false;
foreach ($sessions as $s) {
    if (in_array($s['status'], ['waiting', 'in_progress', 'paused'])) {
        $playerIds = $s['player_user_ids'] ? array_map('intval', explode(',', $s['player_user_ids'])) : [];
        if (in_array($currentUserId, $playerIds, true)) {
            $hasActive = true;
            break;
        }
    }
}
?>

<!-- Catalogue des jeux -->
<div class="card-grid" style="margin-bottom:2rem;">
    <?php foreach ($games as $key => $game): ?>
    <div class="card">
        <div class="card-body" style="text-align:center;">
            <div style="font-size:2.5rem;margin-bottom:.5rem;"><?= $game['icon'] ?></div>
            <h3 style="margin:0 0 .5rem;"><?= e($game['name']) ?></h3>
            <p class="text-muted text-small"><?= e($game['description']) ?></p>
            <p class="text-small text-muted"><?= $game['min_players'] ?>–<?= $game['max_players'] ?> joueurs</p>
            <?php if ($hasActive): ?>
                <p class="text-small text-danger" style="margin-top:.75rem;">Vous avez déjà une partie en cours.</p>
            <?php else: ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/create" style="margin-top:.75rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="game_key" value="<?= $key ?>">
                <?php if ($game['min_players'] !== $game['max_players']): ?>
                    <div style="margin-bottom:.5rem;">
                        <label class="text-small">Nombre de joueurs :</label>
                        <select name="max_players" style="padding:.25rem .5rem;border-radius:4px;border:1px solid var(--border,#e5e7eb);">
                            <?php for ($p = $game['min_players']; $p <= $game['max_players']; $p++): ?>
                                <option value="<?= $p ?>"<?= $p === 2 ? ' selected' : '' ?>><?= $p === 1 ? 'Solo' : "$p joueurs" ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="max_players" value="<?= $game['max_players'] ?>">
                <?php endif; ?>
                <?php if ($key === 'morpion'): ?>
                    <div style="margin-bottom:.5rem;">
                        <label class="text-small">Taille de grille :</label>
                        <select name="grid_size" class="morpion-grid-select" style="padding:.25rem .5rem;border-radius:4px;border:1px solid var(--border,#e5e7eb);">
                            <?php foreach (\App\Models\InteractiveGameSession::MORPION_GRIDS as $sz => $info): ?>
                                <option value="<?= $sz ?>" data-aligns="<?= e(json_encode($info['aligns'])) ?>"<?= $sz === 3 ? ' selected' : '' ?>><?= e($info['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="morpion-align-row" style="margin-bottom:.5rem;display:none;">
                        <label class="text-small">Alignement pour gagner :</label>
                        <select name="align_count" class="morpion-align-select" style="padding:.25rem .5rem;border-radius:4px;border:1px solid var(--border,#e5e7eb);"></select>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-sm">Créer une partie</button>
            </form>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/create" style="margin-top:.5rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="game_key" value="<?= $key ?>">
                <input type="hidden" name="max_players" value="2">
                <input type="hidden" name="vs_bot" value="1">
                <?php if ($key === 'morpion'): ?>
                <input type="hidden" name="grid_size" class="bot-grid-size" value="3">
                <input type="hidden" name="align_count" class="bot-align-count" value="3">
                <?php endif; ?>
                <div style="display:flex;align-items:center;justify-content:center;gap:.5rem;margin-bottom:.5rem;">
                    <label class="text-small">Difficulté :</label>
                    <select name="bot_difficulty" style="padding:.25rem .5rem;border-radius:4px;border:1px solid var(--border,#e5e7eb);font-size:.85rem;">
                        <option value="easy">🟢 Facile</option>
                        <option value="medium" selected>🟡 Moyen</option>
                        <option value="hard">🔴 Difficile</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">🤖 vs Robot</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Sessions actives -->
<div class="card">
    <div class="card-header">
        <h3>📋 Parties en cours</h3>
    </div>
    <div class="card-body">
        <?php
        $activeSessions = array_filter($sessions, fn($s) => in_array($s['status'], ['waiting', 'in_progress', 'paused']));
        ?>
        <?php if (empty($activeSessions)): ?>
            <p class="text-muted">Aucune partie active pour le moment. Créez-en une !</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Jeu</th>
                            <th>Joueurs</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeSessions as $s):
                            $playerIds = $s['player_user_ids'] ? array_map('intval', explode(',', $s['player_user_ids'])) : [];
                            $isInGame = in_array($currentUserId, $playerIds, true);
                            $isCreator = ((int)$s['created_by'] === $currentUserId);
                        ?>
                        <tr>
                            <td>
                                <?= \App\Models\InteractiveGameSession::GAMES[$s['game_key']]['icon'] ?? '' ?>
                                <?= e(\App\Models\InteractiveGameSession::GAMES[$s['game_key']]['name'] ?? $s['game_key']) ?>
                            </td>
                            <td>
                                <?= e($s['player_names'] ?? '') ?>
                                <span class="text-muted text-small">(<?= (int)$s['player_count'] ?>/<?= (int)$s['max_players'] ?>)</span>
                            </td>
                            <td>
                                <?php if ($s['status'] === 'waiting'): ?>
                                    <span class="badge badge-warning">En attente</span>
                                <?php elseif ($s['status'] === 'paused'): ?>
                                    <span class="badge badge-secondary">⏸ En pause</span>
                                <?php else: ?>
                                    <span class="badge badge-success">En cours</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                            <td>
                                <?php if ($s['status'] === 'waiting' && !$isInGame && !$hasActive): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>/join" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-success btn-sm">Rejoindre</button>
                                    </form>
                                <?php elseif ($isInGame): ?>
                                    <a href="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
                                <?php endif; ?>
                                <?php if ($isCreator && $s['status'] === 'in_progress'): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>/pause" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline btn-sm" data-confirm="Mettre en pause ?">⏸ Pause</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($isCreator && $s['status'] === 'paused'): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>/resume" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-success btn-sm">▶ Reprendre</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($isCreator && in_array($s['status'], ['waiting', 'in_progress', 'paused'])): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>/cancel" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="Supprimer cette partie ?">🗑️ Supprimer</button>
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

<!-- Historique -->
<?php
$completedSessions = array_filter($sessions, fn($s) => in_array($s['status'], ['completed', 'cancelled']));
?>
<?php if (!empty($completedSessions)): ?>
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <h3>📜 Historique</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Jeu</th>
                        <th>Joueurs</th>
                        <th>Résultat</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedSessions as $s): ?>
                    <tr>
                        <td>
                            <?= \App\Models\InteractiveGameSession::GAMES[$s['game_key']]['icon'] ?? '' ?>
                            <?= e(\App\Models\InteractiveGameSession::GAMES[$s['game_key']]['name'] ?? $s['game_key']) ?>
                        </td>
                        <td><?= e($s['player_names'] ?? '—') ?></td>
                        <td>
                            <?php if ($s['status'] === 'cancelled'): ?>
                                <span class="badge badge-secondary">Annulée</span>
                            <?php elseif ($s['winner_id']): ?>
                                <span class="badge badge-success">
                                    🏆 <?= e($s['winner_name'] ?? '?') ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Égalité</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                        <td>
                            <a href="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.morpion-grid-select').forEach(function(gridSelect) {
    const form = gridSelect.closest('form');
    const alignRow = form.querySelector('.morpion-align-row');
    const alignSelect = form.querySelector('.morpion-align-select');
    if (!alignRow || !alignSelect) return;

    function updateAlignOptions() {
        const selected = gridSelect.options[gridSelect.selectedIndex];
        const aligns = JSON.parse(selected.dataset.aligns || '[]');
        alignSelect.innerHTML = '';
        aligns.forEach(function(a) {
            const opt = document.createElement('option');
            opt.value = a;
            opt.textContent = a + ' symboles';
            alignSelect.appendChild(opt);
        });
        if (aligns.length > 1) {
            alignRow.style.display = '';
        } else {
            alignRow.style.display = 'none';
        }
        syncBotHiddenInputs();
    }

    gridSelect.addEventListener('change', updateAlignOptions);
    alignSelect.addEventListener('change', syncBotHiddenInputs);
    updateAlignOptions();
});

function syncBotHiddenInputs() {
    var gridSelect = document.querySelector('.morpion-grid-select');
    var alignSelect = document.querySelector('.morpion-align-select');
    if (!gridSelect || !alignSelect) return;
    document.querySelectorAll('.bot-grid-size').forEach(function(el) { el.value = gridSelect.value; });
    document.querySelectorAll('.bot-align-count').forEach(function(el) { el.value = alignSelect.value; });
}
</script>
