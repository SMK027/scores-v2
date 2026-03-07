<div class="page-header">
    <h1>📊 Statistiques</h1>
</div>

<!-- Vue d'ensemble -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-value"><?= $overview['total_games'] ?></div>
        <div class="stat-label">Parties</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $overview['completed_games'] ?></div>
        <div class="stat-label">Terminées</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $overview['active_games'] ?></div>
        <div class="stat-label">En cours</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $overview['total_players'] ?></div>
        <div class="stat-label">Joueurs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $overview['total_game_types'] ?></div>
        <div class="stat-label">Types de jeu</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $overview['total_rounds'] ?></div>
        <div class="stat-label">Manches</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

    <!-- Top joueurs -->
    <div class="card">
        <div class="card-header"><h3>🏆 Top joueurs</h3></div>
        <div class="card-body">
            <?php if (empty($topPlayers)): ?>
                <p class="text-muted text-center">Aucune donnée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Joueur</th>
                                <th class="text-right">Manches gagnées</th>
                                <th class="text-right">Manches jouées</th>
                                <th class="text-right">Taux</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $lastWon = null;
                                $displayRank = 0;
                                foreach ($topPlayers as $i => $player):
                                    if ($lastWon === null || $player['rounds_won'] !== $lastWon) {
                                        $displayRank = $i + 1;
                                    }
                                    $lastWon = $player['rounds_won'];
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($displayRank === 1): ?>🥇
                                        <?php elseif ($displayRank === 2): ?>🥈
                                        <?php elseif ($displayRank === 3): ?>🥉
                                        <?php else: ?><?= $displayRank ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-bold"><?= e($player['name']) ?></td>
                                    <td class="text-right"><?= $player['rounds_won'] ?></td>
                                    <td class="text-right"><?= $player['rounds_played'] ?></td>
                                    <td class="text-right"><?= $player['win_rate'] ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dernières parties -->
    <div class="card">
        <div class="card-header"><h3>🎮 Dernières parties</h3></div>
        <div class="card-body">
            <?php if (empty($recentGames)): ?>
                <p class="text-muted text-center">Aucune partie.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Jeu</th>
                                <th>Statut</th>
                                <th>Gagnant</th>
                                <th class="text-right">Joueurs</th>
                                <th class="text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentGames as $game): ?>
                                <tr>
                                    <td>
                                        <a href="/spaces/<?= $currentSpace['id'] ?>/games/<?= $game['id'] ?>">
                                            <?= e($game['game_type_name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($game['status'] === 'completed'): ?>
                                            <span class="badge badge-success">Terminée</span>
                                        <?php elseif ($game['status'] === 'paused'): ?>
                                            <span class="badge badge-warning">En pause</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">En cours</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-bold"><?= e($game['winner_name'] ?? '—') ?></td>
                                    <td class="text-right"><?= $game['player_count'] ?></td>
                                    <td class="text-right text-muted text-small"><?= time_ago($game['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Stats par type de jeu -->
<div class="card mt-3 mb-3">
    <div class="card-header"><h3>🎯 Par type de jeu</h3></div>
    <div class="card-body">
        <?php if (empty($statsByGameType)): ?>
            <p class="text-muted text-center">Aucune donnée.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type de jeu</th>
                            <th>Condition de victoire</th>
                            <th class="text-right">Parties</th>
                            <th class="text-right">Terminées</th>
                            <th class="text-right">Manches moy.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statsByGameType as $gt): ?>
                            <tr>
                                <td class="font-bold"><?= e($gt['name']) ?></td>
                                <td><span class="badge badge-info"><?= win_condition_label($gt['win_condition']) ?></span></td>
                                <td class="text-right"><?= $gt['total_games'] ?></td>
                                <td class="text-right"><?= $gt['completed_games'] ?></td>
                                <td class="text-right"><?= $gt['avg_rounds'] ?? '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Activité mensuelle -->
<?php if (!empty($monthlyActivity)): ?>
<div class="card mb-3">
    <div class="card-header"><h3>📅 Activité mensuelle</h3></div>
    <div class="card-body">
        <div class="activity-chart" style="display:flex;align-items:flex-end;gap:0.5rem;height:200px;padding:1rem 0;">
            <?php
                $maxCount = max(array_column($monthlyActivity, 'game_count'));
                $monthNames = [
                    '01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr',
                    '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aoû',
                    '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'
                ];
            ?>
            <?php foreach ($monthlyActivity as $month): ?>
                <?php
                    $height = $maxCount > 0 ? ($month['game_count'] / $maxCount * 100) : 0;
                    $parts = explode('-', $month['month']);
                    $label = ($monthNames[$parts[1]] ?? $parts[1]) . ' ' . substr($parts[0], 2);
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;">
                    <span class="text-small font-bold"><?= $month['game_count'] ?></span>
                    <div style="width:100%;max-width:60px;height:<?= max($height, 5) ?>%;background:var(--primary);border-radius:var(--radius) var(--radius) 0 0;min-height:4px;"></div>
                    <span class="text-small text-muted mt-1" style="font-size:0.7rem;"><?= $label ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
