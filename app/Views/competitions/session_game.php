<div class="page-header">
    <div>
        <h1>
            <?= e($game['game_type_name']) ?>
            <?php
            $gStatus = match ($game['status']) {
                'pending'     => ['Attente', 'badge-secondary'],
                'in_progress' => ['En cours', 'badge-primary'],
                'paused'      => ['Pause', 'badge-warning'],
                'completed'   => ['Terminée', 'badge-success'],
                default       => [$game['status'], ''],
            };
            ?>
            <span class="badge <?= $gStatus[1] ?>" style="font-size:0.5em;vertical-align:middle;"><?= $gStatus[0] ?></span>
        </h1>
        <p class="text-muted text-small">
            Session #<?= (int) $session['session_number'] ?> — <?= e($session['referee_name']) ?>
            <?php if ($game['notes']): ?>
                — <?= e($game['notes']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-1">
        <a href="/competition/dashboard" class="btn btn-outline btn-sm">← Dashboard</a>
        <?php if ($game['status'] !== 'completed'): ?>
            <form method="POST" action="/competition/games/<?= $game['id'] ?>/complete" style="display:inline;">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-success" data-confirm="Terminer cette partie ?">✓ Terminer la partie</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Joueurs -->
<div class="card mb-3">
    <div class="card-header"><h3>Joueurs (<?= count($gamePlayers) ?>)</h3></div>
    <div class="card-body">
        <div class="d-flex gap-1 flex-wrap">
            <?php foreach ($gamePlayers as $gp): ?>
                <span class="badge" style="font-size:0.9em;padding:0.3rem 0.6rem;">
                    <?= e($gp['player_name']) ?>
                    <?php if ($gp['total_score'] !== null): ?>
                        — <?= number_format((float) $gp['total_score'], 1) ?>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Manches -->
<div class="card mb-3">
    <div class="card-header d-flex justify-between align-center">
        <h3>Manches (<?= count($rounds) ?>)</h3>
        <?php
        $hasActiveRound = false;
        foreach ($rounds as $rCheck) {
            if (($rCheck['status'] ?? '') !== 'completed') {
                $hasActiveRound = true;
                break;
            }
        }
        ?>
        <?php if ($game['status'] !== 'completed'): ?>
            <form method="POST" action="/competition/games/<?= $game['id'] ?>/rounds/create">
                <?= csrf_field() ?>
                <button
                    class="btn btn-sm btn-primary"
                    <?= $hasActiveRound ? 'disabled' : '' ?>
                    title="<?= $hasActiveRound ? 'Terminez la manche en cours avant d\'en créer une nouvelle.' : 'Ajouter une manche' ?>"
                ><i class="bi bi-plus-circle"></i> Manche</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($rounds)): ?>
            <p class="text-muted text-center">Aucune manche. Ajoutez-en une pour commencer la saisie des scores.</p>
        <?php else: ?>
            <?php foreach ($rounds as $round): ?>
                <?php
                $rid = $round['id'];
                $rScores = $roundScores[$rid] ?? [];
                $isCompleted = $round['status'] === 'completed';
                $isPaused = $round['status'] === 'paused';
                $dur = $roundDurations[$rid] ?? ['play' => 0, 'pause' => 0];
                ?>
                <div class="card mb-2" style="border-left:3px solid <?= $isCompleted ? 'var(--success)' : ($isPaused ? 'var(--warning)' : 'var(--primary)') ?>;">
                    <div class="card-body">
                        <div class="d-flex justify-between align-center mb-1">
                            <strong>Manche <?= (int) $round['round_number'] ?></strong>
                            <div class="d-flex gap-05 align-center">
                                <?php if ($dur['play'] > 0): ?>
                                    <span class="text-muted text-small">
                                        <?= gmdate('H:i:s', $dur['play']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$isCompleted): ?>
                                    <?php if (($round['status'] ?? '') === 'in_progress'): ?>
                                        <form method="POST" action="/competition/games/<?= $game['id'] ?>/rounds/<?= $rid ?>/status" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="paused">
                                            <button type="submit" class="btn btn-sm btn-warning" data-confirm="Mettre cette manche en pause ?" title="Pause">⏸</button>
                                        </form>
                                    <?php elseif (($round['status'] ?? '') === 'paused'): ?>
                                        <form method="POST" action="/competition/games/<?= $game['id'] ?>/rounds/<?= $rid ?>/status" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="btn btn-sm btn-success" data-confirm="Reprendre cette manche ?" title="Reprendre">▶</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($isCompleted): ?>
                                    <span class="badge badge-success">Terminée</span>
                                <?php else: ?>
                                    <span class="badge <?= ($round['status'] ?? '') === 'paused' ? 'badge-warning' : 'badge-primary' ?>">
                                        <?= ($round['status'] ?? '') === 'paused' ? 'En pause' : 'En cours' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="POST" action="/competition/games/<?= $game['id'] ?>/rounds/<?= $rid ?>/delete" class="mb-1" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                            <?= csrf_field() ?>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Motif obligatoire pour supprimer la manche" maxlength="255" required style="max-width:340px;">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer cette manche et ses scores ? Le motif sera journalise.">🗑 Supprimer</button>
                        </form>

                        <?php if (!$isCompleted): ?>
                            <!-- Formulaire de saisie des scores -->
                            <form method="POST" action="/competition/games/<?= $game['id'] ?>/rounds/<?= $rid ?>/scores">
                                <?= csrf_field() ?>

                                <?php if ($game['win_condition'] === 'win_loss'): ?>
                                    <p class="text-small text-muted mb-1">Cochez le(s) gagnant(s) :</p>
                                    <div class="d-flex gap-1 flex-wrap mb-1">
                                        <?php foreach ($gamePlayers as $gp): ?>
                                            <label class="d-flex align-center gap-05" style="cursor:pointer;padding:0.25rem 0.5rem;background:var(--bg-secondary);border-radius:6px;">
                                                <input type="checkbox" name="scores[<?= $gp['player_id'] ?>]" value="1"
                                                    <?= isset($rScores[$gp['player_id']]) && (int) $rScores[$gp['player_id']]['score'] === 1 ? 'checked' : '' ?>>
                                                <?= e($gp['player_name']) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex gap-1 flex-wrap mb-1">
                                        <?php foreach ($gamePlayers as $gp): ?>
                                            <div class="form-group" style="min-width:100px;flex:1;">
                                                <label class="form-label text-small"><?= e($gp['player_name']) ?></label>
                                                <input type="number" step="any" name="scores[<?= $gp['player_id'] ?>]"
                                                       class="form-control form-control-sm"
                                                       value="<?= isset($rScores[$gp['player_id']]) ? e((string) $rScores[$gp['player_id']]['score']) : '' ?>"
                                                       placeholder="Score">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-check-circle"></i> Valider les scores
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Scores affichés (manche terminée) -->
                            <div class="d-flex gap-1 flex-wrap">
                                <?php foreach ($gamePlayers as $gp): ?>
                                    <?php
                                    $scoreVal = $rScores[$gp['player_id']]['score'] ?? null;
                                    if ($scoreVal === null) continue;

                                    // Déterminer le meilleur score
                                    $allScores = array_column($rScores, 'score');
                                    if ($game['win_condition'] === 'ranking' || $game['win_condition'] === 'lowest_score') {
                                        $bestScore = min($allScores);
                                    } else {
                                        $bestScore = max($allScores);
                                    }
                                    $isWinner = (float) $scoreVal === (float) $bestScore;
                                    ?>
                                    <span class="badge <?= $isWinner ? 'badge-success' : '' ?>" style="font-size:0.85em;padding:0.25rem 0.5rem;">
                                        <?php if ($isWinner): ?>🏆<?php endif; ?>
                                        <?= e($gp['player_name']) ?> : <?= $scoreVal ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
