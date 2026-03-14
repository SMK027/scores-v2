<?php
$monthDate = DateTime::createFromFormat('Y-m', $filters['month'] ?? date('Y-m'));
if (!$monthDate) {
    $monthDate = new DateTime('first day of this month');
}

$monthStart = (clone $monthDate)->modify('first day of this month');
$monthEnd = (clone $monthDate)->modify('last day of this month');
$startWeekDay = (int) $monthStart->format('N'); // 1 (lundi) -> 7 (dimanche)
$daysInMonth = (int) $monthEnd->format('j');

$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');

$monthNames = [
    '01' => 'janvier',
    '02' => 'fevrier',
    '03' => 'mars',
    '04' => 'avril',
    '05' => 'mai',
    '06' => 'juin',
    '07' => 'juillet',
    '08' => 'aout',
    '09' => 'septembre',
    '10' => 'octobre',
    '11' => 'novembre',
    '12' => 'decembre',
];
$monthLabel = ($monthNames[$monthStart->format('m')] ?? $monthStart->format('m')) . ' ' . $monthStart->format('Y');

$queryBase = [
    'space_id' => $filters['space_id'] ?? '',
    'status' => $filters['status'] ?? '',
    'game_type_id' => $filters['game_type_id'] ?? '',
    'period' => $filters['period'] ?? '30d',
    'from' => $filters['from'] ?? '',
    'to' => $filters['to'] ?? '',
    'month' => $filters['month'] ?? date('Y-m'),
];
?>

