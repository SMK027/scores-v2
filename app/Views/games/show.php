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
                        <th class="text-right">Score total</th>
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
                            <td class="text-right"><?= $gp['total_score'] ?? '-' ?></td>
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
                            <span class="badge <?= $round['status'] === 'completed' ? 'badge-success' : 'badge-info' ?>" style="font-size:0.75em;">
                                <?= $round['status'] === 'completed' ? 'Terminée' : 'En cours' ?>
                            </span>
                            <?php if ($round['status'] !== 'completed' && in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
                                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/<?= $round['id'] ?>/status" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-sm btn-success" data-confirm="Terminer cette manche ?">Terminer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($round['notes'])): ?>
                        <p class="text-muted text-small mb-1"><?= e($round['notes']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($roundScores[$round['id']])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Joueur</th>
                                        <th class="text-right">Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gamePlayers as $gp): ?>
                                        <?php if (isset($roundScores[$round['id']][$gp['player_id']])): ?>
                                            <tr>
                                                <td><?= e($gp['player_name']) ?></td>
                                                <td class="text-right"><?= $roundScores[$round['id']][$gp['player_id']]['score'] ?? '-' ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-small">Aucun score enregistré.</p>
                    <?php endif; ?>

                    <?php if ($round['status'] !== 'completed' && in_array($spaceRole, ['admin', 'manager', 'member'])): ?>
                        <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>/rounds/<?= $round['id'] ?>/scores" class="score-form">
                            <?= csrf_field() ?>
                            <div class="d-flex gap-1 flex-wrap align-center mt-1">
                                <?php foreach ($gamePlayers as $gp): ?>
                                    <div style="display:flex;flex-direction:column;min-width:100px;">
                                        <label class="text-small"><?= e($gp['player_name']) ?></label>
                                        <input type="number" name="scores[<?= $gp['player_id'] ?>]" class="form-control form-control-sm"
                                               step="0.01" placeholder="Score"
                                               value="<?= $roundScores[$round['id']][$gp['player_id']]['score'] ?? '' ?>">
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end;">Enregistrer</button>
                            </div>
                        </form>
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
                        <?php if (!empty($comment['avatar_url'])): ?>
                            <img src="<?= e($comment['avatar_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
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
