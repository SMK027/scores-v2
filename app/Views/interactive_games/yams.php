<?php
$players = $session['players'];
$myPlayer = null;
foreach ($players as $p) {
    if ((int)$p['user_id'] === $currentUserId) { $myPlayer = $p; break; }
}
$isPlayer = ($myPlayer !== null);
$playerKey = $myPlayer ? 'player' . $myPlayer['player_number'] : null;
$state = $session['game_state'];
$isGlobalStaff = $isGlobalStaff ?? false;
$isSolo = (count($players) === 1 && (int)$session['max_players'] === 1);

$categories = [
    'ones'            => ['label' => 'As (1)',          'section' => 'upper'],
    'twos'            => ['label' => 'Deux (2)',        'section' => 'upper'],
    'threes'          => ['label' => 'Trois (3)',       'section' => 'upper'],
    'fours'           => ['label' => 'Quatre (4)',      'section' => 'upper'],
    'fives'           => ['label' => 'Cinq (5)',        'section' => 'upper'],
    'sixes'           => ['label' => 'Six (6)',         'section' => 'upper'],
    'three_of_kind'   => ['label' => 'Brelan',         'section' => 'lower'],
    'four_of_kind'    => ['label' => 'Carré',          'section' => 'lower'],
    'full_house'      => ['label' => 'Full',           'section' => 'lower'],
    'small_straight'  => ['label' => 'Petite suite',   'section' => 'lower'],
    'large_straight'  => ['label' => 'Grande suite',   'section' => 'lower'],
    'yams'            => ['label' => 'YAMS',           'section' => 'lower'],
    'chance'          => ['label' => 'Chance',         'section' => 'lower'],
];
?>

