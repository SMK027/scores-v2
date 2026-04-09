<div class="page-header">
    <h1>🕹️ Jeux en ligne</h1>
</div>

<!-- Catalogue des jeux -->
<div class="card-grid" style="margin-bottom:2rem;">
    <?php foreach ($games as $key => $game): ?>
    <div class="card">
        <div class="card-body" style="text-align:center;">
            <div style="font-size:2.5rem;margin-bottom:.5rem;"><?= $game['icon'] ?></div>
            <h3 style="margin:0 0 .5rem;"><?= e($game['name']) ?></h3>
            <p class="text-muted text-small"><?= e($game['description']) ?></p>
            <p class="text-small text-muted"><?= $game['min_players'] ?>–<?= $game['max_players'] ?> joueurs</p>
            <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/create" style="margin-top:.75rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="game_key" value="<?= $key ?>">
                <button type="submit" class="btn btn-primary btn-sm">Créer une partie</button>
            </form>
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
        $activeSessions = array_filter($sessions, fn($s) => in_array($s['status'], ['waiting', 'in_progress']));
        ?>
        <?php if (empty($activeSessions)): ?>
            <p class="text-muted">Aucune partie active pour le moment. Créez-en une !</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Jeu</th>
                            <th>Créée par</th>
                            <th>Adversaire</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeSessions as $s): ?>
                        <tr>
                            <td>
                                <?= \App\Models\InteractiveGameSession::GAMES[$s['game_key']]['icon'] ?? '' ?>
                                <?= e(\App\Models\InteractiveGameSession::GAMES[$s['game_key']]['name'] ?? $s['game_key']) ?>
                            </td>
                            <td><?= e($s['player1_name']) ?></td>
                            <td><?= $s['player2_name'] ? e($s['player2_name']) : '<em class="text-muted">En attente…</em>' ?></td>
                            <td>
                                <?php if ($s['status'] === 'waiting'): ?>
                                    <span class="badge badge-warning">En attente</span>
                                <?php else: ?>
                                    <span class="badge badge-success">En cours</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                            <td>
                                <?php if ($s['status'] === 'waiting' && (int)$s['player1_id'] !== $currentUserId): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>/join" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-success btn-sm">Rejoindre</button>
                                    </form>
                                <?php else: ?>
                                    <a href="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
                                <?php endif; ?>
                                <?php if ((int)$s['created_by'] === $currentUserId && $s['status'] === 'waiting'): ?>
                                    <form method="POST" action="/spaces/<?= $currentSpace['id'] ?>/play/<?= $s['id'] ?>/cancel" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="Annuler cette partie ?">Annuler</button>
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
                        <th>Joueur 1</th>
                        <th>Joueur 2</th>
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
                        <td><?= e($s['player1_name']) ?></td>
                        <td><?= $s['player2_name'] ? e($s['player2_name']) : '—' ?></td>
                        <td>
                            <?php if ($s['status'] === 'cancelled'): ?>
                                <span class="badge badge-secondary">Annulée</span>
                            <?php elseif ($s['winner_id']): ?>
                                <span class="badge badge-success">
                                    🏆 <?= e($s['winner_id'] == $s['player1_id'] ? $s['player1_name'] : $s['player2_name']) ?>
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
