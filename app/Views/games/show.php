<div class="page-header">
    <div>
        <h1>
            <?= e($game['game_type_name']) ?>
            <span class="badge <?= game_status_class($game['status']) ?>" style="font-size:0.6em;vertical-align:middle;">
                <?= game_status_label($game['status']) ?>
            </span>
        </h1>
        <p class="text-muted text-small">
            Créée <?= time_ago($game['created_at']) ?> par <?= e($game['creator_name'] ?? 'Inconnu') ?>
        </p>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <a href="/spaces/<?= $currentSpace['id'] ?>/games" class="btn btn-outline btn-sm">← Retour</a>
        <?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
            <?php if ($game['status'] !== 'completed'): ?>
                <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/edit" class="btn btn-sm btn-outline">Modifier</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Gestion du statut -->
<?php if (in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
<div class="card mb-3">
    <div class="card-body d-flex gap-1 flex-wrap align-center">
        <strong class="text-small">Statut :</strong>
        <?php
        $transitions = [
            'pending'     => [['in_progress', 'Démarrer', 'btn-success']],
            'in_progress' => [['paused', 'Pause', 'btn-warning'], ['completed', 'Terminer', 'btn-success']],
            'paused'      => [['in_progress', 'Reprendre', 'btn-success'], ['completed', 'Terminer', 'btn-success']],
            'completed'   => [],
        ];
        foreach ($transitions[$game['status']] ?? [] as $tr):
        ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/status" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="<?= $tr[0] ?>">
                <button type="submit" class="btn btn-sm <?= $tr[2] ?>" data-confirm="Changer le statut ?"><?= $tr[1] ?></button>
            </form>
        <?php endforeach; ?>
        <?php if ($game['status'] === 'completed'): ?>
            <span class="text-success">✓ Partie terminée</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Notes -->
<?php if (!empty($game['notes'])): ?>
<div class="card mb-3">
    <div class="card-header"><h3>Notes</h3></div>
    <div class="card-body">
        <p><?= nl2br(e($game['notes'])) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Durée de la partie -->
<?php if (!empty($rounds)): ?>
<div class="card mb-3">
    <div class="card-header"><h3>⏱ Durée de la partie</h3></div>
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap" style="font-size:1.1em;">
            <div>
                <strong>Temps de jeu effectif :</strong>
                <span style="color:var(--primary);font-weight:bold;"><?= format_duration($totalPlaySeconds) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Classement / Joueurs -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Joueurs & Classement</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Joueur</th>
                        <th class="text-right">
                            <?php if ($game['win_condition'] === 'ranking'): ?>
                                Total positions
                            <?php elseif ($game['win_condition'] === 'win_loss'): ?>
                                Victoires
                            <?php else: ?>
                                Score total
                            <?php endif; ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gamePlayers as $gp): ?>
                        <tr <?= ($gp['rank'] === 1 && $game['status'] === 'completed') ? 'style="background:var(--warning-light,#fff9e6);font-weight:bold;"' : '' ?>>
                            <td>
                                <?php if ($gp['rank']): ?>
                                    <?php if ($gp['rank'] === 1 && $game['status'] === 'completed'): ?>
                                        🏆 <?= $gp['rank'] ?>
                                    <?php else: ?>
                                        <?= $gp['rank'] ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= e($gp['player_name']) ?></td>
                            <td class="text-right">
                                <?php if ($game['win_condition'] === 'win_loss'): ?>
                                    <?= (int)($gp['total_score'] ?? 0) ?> victoire<?= ((int)($gp['total_score'] ?? 0) > 1) ? 's' : '' ?>
                                <?php else: ?>
                                    <?= $gp['total_score'] ?? '-' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manches -->