<div class="page-header">
    <div>
        <h1>🎲 YAMS</h1>
        <p class="text-muted text-small">
            <?php if ($isSolo): ?>
                <?= e($players[0]['username']) ?> — Partie solo
            <?php else: ?>
                <?php
                $names = array_map(fn($p) => e($p['username']), $players);
                echo implode(' vs ', $names);
                if (count($players) < (int)$session['max_players'] && $session['status'] === 'waiting') {
                    echo ' — <em>En attente de joueurs…</em>';
                }
                ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-1">
        <a href="/spaces/<?= $currentSpace['id'] ?>/play" class="btn btn-outline btn-sm">← Retour au lobby</a>
        <?php if ((int)$session['created_by'] === $currentUserId && $session['status'] === 'in_progress'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $session['id'] ?>/pause" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm" data-confirm="Mettre en pause ?">⏸ Pause</button>
            </form>
        <?php endif; ?>
        <?php if ((int)$session['created_by'] === $currentUserId && $session['status'] === 'paused'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $session['id'] ?>/resume" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success btn-sm">▶ Reprendre</button>
            </form>
        <?php endif; ?>
        <?php if ((int)$session['created_by'] === $currentUserId && in_array($session['status'], ['waiting', 'in_progress', 'paused'])): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $session['id'] ?>/cancel" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="Supprimer cette partie ?">🗑️ Supprimer</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Statut -->
<div id="yams-status" class="card mb-3">
    <div class="card-body" style="text-align:center;padding:.75rem;">
        <?php if ($session['status'] === 'waiting'): ?>
            <span class="badge badge-warning">⏳ En attente de joueurs… (<span id="player-count"><?= count($players) ?>/<?= $session['max_players'] ?></span>)</span>
        <?php elseif ($session['status'] === 'paused'): ?>
            <span class="badge badge-secondary" style="font-size:1.1em;">⏸ Partie en pause</span>
        <?php elseif ($session['status'] === 'completed'): ?>
            <?php if ($session['winner_id']): ?>
                <span class="badge badge-success" style="font-size:1.1em;">🏆 <?= e($session['winner_name']) ?> a gagné !</span>
            <?php elseif ($isSolo): ?>
                <span class="badge badge-success" style="font-size:1.1em;">🏁 Partie terminée !</span>
            <?php else: ?>
                <span class="badge badge-secondary" style="font-size:1.1em;">🤝 Égalité !</span>
            <?php endif; ?>
            <?php if (!empty($state['final_scores'])): ?>
                <div style="margin-top:.5rem;" class="text-small">
                    <?php foreach ($players as $i => $p):
                        $pk = 'player' . $p['player_number'];
                    ?>
                        <?php if ($i > 0): ?>&nbsp;|&nbsp;<?php endif; ?>
                        <?= e($p['username']) ?> : <strong><?= $state['final_scores'][$pk] ?? 0 ?></strong>
                        <?php if (($state['final_scores']['bonus' . $p['player_number']] ?? 0) > 0): ?>
                            <span class="text-muted">(+35 bonus)</span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($session['status'] === 'cancelled'): ?>
            <span class="badge badge-secondary">Partie annulée</span>
        <?php else: ?>
            <strong id="turn-indicator"></strong>
            <span id="rolls-left" class="text-small text-muted" style="margin-left:1rem;"></span>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($session['status'], ['in_progress', 'paused', 'completed'])): ?>

<!-- Dés -->
<div id="dice-area" style="display:flex;justify-content:center;gap:.75rem;margin:1.5rem 0;flex-wrap:wrap;align-items:center;">
    <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="yams-die" data-index="<?= $i ?>" data-kept="false">
            <span class="yams-die-value"><?= $state['current_dice'][$i] ?? 1 ?></span>
        </div>
    <?php endfor; ?>
</div>

<?php if ($session['status'] === 'in_progress'): ?>
<div style="text-align:center;margin-bottom:1.5rem;">
    <button id="btn-roll" class="btn btn-primary">🎲 Lancer les dés</button>
    <p class="text-small text-muted" style="margin-top:.25rem;">Cliquez sur un dé pour le garder/relâcher</p>
</div>
<?php endif; ?>

<?php if ($isGlobalStaff && $session['status'] === 'in_progress'): ?>
<!-- Panneau mode développeur (admin global uniquement) -->
<div id="dev-panel" class="card mb-3" style="border:2px dashed var(--warning,#f59e0b);">
    <div class="card-header" style="background:rgba(245,158,11,.1);">
        <h3 style="margin:0;font-size:.9em;">🛠️ Mode développeur</h3>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;justify-content:center;">
            <label class="text-small">Dés :</label>
            <?php for ($i = 0; $i < 5; $i++): ?>
                <select class="dev-die-select" data-index="<?= $i ?>" style="width:50px;padding:.25rem;border-radius:4px;border:1px solid var(--border,#e5e7eb);text-align:center;">
                    <?php for ($v = 1; $v <= 6; $v++): ?>
                        <option value="<?= $v ?>" <?= ($state['current_dice'][$i] ?? 1) === $v ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endfor; ?>
                </select>
            <?php endfor; ?>
            <button id="btn-dev-set" class="btn btn-warning btn-sm">Appliquer</button>
            <span class="text-small text-muted" style="margin-left:.5rem;">|  Lancers illimités activés</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tableau des scores -->
<div class="card">
    <div class="card-header">
        <h3>Feuille de scores</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table" id="yams-scoresheet">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <?php foreach ($players as $p): ?>
                            <th style="text-align:center;"><?= e($p['username']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $lastSection = '';
                    foreach ($categories as $key => $cat):
                        if ($cat['section'] !== $lastSection):
                            $lastSection = $cat['section'];
                    ?>
                        <tr class="yams-section-header yams-section-toggle" data-section-toggle="<?= $cat['section'] ?>" role="button" aria-expanded="true">
                            <td colspan="<?= 1 + count($players) ?>"><strong><span class="yams-chevron">▼</span> <?= $cat['section'] === 'upper' ? '🔢 Partie haute' : '🎯 Combinaisons' ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <tr data-category="<?= $key ?>" data-section="<?= $cat['section'] ?>">
                        <td><?= $cat['label'] ?></td>
                        <?php foreach ($players as $p):
                            $pk = 'player' . $p['player_number'];
                        ?>
                            <td style="text-align:center;" class="score-cell" data-player="<?= $pk ?>">
                                <?= isset($state['scores'][$pk][$key]) ? (int)$state['scores'][$pk][$key] : '—' ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Bonus partie haute -->
                    <?php
                    $upperCats = ['ones', 'twos', 'threes', 'fours', 'fives', 'sixes'];
                    $phpTotals = [];
                    $phpBonuses = [];
                    foreach ($players as $p) {
                        $pk = 'player' . $p['player_number'];
                        $upperSum = 0;
                        $total = 0;
                        foreach ($state['scores'][$pk] ?? [] as $cat => $val) {
                            $total += (int)$val;
                            if (in_array($cat, $upperCats, true)) $upperSum += (int)$val;
                        }
                        $bonus = $upperSum >= 63 ? 35 : 0;
                        $phpBonuses[$pk] = $bonus;
                        $phpTotals[$pk] = $total + $bonus;
                        $phpUpperSums[$pk] = $upperSum;
                    }
                    ?>
                    <tr class="yams-section-header">
                        <td colspan="<?= 1 + count($players) ?>"><strong>📊 Totaux</strong></td>
                    </tr>
                    <tr>
                        <td>Bonus (≥63 partie haute → +35)</td>
                        <?php foreach ($players as $p):
                            $pk = 'player' . $p['player_number'];
                        ?>
                            <td style="text-align:center;" id="bonus-player<?= $p['player_number'] ?>"><?= $phpBonuses[$pk] > 0 ? '+35' : '(' . $phpUpperSums[$pk] . '/63)' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr style="font-weight:bold;">
                        <td>Total</td>
                        <?php foreach ($players as $p):
                            $pk = 'player' . $p['player_number'];
                        ?>
                            <td style="text-align:center;" id="total-player<?= $p['player_number'] ?>"><?= $phpTotals[$pk] ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php if ($session['status'] === 'in_progress' && $isPlayer): ?>
<script>
(function() {
    const spaceId = <?= (int)$currentSpace['id'] ?>;
    const sessionId = <?= (int)$session['id'] ?>;
    const currentUserId = <?= $currentUserId ?>;
    const players = <?= json_encode(array_values($players)) ?>;
    const playerKey = 'player' + <?= $myPlayer ? (int)$myPlayer['player_number'] : 0 ?>;
    const stateUrl = `/spaces/${spaceId}/play/${sessionId}/state`;
    const playUrl = `/spaces/${spaceId}/play/${sessionId}/play`;
    const devMode = <?= $isGlobalStaff ? 'true' : 'false' ?>;
    const isSolo = <?= $isSolo ? 'true' : 'false' ?>;

    let currentState = <?= json_encode($state) ?>;
    let currentTurn = <?= $session['current_turn'] ? (int)$session['current_turn'] : 'null' ?>;
    let gameStatus = '<?= $session['status'] ?>';
    let kept = [false, false, false, false, false];

    const dice = document.querySelectorAll('.yams-die');
    const btnRoll = document.getElementById('btn-roll');
    const turnIndicator = document.getElementById('turn-indicator');
    const rollsLeft = document.getElementById('rolls-left');

    const dieFaces = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];

    // Calcul de preview des scores selon les dés actuels
    function previewScore(category, dice) {
        const counts = {};
        let sum = 0;
        dice.forEach(d => { counts[d] = (counts[d] || 0) + 1; sum += d; });
        const maxCount = Math.max(...Object.values(counts));
        const sorted = [...dice].sort((a,b) => a - b);

        switch (category) {
            case 'ones':   return (counts[1] || 0) * 1;
            case 'twos':   return (counts[2] || 0) * 2;
            case 'threes': return (counts[3] || 0) * 3;
            case 'fours':  return (counts[4] || 0) * 4;
            case 'fives':  return (counts[5] || 0) * 5;
            case 'sixes':  return (counts[6] || 0) * 6;
            case 'three_of_kind': return maxCount >= 3 ? sum : 0;
            case 'four_of_kind':  return maxCount >= 4 ? sum : 0;
            case 'full_house': {
                const vals = Object.values(counts);
                return (vals.includes(3) && vals.includes(2)) ? 25 : 0;
            }
            case 'small_straight': return hasStraight(sorted, 4) ? 30 : 0;
            case 'large_straight': return hasStraight(sorted, 5) ? 40 : 0;
            case 'yams':   return maxCount >= 5 ? 50 : 0;
            case 'chance': return sum;
            default: return 0;
        }
    }

    function hasStraight(sorted, length) {
        const unique = [...new Set(sorted)];
        let consec = 1;
        for (let i = 1; i < unique.length; i++) {
            if (unique[i] === unique[i-1] + 1) { consec++; if (consec >= length) return true; }
            else consec = 1;
        }
        return false;
    }

    function updateUI() {
        // Dés
        dice.forEach((die, i) => {
            const val = currentState.current_dice[i];
            die.querySelector('.yams-die-value').textContent = dieFaces[val - 1] || val;
            die.classList.toggle('yams-die--kept', kept[i]);
        });

        // Scores
        const categories = [
            'ones', 'twos', 'threes', 'fours', 'fives', 'sixes',
            'three_of_kind', 'four_of_kind', 'full_house',
            'small_straight', 'large_straight', 'yams', 'chance'
        ];

        const upperCats = ['ones', 'twos', 'threes', 'fours', 'fives', 'sixes'];
        let totals = {};
        let uppers = {};
        players.forEach(p => {
            const pk = 'player' + p.player_number;
            totals[pk] = 0;
            uppers[pk] = 0;
        });

        categories.forEach(cat => {
            const row = document.querySelector(`tr[data-category="${cat}"]`);
            if (!row) return;

            players.forEach(p => {
                const pk = 'player' + p.player_number;
                const cell = row.querySelector(`.score-cell[data-player="${pk}"]`);
                if (!cell) return;

                const score = currentState.scores[pk]?.[cat];

                if (score !== undefined) {
                    cell.textContent = score;
                    cell.classList.remove('yams-score--available');
                    totals[pk] += score;
                    if (upperCats.includes(cat)) uppers[pk] += score;
                } else if (gameStatus === 'in_progress' && currentTurn === currentUserId && pk === playerKey && (currentState.rolls_left < 3 || devMode)) {
                    const pv = previewScore(cat, currentState.current_dice);
                    cell.textContent = pv;
                    cell.classList.add('yams-score--available');
                    cell.classList.toggle('yams-score--zero', pv === 0);
                } else {
                    cell.textContent = '—';
                    cell.classList.remove('yams-score--available');
                }
            });
        });

        // Totaux
        players.forEach(p => {
            const pk = 'player' + p.player_number;
            const upper = uppers[pk];
            const bonus = upper >= 63 ? 35 : 0;
            const bonusEl = document.getElementById('bonus-' + pk);
            const totalEl = document.getElementById('total-' + pk);
            if (bonusEl) bonusEl.textContent = bonus > 0 ? '+' + bonus : '(' + upper + '/63)';
            if (totalEl) totalEl.textContent = totals[pk] + bonus;
        });

        // Tour
        if (turnIndicator) {
            if (currentTurn === currentUserId) {
                turnIndicator.textContent = isSolo ? '🟢 À vous de jouer !' : '🟢 C\'est votre tour !';
                turnIndicator.style.color = 'var(--success, #22c55e)';
            } else if (!isSolo) {
                const cp = players.find(p => p.user_id === currentTurn);
                turnIndicator.textContent = '⏳ Tour de ' + (cp ? cp.username : "l'adversaire") + '…';
                turnIndicator.style.color = 'var(--text-muted, #6b7280)';
            }
        }

        if (rollsLeft) {
            if (devMode) {
                rollsLeft.textContent = 'Lancers restants : ∞ (dev)';
            } else {
                rollsLeft.textContent = 'Lancers restants : ' + (currentState.rolls_left ?? 0);
            }
        }

        // Bouton lancer
        if (btnRoll) {
            if (devMode) {
                btnRoll.disabled = (gameStatus !== 'in_progress' || currentTurn !== currentUserId);
            } else {
                btnRoll.disabled = (gameStatus !== 'in_progress' || currentTurn !== currentUserId || (currentState.rolls_left ?? 0) <= 0);
            }
        }

        // Auto-expand de la section contenant des scores disponibles (mobile)
        autoExpandAvailableSection();
    }

    // --- Sections repliables ---
    const isMobile = window.matchMedia('(max-width: 768px)').matches;

    function toggleSection(section, forceExpand) {
        const toggle = document.querySelector(`tr[data-section-toggle="${section}"]`);
        if (!toggle) return;
        const rows = document.querySelectorAll(`tr[data-section="${section}"]`);
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        const shouldCollapse = forceExpand !== undefined ? !forceExpand : isExpanded;

        rows.forEach(row => row.classList.toggle('yams-row-hidden', shouldCollapse));
        toggle.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true');
    }

    function autoExpandAvailableSection() {
        if (!isMobile) return;
        const hasAvailableInSection = (section) => {
            return document.querySelectorAll(`tr[data-section="${section}"] .yams-score--available`).length > 0;
        };
        // On expand la section qui a des scores disponibles,
        // on replie l'autre seulement si celle-ci en a
        const upperHas = hasAvailableInSection('upper');
        const lowerHas = hasAvailableInSection('lower');
        if (upperHas && !lowerHas) {
            toggleSection('upper', true);
            toggleSection('lower', false);
        } else if (lowerHas && !upperHas) {
            toggleSection('lower', true);
            toggleSection('upper', false);
        }
    }

    // Bind des clics sur les en-têtes de section
    document.querySelectorAll('.yams-section-toggle').forEach(header => {
        header.addEventListener('click', function() {
            const section = this.dataset.sectionToggle;
            toggleSection(section);
        });
    });

    // Sur mobile, replier les deux sections par défaut
    if (isMobile) {
        toggleSection('upper', false);
        toggleSection('lower', false);
    }

    function syncDevSelects() {
        if (!devMode) return;
        document.querySelectorAll('.dev-die-select').forEach((sel, i) => {
            sel.value = currentState.current_dice[i];
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Clic sur dé pour garder/relâcher
    dice.forEach(die => {
        die.addEventListener('click', function() {
            if (gameStatus !== 'in_progress' || currentTurn !== currentUserId) return;
            if (currentState.rolls_left >= 3) return;
            const idx = parseInt(this.dataset.index);
            kept[idx] = !kept[idx];
            this.classList.toggle('yams-die--kept', kept[idx]);
        });
    });

    // Lancer les dés
    if (btnRoll) {
        btnRoll.addEventListener('click', async function() {
            if (gameStatus !== 'in_progress' || currentTurn !== currentUserId) return;
            if (!devMode && (currentState.rolls_left ?? 0) <= 0) return;

            btnRoll.disabled = true;

            try {
                const payload = { action: 'roll', kept: kept };
                if (devMode) payload.dev_mode = true;
                const resp = await fetch(playUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (data.error) {
                    btnRoll.disabled = false;
                    return;
                }
                currentState = data.game_state;
                currentTurn = data.current_turn;
                gameStatus = data.status;
                syncDevSelects();
                updateUI();
            } catch(e) {
                btnRoll.disabled = false;
            }
        });
    }

    // Clic sur cellule de score disponible
    document.getElementById('yams-scoresheet').addEventListener('click', async function(e) {
        const cell = e.target.closest('.yams-score--available');
        if (!cell) return;
        if (gameStatus !== 'in_progress' || currentTurn !== currentUserId) return;

        const row = cell.closest('tr');
        const category = row?.dataset.category;
        if (!category) return;

        cell.classList.remove('yams-score--available');
        cell.textContent = '…';

        try {
            const resp = await fetch(playUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'score', category: category }),
            });
            const data = await resp.json();
            if (data.error) {
                updateUI();
                return;
            }
            currentState = data.game_state;
            currentTurn = data.current_turn;
            gameStatus = data.status;
            kept = [false, false, false, false, false];
            updateUI();

            if (data.status === 'completed') {
                showEndStatus(data);
            }
        } catch(e) {
            updateUI();
        }
    });

    function showEndStatus(data) {
        const statusDiv = document.getElementById('yams-status');
        if (!statusDiv) return;
        const body = statusDiv.querySelector('.card-body');
        let html = '';
        if (data.winner_id && !isSolo) {
            html += '<span class="badge badge-success" style="font-size:1.1em;">🏆 ' + escapeHtml(data.winner_name || 'Gagnant') + ' a gagné !</span>';
        } else if (isSolo) {
            html += '<span class="badge badge-success" style="font-size:1.1em;">🏁 Partie terminée !</span>';
        } else {
            html += '<span class="badge badge-secondary" style="font-size:1.1em;">🤝 Égalité !</span>';
        }
        if (data.game_state && data.game_state.final_scores) {
            html += '<div style="margin-top:.5rem;" class="text-small">';
            players.forEach(function(p, i) {
                const pk = 'player' + p.player_number;
                if (i > 0) html += '&nbsp;|&nbsp;';
                html += escapeHtml(p.username) + ' : <strong>' + (data.game_state.final_scores[pk] ?? 0) + '</strong>';
                const bonus = data.game_state.final_scores['bonus' + p.player_number] ?? 0;
                if (bonus > 0) html += ' <span class="text-muted">(+35 bonus)</span>';
            });
            html += '</div>';
        }
        body.innerHTML = html;
        if (btnRoll) btnRoll.style.display = 'none';
    }

    // Polling
    let pollInterval = setInterval(async () => {
        if (gameStatus === 'completed' || gameStatus === 'cancelled') {
            clearInterval(pollInterval);
            return;
        }
        try {
            const resp = await fetch(stateUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await resp.json();
            currentState = data.game_state;
            currentTurn = data.current_turn;
            gameStatus = data.status;
            if (currentTurn !== currentUserId) {
                kept = [false, false, false, false, false];
            }
            updateUI();
            if (data.status === 'completed') {
                showEndStatus(data);
                clearInterval(pollInterval);
            }
        } catch(e) {}
    }, 2000);

    // Init
    updateUI();
    syncDevSelects();

    // Dev mode: bouton "Appliquer" pour forcer les valeurs des dés
    if (devMode) {
        const btnDevSet = document.getElementById('btn-dev-set');
        if (btnDevSet) {
            btnDevSet.addEventListener('click', async function() {
                if (gameStatus !== 'in_progress' || currentTurn !== currentUserId) return;
                const newDice = [];
                document.querySelectorAll('.dev-die-select').forEach(sel => {
                    newDice.push(parseInt(sel.value));
                });

                btnDevSet.disabled = true;
                try {
                    const resp = await fetch(playUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ action: 'set_dice', dice: newDice }),
                    });
                    const data = await resp.json();
                    if (!data.error) {
                        currentState = data.game_state;
                        currentTurn = data.current_turn;
                        gameStatus = data.status;
                        updateUI();
                    }
                } catch(e) {}
                btnDevSet.disabled = false;
            });
        }
    }
})();
</script>
<?php endif; ?>

