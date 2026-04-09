<?php
$isPlayer = ($currentUserId === (int)$session['player1_id'] || $currentUserId === (int)($session['player2_id'] ?? 0));
$playerKey = ($currentUserId === (int)$session['player1_id']) ? 'player1' : 'player2';
$state = $session['game_state'];
$isGlobalStaff = $isGlobalStaff ?? false;

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
            <?= e($session['player1_name']) ?>
            vs
            <?= $session['player2_name'] ? e($session['player2_name']) : '<em>En attente d\'un adversaire…</em>' ?>
        </p>
    </div>
    <div class="d-flex gap-1">
        <a href="/spaces/<?= $currentSpace['id'] ?>/play" class="btn btn-outline btn-sm">← Retour au lobby</a>
        <?php if ((int)$session['created_by'] === $currentUserId && $session['status'] === 'waiting'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $session['id'] ?>/cancel" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="Annuler cette partie ?">Annuler</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Statut -->
<div id="yams-status" class="card mb-3">
    <div class="card-body" style="text-align:center;padding:.75rem;">
        <?php if ($session['status'] === 'waiting'): ?>
            <span class="badge badge-warning">⏳ En attente d'un adversaire…</span>
        <?php elseif ($session['status'] === 'completed'): ?>
            <?php if ($session['winner_id']): ?>
                <span class="badge badge-success" style="font-size:1.1em;">🏆 <?= e($session['winner_name']) ?> a gagné !</span>
            <?php else: ?>
                <span class="badge badge-secondary" style="font-size:1.1em;">🤝 Égalité !</span>
            <?php endif; ?>
            <?php if (!empty($state['final_scores'])): ?>
                <div style="margin-top:.5rem;" class="text-small">
                    <?= e($session['player1_name']) ?> : <strong><?= $state['final_scores']['player1'] ?></strong>
                    <?php if ($state['final_scores']['bonus1'] > 0): ?><span class="text-muted">(+35 bonus)</span><?php endif; ?>
                    &nbsp;|&nbsp;
                    <?= e($session['player2_name']) ?> : <strong><?= $state['final_scores']['player2'] ?></strong>
                    <?php if ($state['final_scores']['bonus2'] > 0): ?><span class="text-muted">(+35 bonus)</span><?php endif; ?>
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

<?php if ($session['status'] === 'in_progress' || $session['status'] === 'completed'): ?>

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
            <span class="text-small text-muted" style="margin-left:.5rem;">|  Lancers illimités activés</span>
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
                        <th style="text-align:center;"><?= e($session['player1_name']) ?></th>
                        <th style="text-align:center;"><?= e($session['player2_name'] ?? '—') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $lastSection = '';
                    foreach ($categories as $key => $cat):
                        if ($cat['section'] !== $lastSection):
                            $lastSection = $cat['section'];
                    ?>
                        <tr class="yams-section-header">
                            <td colspan="3"><strong><?= $cat['section'] === 'upper' ? '🔢 Partie haute' : '🎯 Combinaisons' ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <tr data-category="<?= $key ?>">
                        <td><?= $cat['label'] ?></td>
                        <td style="text-align:center;" class="score-cell" data-player="player1">
                            <?= isset($state['scores']['player1'][$key]) ? (int)$state['scores']['player1'][$key] : '—' ?>
                        </td>
                        <td style="text-align:center;" class="score-cell" data-player="player2">
                            <?= isset($state['scores']['player2'][$key]) ? (int)$state['scores']['player2'][$key] : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Bonus partie haute -->
                    <tr class="yams-section-header">
                        <td colspan="3"><strong>📊 Totaux</strong></td>
                    </tr>
                    <tr>
                        <td>Bonus (≥63 partie haute → +35)</td>
                        <td style="text-align:center;" id="bonus-p1">—</td>
                        <td style="text-align:center;" id="bonus-p2">—</td>
                    </tr>
                    <tr style="font-weight:bold;">
                        <td>Total</td>
                        <td style="text-align:center;" id="total-p1">0</td>
                        <td style="text-align:center;" id="total-p2">0</td>
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
    const player1Id = <?= (int)$session['player1_id'] ?>;
    const player2Id = <?= (int)($session['player2_id'] ?? 0) ?>;
    const playerKey = currentUserId === player1Id ? 'player1' : 'player2';
    const stateUrl = `/spaces/${spaceId}/play/${sessionId}/state`;
    const playUrl = `/spaces/${spaceId}/play/${sessionId}/play`;
    const devMode = <?= $isGlobalStaff ? 'true' : 'false' ?>;

    let currentState = <?= json_encode($state) ?>;
    let currentTurn = <?= $session['current_turn'] ? (int)$session['current_turn'] : 'null' ?>;
    let gameStatus = '<?= $session['status'] ?>';
    let kept = [false, false, false, false, false];

    const dice = document.querySelectorAll('.yams-die');
    const btnRoll = document.getElementById('btn-roll');
    const turnIndicator = document.getElementById('turn-indicator');
    const rollsLeft = document.getElementById('rolls-left');

    const dieFaces = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];

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

        let totalP1 = 0, totalP2 = 0;
        let upperP1 = 0, upperP2 = 0;
        const upperCats = ['ones', 'twos', 'threes', 'fours', 'fives', 'sixes'];

        categories.forEach(cat => {
            const row = document.querySelector(`tr[data-category="${cat}"]`);
            if (!row) return;

            const cellP1 = row.querySelector('.score-cell[data-player="player1"]');
            const cellP2 = row.querySelector('.score-cell[data-player="player2"]');

            const s1 = currentState.scores.player1[cat];
            const s2 = currentState.scores.player2[cat];

            if (cellP1) {
                if (s1 !== undefined) {
                    cellP1.textContent = s1;
                    cellP1.classList.remove('yams-score--available');
                    totalP1 += s1;
                    if (upperCats.includes(cat)) upperP1 += s1;
                } else if (gameStatus === 'in_progress' && currentTurn === currentUserId && playerKey === 'player1' && (currentState.rolls_left < 3 || devMode)) {
                    cellP1.textContent = '✎';
                    cellP1.classList.add('yams-score--available');
                } else {
                    cellP1.textContent = '—';
                    cellP1.classList.remove('yams-score--available');
                }
            }

            if (cellP2) {
                if (s2 !== undefined) {
                    cellP2.textContent = s2;
                    cellP2.classList.remove('yams-score--available');
                    totalP2 += s2;
                    if (upperCats.includes(cat)) upperP2 += s2;
                } else if (gameStatus === 'in_progress' && currentTurn === currentUserId && playerKey === 'player2' && (currentState.rolls_left < 3 || devMode)) {
                    cellP2.textContent = '✎';
                    cellP2.classList.add('yams-score--available');
                } else {
                    cellP2.textContent = '—';
                    cellP2.classList.remove('yams-score--available');
                }
            }
        });

        const bonusP1 = upperP1 >= 63 ? 35 : 0;
        const bonusP2 = upperP2 >= 63 ? 35 : 0;
        document.getElementById('bonus-p1').textContent = bonusP1 > 0 ? `+${bonusP1}` : `(${upperP1}/63)`;
        document.getElementById('bonus-p2').textContent = bonusP2 > 0 ? `+${bonusP2}` : `(${upperP2}/63)`;
        document.getElementById('total-p1').textContent = totalP1 + bonusP1;
        document.getElementById('total-p2').textContent = totalP2 + bonusP2;

        // Tour
        if (turnIndicator) {
            if (currentTurn === currentUserId) {
                turnIndicator.textContent = '🟢 C\'est votre tour !';
                turnIndicator.style.color = 'var(--success, #22c55e)';
            } else {
                turnIndicator.textContent = '⏳ Tour de l\'adversaire…';
                turnIndicator.style.color = 'var(--text-muted, #6b7280)';
            }
        }

        if (rollsLeft) {
            if (devMode) {
                rollsLeft.textContent = 'Lancers restants : ∞ (dev)';
            } else {
                rollsLeft.textContent = `Lancers restants : ${currentState.rolls_left ?? 0}`;
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

        // Sync dev panel selects only on explicit sync call
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
            if (currentState.rolls_left >= 3) return; // Doit avoir lancé au moins une fois
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
        if (data.winner_id) {
            html += `<span class="badge badge-success" style="font-size:1.1em;">🏆 ${escapeHtml(data.winner_name || 'Gagnant')} a gagné !</span>`;
        } else {
            html += '<span class="badge badge-secondary" style="font-size:1.1em;">🤝 Égalité !</span>';
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

<?php if ($session['status'] === 'waiting'): ?>
<script>
(function() {
    const stateUrl = `/spaces/<?= (int)$currentSpace['id'] ?>/play/<?= (int)$session['id'] ?>/state`;
    let poll = setInterval(async () => {
        try {
            const resp = await fetch(stateUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await resp.json();
            if (data.status !== 'waiting') {
                clearInterval(poll);
                location.reload();
            }
        } catch(e) {}
    }, 2000);
})();
</script>
<?php endif; ?>