<div class="card mb-3">
    <div class="card-header d-flex justify-between align-center">
        <h3>Manches</h3>
        <?php if (in_array($game['status'], ['in_progress', 'paused']) && in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/create" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-primary">+ Ajouter une manche</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($rounds)): ?>
            <p class="text-muted text-center">Aucune manche enregistrée.</p>
        <?php else: ?>
            <?php foreach ($rounds as $idx => $round): ?>
                <div class="round-block mb-2" style="border:1px solid var(--gray-light);border-radius:var(--radius);padding:1rem;">
                    <div class="d-flex justify-between align-center mb-1">
                        <strong>Manche <?= $idx + 1 ?></strong>
                        <div class="d-flex gap-1 align-center">
                            <span class="badge <?= round_status_class($round['status']) ?>" style="font-size:0.75em;">
                                <?= round_status_label($round['status']) ?>
                            </span>
                            <?php if ($round['status'] !== 'completed' && in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
                                <?php if ($round['status'] === 'in_progress'): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/<?= $round['id'] ?>/status" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="status" value="paused">
                                        <button type="submit" class="btn btn-sm btn-warning" data-confirm="Mettre cette manche en pause ?">⏸ Pause</button>
                                    </form>
                                <?php elseif ($round['status'] === 'paused'): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/<?= $round['id'] ?>/status" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="btn btn-sm btn-success" data-confirm="Reprendre cette manche ?">▶ Reprendre</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/<?= $round['id'] ?>/status" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-sm btn-success" data-confirm="Terminer cette manche ?">✓ Terminer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (isset($roundDurations[$round['id']])): ?>
                        <?php $dur = $roundDurations[$round['id']]; ?>
                        <div class="text-muted text-small mb-1" style="display:flex;gap:1rem;flex-wrap:wrap;">
                            <span>⏱ Jeu : <strong><?= format_duration($dur['play']) ?></strong></span>
                            <?php if ($dur['pause'] > 0): ?>
                                <span>⏸ Pause : <?= format_duration($dur['pause']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($round['started_at'])): ?>
                                <span>Début : <?= format_date($round['started_at'], 'd/m/Y H:i') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($round['ended_at'])): ?>
                                <span>Fin : <?= format_date($round['ended_at'], 'd/m/Y H:i') ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($round['notes'])): ?>
                        <p class="text-muted text-small mb-1"><?= e($round['notes']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($roundScores[$round['id']])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Joueur</th>
                                        <th class="text-right">
                                            <?php if ($game['win_condition'] === 'ranking'): ?>
                                                Position
                                            <?php elseif ($game['win_condition'] === 'win_loss'): ?>
                                                Résultat
                                            <?php else: ?>
                                                Score
                                            <?php endif; ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gamePlayers as $gp): ?>
                                        <?php if (isset($roundScores[$round['id']][$gp['player_id']])): ?>
                                            <tr>
                                                <td><?= e($gp['player_name']) ?></td>
                                                <td class="text-right">
                                                    <?php if ($game['win_condition'] === 'ranking'): ?>
                                                        <?= (int)$roundScores[$round['id']][$gp['player_id']]['score'] ?>e
                                                    <?php elseif ($game['win_condition'] === 'win_loss'): ?>
                                                        <?= $roundScores[$round['id']][$gp['player_id']]['score'] == 1 ? '✓ Victoire' : '✗ Défaite' ?>
                                                    <?php else: ?>
                                                        <?= $roundScores[$round['id']][$gp['player_id']]['score'] ?? '-' ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-small">Aucun score enregistré.</p>
                    <?php endif; ?>

                    <?php
                        $canEditScores = $round['status'] !== 'completed' && in_array($spaceRole, ['admin', 'manager', 'member']);
                        $canCorrectScores = $round['status'] === 'completed' && in_array($spaceRole, ['admin', 'manager']);
                    ?>

                    <?php if ($canEditScores): ?>
                        <?php if ($game['win_condition'] === 'ranking'): ?>
                            <p class="text-muted text-small mb-1"><em>Saisissez la position finale de chaque joueur (1 = 1ère place, 2 = 2ème place, etc.)</em></p>
                        <?php elseif ($game['win_condition'] === 'win_loss'): ?>
                            <p class="text-muted text-small mb-1"><em>Cochez le(s) gagnant(s) de cette manche</em></p>
                        <?php endif; ?>
                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/<?= $round['id'] ?>/scores" class="score-form">
                            <?= csrf_field() ?>
                            <div class="d-flex gap-1 flex-wrap align-center mt-1">
                                <?php if ($game['win_condition'] === 'ranking'): ?>
                                    <?php foreach ($gamePlayers as $gp): ?>
                                        <div style="display:flex;flex-direction:column;min-width:100px;">
                                            <label class="text-small"><?= e($gp['player_name']) ?></label>
                                            <input type="number" name="scores[<?= $gp['player_id'] ?>]" class="form-control form-control-sm"
                                                   min="1" step="1" placeholder="Position"
                                                   value="<?= $roundScores[$round['id']][$gp['player_id']]['score'] ?? '' ?>">
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif ($game['win_condition'] === 'win_loss'): ?>
                                    <?php foreach ($gamePlayers as $gp): ?>
                                        <div style="display:flex;align-items:center;gap:0.5rem;min-width:150px;">
                                            <input type="checkbox" name="scores[<?= $gp['player_id'] ?>]" value="1" id="win_<?= $round['id'] ?>_<?= $gp['player_id'] ?>"
                                                   <?= (isset($roundScores[$round['id']][$gp['player_id']]) && $roundScores[$round['id']][$gp['player_id']]['score'] == 1) ? 'checked' : '' ?>>
                                            <label for="win_<?= $round['id'] ?>_<?= $gp['player_id'] ?>" class="text-small" style="margin:0;cursor:pointer;"><?= e($gp['player_name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($gamePlayers as $gp): ?>
                                        <div style="display:flex;flex-direction:column;min-width:100px;">
                                            <label class="text-small"><?= e($gp['player_name']) ?></label>
                                            <input type="number" name="scores[<?= $gp['player_id'] ?>]" class="form-control form-control-sm"
                                                   step="0.01" placeholder="Score"
                                                   value="<?= $roundScores[$round['id']][$gp['player_id']]['score'] ?? '' ?>">
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end;">Enregistrer</button>
                            </div>
                        </form>
                    <?php elseif ($canCorrectScores): ?>
                        <button type="button" class="btn btn-sm btn-outline mt-1" onclick="this.style.display='none';this.nextElementSibling.style.display='block';">
                            ✏️ Corriger les scores
                        </button>
                        <div style="display:none;">
                            <?php if ($game['win_condition'] === 'ranking'): ?>
                                <p class="text-muted text-small mb-1"><em>Corrigez la position finale de chaque joueur</em></p>
                            <?php elseif ($game['win_condition'] === 'win_loss'): ?>
                                <p class="text-muted text-small mb-1"><em>Corrigez le(s) gagnant(s) de cette manche</em></p>
                            <?php else: ?>
                                <p class="text-muted text-small mb-1"><em>Corrigez les scores de cette manche</em></p>
                            <?php endif; ?>
                            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/<?= $round['id'] ?>/scores" class="score-form">
                                <?= csrf_field() ?>
                                <div class="d-flex gap-1 flex-wrap align-center mt-1">
                                    <?php if ($game['win_condition'] === 'ranking'): ?>
                                        <?php foreach ($gamePlayers as $gp): ?>
                                            <div style="display:flex;flex-direction:column;min-width:100px;">
                                                <label class="text-small"><?= e($gp['player_name']) ?></label>
                                                <input type="number" name="scores[<?= $gp['player_id'] ?>]" class="form-control form-control-sm"
                                                       min="1" step="1" placeholder="Position"
                                                       value="<?= $roundScores[$round['id']][$gp['player_id']]['score'] ?? '' ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php elseif ($game['win_condition'] === 'win_loss'): ?>
                                        <?php foreach ($gamePlayers as $gp): ?>
                                            <div style="display:flex;align-items:center;gap:0.5rem;min-width:150px;">
                                                <input type="checkbox" name="scores[<?= $gp['player_id'] ?>]" value="1" id="edit_win_<?= $round['id'] ?>_<?= $gp['player_id'] ?>"
                                                       <?= (isset($roundScores[$round['id']][$gp['player_id']]) && $roundScores[$round['id']][$gp['player_id']]['score'] == 1) ? 'checked' : '' ?>>
                                                <label for="edit_win_<?= $round['id'] ?>_<?= $gp['player_id'] ?>" class="text-small" style="margin:0;cursor:pointer;"><?= e($gp['player_name']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php foreach ($gamePlayers as $gp): ?>
                                            <div style="display:flex;flex-direction:column;min-width:100px;">
                                                <label class="text-small"><?= e($gp['player_name']) ?></label>
                                                <input type="number" name="scores[<?= $gp['player_id'] ?>]" class="form-control form-control-sm"
                                                       step="0.01" placeholder="Score"
                                                       value="<?= $roundScores[$round['id']][$gp['player_id']]['score'] ?? '' ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm btn-warning" style="align-self:flex-end;">Corriger</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Commentaires -->
<div class="card mb-3">
    <div class="card-header"><h3>Commentaires (<?= count($comments) ?>)</h3></div>
    <div class="card-body">
        <?php if (empty($comments)): ?>
            <p class="text-muted text-center">Aucun commentaire.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment mb-2" style="display:flex;gap:0.75rem;padding:0.75rem 0;border-bottom:1px solid var(--gray-light);">
                    <div class="avatar" style="width:36px;height:36px;border-radius:50%;overflow:hidden;background:var(--gray-light);flex-shrink:0;">
                        <?php if (!empty($comment['avatar'])): ?>
                            <img src="<?= e($comment['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:var(--gray);">
                                <?= strtoupper(substr($comment['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div class="d-flex justify-between align-center">
                            <strong class="text-small"><?= e($comment['username']) ?></strong>
                            <div class="d-flex gap-1 align-center">
                                <span class="text-muted text-small"><?= time_ago($comment['created_at']) ?></span>
                                <?php if ($comment['user_id'] == current_user_id() || in_array($spaceRole, ['admin', 'manager'])): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/comments/<?= $comment['id'] ?>/delete" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Supprimer ce commentaire ?" style="padding:0.1rem 0.4rem;font-size:0.7rem;">×</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p style="margin:0.25rem 0 0;"><?= nl2br(e($comment['content'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (is_authenticated()): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/comments" class="mt-2">
                <?= csrf_field() ?>
                <div class="form-group">
                    <textarea name="content" class="form-control" rows="2" placeholder="Ajouter un commentaire..." required></textarea>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Commenter</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Actions dangereuses -->
<?php if (in_array($spaceRole, ['admin', 'manager'])): ?>
<div class="card mb-3" style="border-color:var(--danger);">
    <div class="card-header" style="background:var(--danger-light,#fff0f0);"><h3 style="color:var(--danger);">Zone de danger</h3></div>
    <div class="card-body">
        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/delete" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" data-confirm="Supprimer définitivement cette partie et toutes ses données ?">
                Supprimer la partie
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