<div class="page-header">
    <div>
        <h1>Calendrier des parties</h1>
        <p class="page-description">Historique de vos parties avec filtres par espace, période, statut et type de jeu.</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="/profile/calendar" class="calendar-filters">
            <div class="form-group">
                <label for="space_id" class="form-label">Espace</label>
                <select id="space_id" name="space_id" class="form-control">
                    <option value="">Tous les espaces</option>
                    <?php foreach ($spaces as $space): ?>
                        <option value="<?= (int) $space['id'] ?>" <?= ((int) ($filters['space_id'] ?? 0) === (int) $space['id']) ? 'selected' : '' ?>>
                            <?= e($space['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="period" class="form-label">Période</label>
                <select id="period" name="period" class="form-control">
                    <option value="7d" <?= ($filters['period'] ?? '') === '7d' ? 'selected' : '' ?>>7 derniers jours</option>
                    <option value="30d" <?= ($filters['period'] ?? '') === '30d' ? 'selected' : '' ?>>30 derniers jours</option>
                    <option value="90d" <?= ($filters['period'] ?? '') === '90d' ? 'selected' : '' ?>>90 derniers jours</option>
                    <option value="365d" <?= ($filters['period'] ?? '') === '365d' ? 'selected' : '' ?>>12 derniers mois</option>
                    <option value="custom" <?= ($filters['period'] ?? '') === 'custom' ? 'selected' : '' ?>>Plage personnalisée</option>
                    <option value="all" <?= ($filters['period'] ?? '') === 'all' ? 'selected' : '' ?>>Tout l'historique</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">Statut</label>
                <select id="status" name="status" class="form-control">
                    <option value="">Tous les statuts</option>
                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>En cours</option>
                    <option value="paused" <?= ($filters['status'] ?? '') === 'paused' ? 'selected' : '' ?>>En pause</option>
                    <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Terminée</option>
                </select>
            </div>

            <div class="form-group">
                <label for="game_type_id" class="form-label">Type de jeu</label>
                <select id="game_type_id" name="game_type_id" class="form-control">
                    <option value="">Tous les types</option>
                    <?php foreach ($gameTypes as $gt): ?>
                        <option value="<?= (int) $gt['id'] ?>" <?= ((int) ($filters['game_type_id'] ?? 0) === (int) $gt['id']) ? 'selected' : '' ?>>
                            <?= e($gt['name']) ?><?= empty($filters['space_id']) ? ' - ' . e($gt['space_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="from" class="form-label">Du</label>
                <input id="from" type="date" name="from" class="form-control" value="<?= e($filters['from'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="to" class="form-label">Au</label>
                <input id="to" type="date" name="to" class="form-control" value="<?= e($filters['to'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="month" class="form-label">Mois affiché</label>
                <input id="month" type="month" name="month" class="form-control" value="<?= e($filters['month'] ?? date('Y-m')) ?>">
            </div>

            <div class="calendar-filter-actions">
                <button type="submit" class="btn btn-primary">Appliquer</button>
                <a href="/profile/calendar" class="btn btn-outline">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header calendar-month-header">
        <h3>Vue calendrier - <?= e($monthLabel) ?></h3>
        <div class="btn-group">
            <a class="btn btn-sm btn-outline" href="/profile/calendar?<?= e(http_build_query(array_merge($queryBase, ['month' => $prevMonth, 'page' => 1]))) ?>">Mois precedent</a>
            <a class="btn btn-sm btn-outline" href="/profile/calendar?<?= e(http_build_query(array_merge($queryBase, ['month' => date('Y-m'), 'page' => 1]))) ?>">Aujourd'hui</a>
            <a class="btn btn-sm btn-outline" href="/profile/calendar?<?= e(http_build_query(array_merge($queryBase, ['month' => $nextMonth, 'page' => 1]))) ?>">Mois suivant</a>
        </div>
    </div>
    <div class="card-body">
        <div class="calendar-grid-head">
            <span>Lun</span>
            <span>Mar</span>
            <span>Mer</span>
            <span>Jeu</span>
            <span>Ven</span>
            <span>Sam</span>
            <span>Dim</span>
        </div>
        <div class="calendar-grid">
            <?php for ($i = 1; $i < $startWeekDay; $i++): ?>
                <div class="calendar-cell calendar-cell-empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                    $dayDate = sprintf('%s-%02d', $monthStart->format('Y-m'), $day);
                    $metrics = $calendarDays[$dayDate] ?? ['game_count' => 0, 'win_count' => 0];
                    $isToday = $dayDate === date('Y-m-d');
                ?>
                <div class="calendar-cell <?= $isToday ? 'calendar-cell-today' : '' ?> <?= ($metrics['game_count'] > 0) ? 'calendar-cell-active' : '' ?>">
                    <div class="calendar-day-number"><?= $day ?></div>
                    <div class="calendar-day-stats">
                        <span class="badge badge-info"><?= (int) $metrics['game_count'] ?> partie<?= ((int) $metrics['game_count'] > 1) ? 's' : '' ?></span>
                        <?php if ((int) $metrics['win_count'] > 0): ?>
                            <span class="badge badge-success"><?= (int) $metrics['win_count'] ?> victoire<?= ((int) $metrics['win_count'] > 1) ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Historique des parties</h3>
        <span class="badge badge-primary"><?= (int) $history['total'] ?> resultat<?= ((int) $history['total'] > 1) ? 's' : '' ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($history['data'])): ?>
            <p class="text-muted">Aucune partie ne correspond aux filtres actuels.</p>
        <?php else: ?>
            <div class="table-responsive calendar-history-table">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Espace</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Participants</th>
                            <th>Mon résultat</th>
                            <th>Gagnant(s)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history['data'] as $game): ?>
                            <tr>
                                <td>
                                    <div class="font-bold"><?= e(format_date($game['started_at'] ?: $game['created_at'], 'd/m/Y')) ?></div>
                                    <div class="text-small text-muted"><?= e(format_date($game['started_at'] ?: $game['created_at'], 'H:i')) ?></div>
                                </td>
                                <td><?= e($game['space_name']) ?></td>
                                <td><?= e($game['game_type_name']) ?></td>
                                <td><span class="badge <?= e(game_status_class($game['status'])) ?>"><?= e(game_status_label($game['status'])) ?></span></td>
                                <td><?= (int) $game['player_count'] ?></td>
                                <td>
                                    <?php if ($game['my_rank'] !== null): ?>
                                        <span class="font-bold">#<?= (int) $game['my_rank'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                    <span class="text-small text-muted">(<?= e($game['my_player_name']) ?>)</span>
                                    <?php if ((int) $game['my_is_winner'] === 1): ?>
                                        <span class="badge badge-success">Victoire</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($game['winner_names'] ?? '-') ?></td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline" href="/spaces/<?= (int) $game['space_id'] ?>/games/<?= (int) $game['id'] ?>">Voir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="calendar-history-cards">
                <?php foreach ($history['data'] as $game): ?>
                    <article class="calendar-history-card">
                        <div class="calendar-history-card-head">
                            <div>
                                <div class="font-bold"><?= e(format_date($game['started_at'] ?: $game['created_at'], 'd/m/Y H:i')) ?></div>
                                <div class="text-small text-muted"><?= e($game['space_name']) ?> - <?= e($game['game_type_name']) ?></div>
                            </div>
                            <span class="badge <?= e(game_status_class($game['status'])) ?>"><?= e(game_status_label($game['status'])) ?></span>
                        </div>
                        <div class="calendar-history-card-body">
                            <p><strong>Participants:</strong> <?= (int) $game['player_count'] ?></p>
                            <p>
                                <strong>Mon resultat:</strong>
                                <?php if ($game['my_rank'] !== null): ?>
                                    #<?= (int) $game['my_rank'] ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                                <?php if ((int) $game['my_is_winner'] === 1): ?>
                                    <span class="badge badge-success">Victoire</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Gagnant(s):</strong> <?= e($game['winner_names'] ?? '-') ?></p>
                        </div>
                        <a class="btn btn-sm btn-outline" href="/spaces/<?= (int) $game['space_id'] ?>/games/<?= (int) $game['id'] ?>">Ouvrir la partie</a>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (($history['lastPage'] ?? 1) > 1): ?>
                <div class="pagination">
                    <?php for ($p = 1; $p <= (int) $history['lastPage']; $p++): ?>
                        <?php $qs = http_build_query(array_merge($queryBase, ['page' => $p])); ?>
                        <?php if ($p === (int) $history['page']): ?>
                            <span class="active"><?= $p ?></span>
                        <?php else: ?>
                            <a href="/profile/calendar?<?= e($qs) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
