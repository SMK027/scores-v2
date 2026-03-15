<div class="stats-page">
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

<!-- Joueur le plus actif -->
<div class="card mb-3">
    <div class="card-header"><h3>🔥 Joueur le plus actif</h3></div>
    <div class="card-body">
        <?php if (empty($mostActivePlayer)): ?>
            <p class="text-muted text-center">Aucune activité joueur pour le moment.</p>
        <?php else: ?>
            <div class="most-active-panel">
                <div class="most-active-main">
                    <p class="most-active-name">🥇 <?= e($mostActivePlayer['name']) ?></p>
                    <p class="most-active-subtitle">
                        Classement base sur les manches jouees dans cet espace.
                        Taux = manches gagnees / manches jouees.
                    </p>
                </div>
                <div class="most-active-metrics">
                    <span class="badge badge-primary">Manches jouees: <?= (int) $mostActivePlayer['rounds_played'] ?></span>
                    <span class="badge badge-success">Manches gagnees: <?= (int) $mostActivePlayer['rounds_won'] ?></span>
                    <span class="badge badge-info">Taux: <?= number_format((float) $mostActivePlayer['win_rate'], 1) ?>%</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="stats-duo-grid">

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

<!-- Evolution du nombre de parties -->
<?php if (!empty($monthlyActivity)): ?>
<?php
    $activityLabels = array_map(static fn($m) => $m['label'] ?? $m['month'], $monthlyActivity);
    $activityValues = array_map(static fn($m) => (int) ($m['game_count'] ?? 0), $monthlyActivity);
?>
<div class="card mb-3">
    <div class="card-header"><h3>📈 Evolution des parties jouees</h3></div>
    <div class="card-body">
        <div class="stats-evolution-chart-wrap">
            <svg id="gamesEvolutionChart" viewBox="0 0 800 260" preserveAspectRatio="none" style="width:100%;height:100%;display:block;"></svg>
        </div>
        <div class="text-muted text-small mt-1 stats-chart-meta">
            <span>Periode: <?= e($activityLabels[0] ?? '-') ?> -> <?= e($activityLabels[count($activityLabels) - 1] ?? '-') ?></span>
            <span>Total: <?= array_sum($activityValues) ?> partie(s)</span>
        </div>
    </div>
</div>
<script>
(function(){
    var svg = document.getElementById('gamesEvolutionChart');
    if (!svg) return;

    var labels = <?= json_encode($activityLabels, JSON_UNESCAPED_UNICODE) ?>;
    var values = <?= json_encode($activityValues, JSON_UNESCAPED_UNICODE) ?>;
    if (!values.length) return;

    var w = 800, h = 260;
    var pad = { top: 18, right: 18, bottom: 34, left: 36 };
    var innerW = w - pad.left - pad.right;
    var innerH = h - pad.top - pad.bottom;
    var max = Math.max.apply(null, values.concat([1]));

    function x(i) {
        if (values.length === 1) return pad.left + innerW / 2;
        return pad.left + (i * innerW / (values.length - 1));
    }
    function y(v) {
        return pad.top + innerH - (v / max) * innerH;
    }

    var points = values.map(function(v, i) { return [x(i), y(v)]; });
    var lineD = 'M ' + points.map(function(p){ return p[0].toFixed(2) + ' ' + p[1].toFixed(2); }).join(' L ');
    var areaD = lineD + ' L ' + x(values.length - 1).toFixed(2) + ' ' + (pad.top + innerH).toFixed(2)
        + ' L ' + x(0).toFixed(2) + ' ' + (pad.top + innerH).toFixed(2) + ' Z';

    var axisColor = '#cbd5e1';
    var lineColor = '#2563eb';
    var fillColor = 'rgba(37,99,235,0.15)';

    var html = '';
    html += '<line x1="' + pad.left + '" y1="' + (pad.top + innerH) + '" x2="' + (pad.left + innerW) + '" y2="' + (pad.top + innerH) + '" stroke="' + axisColor + '" />';
    html += '<line x1="' + pad.left + '" y1="' + pad.top + '" x2="' + pad.left + '" y2="' + (pad.top + innerH) + '" stroke="' + axisColor + '" />';
    html += '<path d="' + areaD + '" fill="' + fillColor + '" />';
    html += '<path d="' + lineD + '" fill="none" stroke="' + lineColor + '" stroke-width="3" stroke-linecap="round" />';

    points.forEach(function(p, i){
        var title = (labels[i] || ('M' + (i + 1))) + ': ' + values[i] + ' partie(s)';
        html += '<circle cx="' + p[0].toFixed(2) + '" cy="' + p[1].toFixed(2) + '" r="4" fill="' + lineColor + '"><title>' + title + '</title></circle>';
    });

    var ticks = Math.min(values.length, 6);
    for (var t = 0; t < ticks; t++) {
        var idx = Math.round(t * (values.length - 1) / Math.max(ticks - 1, 1));
        var lx = x(idx);
        var lbl = labels[idx] || '';
        html += '<text x="' + lx.toFixed(2) + '" y="' + (h - 10) + '" text-anchor="middle" font-size="11" fill="#64748b">' + lbl + '</text>';
    }

    html += '<text x="' + (pad.left - 8) + '" y="' + (y(max) + 4).toFixed(2) + '" text-anchor="end" font-size="11" fill="#64748b">' + max + '</text>';
    html += '<text x="' + (pad.left - 8) + '" y="' + (y(0) + 4).toFixed(2) + '" text-anchor="end" font-size="11" fill="#64748b">0</text>';

    svg.innerHTML = html;
})();
</script>
<?php endif; ?>
</div>