<!-- Toggle repliable pour tous les états (spectateur, terminé, en pause) -->
<?php if (!($session['status'] === 'in_progress' && $isPlayer)): ?>
<script>
(function() {
    const isMobile = window.matchMedia('(max-width: 768px)').matches;

    function toggleSection(section, forceExpand) {
        const toggle = document.querySelector('tr[data-section-toggle="' + section + '"]');
        if (!toggle) return;
        const rows = document.querySelectorAll('tr[data-section="' + section + '"]');
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        const shouldCollapse = forceExpand !== undefined ? !forceExpand : isExpanded;
        rows.forEach(function(row) { row.classList.toggle('yams-row-hidden', shouldCollapse); });
        toggle.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true');
    }

    document.querySelectorAll('.yams-section-toggle').forEach(function(header) {
        header.addEventListener('click', function() {
            toggleSection(this.dataset.sectionToggle);
        });
    });

    if (isMobile) {
        toggleSection('upper', false);
        toggleSection('lower', false);
    }
})();
</script>
<?php endif; ?>

<?php if ($session['status'] === 'waiting'): ?>
<script>
(function() {
    const stateUrl = `/spaces/<?= (int)$currentSpace['id'] ?>/play/<?= (int)$session['id'] ?>/state`;
    let poll = setInterval(async () => {
        try {
            const resp = await fetch(stateUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await resp.json();
            const counter = document.getElementById('player-count');
            if (counter && data.players) {
                counter.textContent = data.players.length + '/' + data.max_players;
            }
            if (data.status !== 'waiting') {
                clearInterval(poll);
                location.reload();
            }
        } catch(e) {}
    }, 2000);
})();
</script>
<?php endif; ?>
