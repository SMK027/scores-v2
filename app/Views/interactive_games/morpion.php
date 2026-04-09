<?php
$players = $session['players'];
$myPlayer = null;
foreach ($players as $p) {
    if ((int)$p['user_id'] === $currentUserId) { $myPlayer = $p; break; }
}
$isPlayer = ($myPlayer !== null);
$mySymbol = ($myPlayer && (int)$myPlayer['player_number'] === 1) ? 'X' : 'O';
$state = $session['game_state'];
$player1 = $players[0] ?? null;
$player2 = $players[1] ?? null;
?>

<div class="page-header">
    <div>
        <h1>❌⭕ Morpion</h1>
        <p class="text-muted text-small">
            <?= $player1 ? e($player1['username']) . ' (X)' : '?' ?>
            vs
            <?= $player2 ? e($player2['username']) . ' (O)' : '<em>En attente d\'un adversaire…</em>' ?>
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

<!-- Info statut -->
<div id="morpion-status" class="card mb-3">
    <div class="card-body" style="text-align:center;padding:.75rem;">
        <?php if ($session['status'] === 'waiting'): ?>
            <span class="badge badge-warning">⏳ En attente d'un adversaire…</span>
        <?php elseif ($session['status'] === 'completed'): ?>
            <?php if ($session['winner_id']): ?>
                <span class="badge badge-success" style="font-size:1.1em;">🏆 <?= e($session['winner_name']) ?> a gagné !</span>
            <?php else: ?>
                <span class="badge badge-secondary" style="font-size:1.1em;">🤝 Match nul !</span>
            <?php endif; ?>
        <?php elseif ($session['status'] === 'cancelled'): ?>
            <span class="badge badge-secondary">Partie annulée</span>
        <?php else: ?>
            <strong id="turn-indicator"></strong>
        <?php endif; ?>
    </div>
</div>

<!-- Plateau de jeu -->
<div style="display:flex;justify-content:center;margin:1.5rem 0;">
    <div id="morpion-board" class="morpion-board">
        <?php for ($i = 0; $i < 9; $i++): ?>
            <div class="morpion-cell" data-index="<?= $i ?>">
                <?php if (($state['board'][$i] ?? null) === 'X'): ?>
                    <span class="morpion-x">✕</span>
                <?php elseif (($state['board'][$i] ?? null) === 'O'): ?>
                    <span class="morpion-o">◯</span>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<?php if ($session['status'] === 'in_progress' && $isPlayer): ?>
<script>
(function() {
    const spaceId = <?= (int)$currentSpace['id'] ?>;
    const sessionId = <?= (int)$session['id'] ?>;
    const currentUserId = <?= $currentUserId ?>;
    const mySymbol = '<?= $mySymbol ?>';
    const players = <?= json_encode(array_values($players)) ?>;
    const stateUrl = `/spaces/${spaceId}/play/${sessionId}/state`;
    const playUrl = `/spaces/${spaceId}/play/${sessionId}/play`;

    let currentState = <?= json_encode($state) ?>;
    let currentTurn = <?= $session['current_turn'] ? (int)$session['current_turn'] : 'null' ?>;
    let gameStatus = '<?= $session['status'] ?>';

    const board = document.getElementById('morpion-board');
    const cells = board.querySelectorAll('.morpion-cell');
    const turnIndicator = document.getElementById('turn-indicator');

    function updateTurnIndicator() {
        if (!turnIndicator) return;
        if (gameStatus === 'completed' || gameStatus === 'cancelled') return;
        if (currentTurn === currentUserId) {
            turnIndicator.textContent = '🟢 C\'est votre tour !';
            turnIndicator.style.color = 'var(--success, #22c55e)';
        } else {
            turnIndicator.textContent = '⏳ Tour de l\'adversaire…';
            turnIndicator.style.color = 'var(--text-muted, #6b7280)';
        }
    }

    function renderBoard() {
        cells.forEach((cell, i) => {
            const val = currentState.board[i];
            if (val === 'X') {
                cell.innerHTML = '<span class="morpion-x">✕</span>';
            } else if (val === 'O') {
                cell.innerHTML = '<span class="morpion-o">◯</span>';
            } else {
                cell.innerHTML = '';
            }
            cell.classList.toggle('morpion-cell--clickable',
                gameStatus === 'in_progress' && currentTurn === currentUserId && val === null);
        });
    }

    function showEndStatus(data) {
        const statusDiv = document.getElementById('morpion-status');
        if (!statusDiv) return;
        const body = statusDiv.querySelector('.card-body');
        if (data.winner_id) {
            body.innerHTML = `<span class="badge badge-success" style="font-size:1.1em;">🏆 ${escapeHtml(data.winner_name || 'Gagnant')} a gagné !</span>`;
        } else {
            body.innerHTML = '<span class="badge badge-secondary" style="font-size:1.1em;">🤝 Match nul !</span>';
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Jouer un coup
    cells.forEach(cell => {
        cell.addEventListener('click', async function() {
            if (gameStatus !== 'in_progress' || currentTurn !== currentUserId) return;
            const idx = parseInt(this.dataset.index);
            if (currentState.board[idx] !== null) return;

            // Optimistic update
            currentState.board[idx] = mySymbol;
            currentTurn = null;
            renderBoard();

            try {
                const resp = await fetch(playUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ cell: idx }),
                });
                const data = await resp.json();
                if (data.error) {
                    // Rollback
                    currentState.board[idx] = null;
                    renderBoard();
                    return;
                }
                currentState = data.game_state;
                currentTurn = data.current_turn;
                gameStatus = data.status;
                renderBoard();
                updateTurnIndicator();
                if (data.status === 'completed') {
                    showEndStatus(data);
                }
            } catch (e) {
                currentState.board[idx] = null;
                renderBoard();
            }
        });
    });

    // Polling pour l'état adverse
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
            renderBoard();
            updateTurnIndicator();
            if (data.status === 'completed') {
                showEndStatus(data);
                clearInterval(pollInterval);
            }
        } catch(e) {}
    }, 2000);

    // Init
    renderBoard();
    updateTurnIndicator();
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
