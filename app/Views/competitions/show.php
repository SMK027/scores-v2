<div class="page-header">
    <div>
        <h1>
            🏆 <?= e($competition['name']) ?>
            <?php
            $statusBadge = match ($competition['status']) {
                'planned' => 'badge-warning',
                'active'  => 'badge-success',
                'paused'  => 'badge-info',
                'closed'  => 'badge-secondary',
                default   => '',
            };
            $statusLabel = match ($competition['status']) {
                'planned' => 'Planifiée',
                'active'  => 'Active',
                'paused'  => 'En pause',
                'closed'  => 'Clôturée',
                default   => $competition['status'],
            };
            ?>
            <span class="badge <?= $statusBadge ?>" style="font-size:0.5em;vertical-align:middle;"><?= $statusLabel ?></span>
        </h1>
        <p class="text-muted text-small">
            Du <?= date('d/m/Y H:i', strtotime($competition['starts_at'])) ?>
            au <?= date('d/m/Y H:i', strtotime($competition['ends_at'])) ?>
            — Créée par <?= e($competition['creator_name'] ?? 'Inconnu') ?>
        </p>
        <?php if ($competition['description']): ?>
            <p><?= nl2br(e($competition['description'])) ?></p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1 flex-wrap">
        <a href="/spaces/<?= $currentSpace['id'] ?>/competitions" class="btn btn-outline btn-sm">← Retour</a>
        <?php if ($isStaff): ?>
            <?php if ($competition['status'] !== 'closed'): ?>
                <a href="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/edit" class="btn btn-sm btn-outline" title="Modifier">
                    <i class="bi bi-pencil"></i> Modifier
                </a>
            <?php endif; ?>
            <?php if ($competition['status'] === 'planned'): ?>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/activate" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-success" data-confirm="Activer la compétition ?">▶ Activer</button>
                </form>
            <?php endif; ?>
            <?php if ($competition['status'] === 'active'): ?>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/pause" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-info" data-confirm="Mettre en pause ? Les arbitres ne pourront plus saisir.">⏸ Pause</button>
                </form>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/close" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-warning" data-confirm="Clôturer ? Toutes les sessions seront désactivées.">⏹ Clôturer</button>
                </form>
            <?php endif; ?>
            <?php if ($competition['status'] === 'paused'): ?>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/resume" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-success" data-confirm="Reprendre la compétition ?">▶ Reprendre</button>
                </form>
                <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/close" style="display:inline;">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-warning" data-confirm="Clôturer ? Toutes les sessions seront désactivées.">⏹ Clôturer</button>
                </form>
            <?php endif; ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/delete" style="display:inline;">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-danger" data-confirm="Supprimer cette compétition ?"><i class="bi bi-trash"></i></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Sessions -->
<div class="card mb-3">
    <div class="card-header d-flex justify-between align-center">
        <h3>Sessions (<?= count($sessions) ?>)</h3>
        <?php if ($isStaff && $competition['status'] !== 'closed'): ?>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/competitions/<?= $competition['id'] ?>/sessions/add" class="d-flex gap-1">
                <?= csrf_field() ?>
                <input type="text" name="referee_name" class="form-control form-control-sm" placeholder="Nom de l'arbitre" required style="width:200px;">
                <button class="btn btn-sm btn-primary">+ Session</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($sessions)): ?>
            <p class="text-muted text-center">Aucune session.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Arbitre</th>
                            <?php if ($isStaff): ?>
                                <th>Mot de passe</th>
                            <?php endif; ?>
                            <th>Parties</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><strong><?= (int) $s['session_number'] ?></strong></td>
                            <td><?= e($s['referee_name']) ?></td>
                            <?php if ($isStaff): ?>
                                <td><code><?= e($s['password']) ?></code></td>
                            <?php endif; ?>
                            <td><?= (int) $s['game_count'] ?></td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($isStaff && $competition['status'] === 'active'): ?>
                <div class="card mt-2" style="background:var(--bg-secondary); border:1px dashed var(--border-color);">
                    <div class="card-body text-small">
                        <strong>Codes de connexion arbitres :</strong>
                        <p class="text-muted">Competition ID : <code><?= (int) $competition['id'] ?></code></p>
                        <p class="text-muted">URL de connexion : <code>/competition/login</code></p>
                        <ul style="list-style:none;padding:0;">
                            <?php foreach ($sessions as $s): ?>
                                <?php if ($s['is_active']): ?>
                                <li>
                                    Session <strong>#<?= (int) $s['session_number'] ?></strong>
                                    (<?= e($s['referee_name']) ?>) —
                                    Mot de passe : <code><?= e($s['password']) ?></code>
                                </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Classement -->
<?php if (!empty($rankings)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h3>Classement</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Joueur</th>
                        <th>Manches jouées</th>
                        <th>Manches gagnées</th>
                        <th>Taux de victoire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $i => $r): ?>
                    <tr>
                        <td>
                            <?php if ($i === 0): ?>🥇
                            <?php elseif ($i === 1): ?>🥈
                            <?php elseif ($i === 2): ?>🥉
                            <?php else: ?><?= $i + 1 ?>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><?= (int) $r['rounds_played'] ?></td>
                        <td><?= (int) $r['rounds_won'] ?></td>
                        <td><?= $r['win_rate'] ?> %</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Parties de la compétition -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Parties (<?= count($games) ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($games)): ?>
            <p class="text-muted text-center">Aucune partie enregistrée.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Session</th>
                            <th>Joueurs</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                        <tr>
                            <td><?= e($g['game_type_name']) ?></td>
                            <td>
                                #<?= (int) $g['session_number'] ?>
                                <span class="text-muted text-small">(<?= e($g['referee_name'] ?? '') ?>)</span>
                            </td>
                            <td><?= (int) $g['player_count'] ?></td>
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
                            <td><?= date('d/m H:i', strtotime($g['created_at'])) ?></td>
                            <td>
                                <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $g['id'] ?>" class="btn btn-sm btn-outline" title="Voir">
                                    <i class="bi bi-eye"></i>
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
