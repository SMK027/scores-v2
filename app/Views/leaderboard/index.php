<div class="page-header">
    <h1>🏆 Leaderboard global</h1>
</div>

<?php
    $period     = $period ?? 'all';
    $customFrom = $customFrom ?? '';
    $customTo   = $customTo   ?? '';
    $presets = [
        'all' => 'Tout',
        '7d'  => '7 jours',
        '30d' => '30 jours',
        '3m'  => '3 mois',
        '6m'  => '6 mois',
        '1y'  => '1 an',
        'custom' => 'Personnalisé',
    ];
?>
<div class="card mb-2">
    <div class="card-body" style="padding:0.8rem 1rem;">
        <form method="GET" action="/leaderboard" id="period-form">
            <div class="d-flex align-center gap-1 flex-wrap">
                <span class="text-muted text-small" style="margin-right:0.25rem;">Période :</span>
                <?php foreach ($presets as $key => $label): ?>
                    <?php if ($key === 'custom'): ?>
                        <button type="button"
                                class="btn btn-sm <?= $period === 'custom' ? 'btn-primary' : 'btn-outline' ?>"
                                onclick="document.getElementById('custom-range').style.display='flex';
                                         document.getElementById('period-input').value='custom';">
                            <?= $label ?>
                        </button>
                    <?php else: ?>
                        <a href="/leaderboard?period=<?= $key ?>"
                           class="btn btn-sm <?= $period === $key ? 'btn-primary' : 'btn-outline' ?>">
                            <?= $label ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="period-input" name="period" value="custom">
            <div id="custom-range"
                 style="display:<?= $period === 'custom' ? 'flex' : 'none' ?>;align-items:center;gap:0.5rem;margin-top:0.75rem;flex-wrap:wrap;">
                <label class="text-small text-muted" style="white-space:nowrap;">Du</label>
                <input type="date" name="from" id="date-from" class="form-control"
                       style="width:auto;" value="<?= e($customFrom) ?>">
                <label class="text-small text-muted" style="white-space:nowrap;">au</label>
                <input type="date" name="to" id="date-to" class="form-control"
                       style="width:auto;" value="<?= e($customTo) ?>">
                <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-2">
    <div class="card-body" style="padding:0.6rem 1rem;">
        <span class="text-muted text-small">
            Pour figurer dans ce classement, un joueur doit avoir au moins
            <strong><?= (int) ($criteria['min_rounds_played'] ?? 5) ?></strong> manches jouées
            et une activité sur
            <strong><?= (int) ($criteria['min_spaces_played'] ?? 2) ?></strong> espace(s) distinct(s)
            <?php if ($period !== 'all'): ?>
                sur la période sélectionnée.
            <?php endif; ?>
        </span>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($leaderboard)): ?>
            <p class="text-muted text-center">Aucune donnée disponible pour le moment.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Joueur</th>
                            <th class="text-right">Taux global</th>
                            <th class="text-right">Manches gagnées</th>
                            <th class="text-right">Manches jouées</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $lastRate = null;
                            $displayRank = 0;
                            foreach ($leaderboard as $i => $row):
                                if ($lastRate === null || (float) $row['win_rate'] !== (float) $lastRate) {
                                    $displayRank = $i + 1;
                                }
                                $lastRate = (float) $row['win_rate'];
                        ?>
                        <tr>
                            <td>
                                <?php if ($displayRank === 1): ?>🥇
                                <?php elseif ($displayRank === 2): ?>🥈
                                <?php elseif ($displayRank === 3): ?>🥉
                                <?php else: ?><?= $displayRank ?>
                                <?php endif; ?>
                            </td>
                            <td class="font-bold">
                                <?php if (!empty($row['avatar'])): ?>
                                    <img src="<?= e($row['avatar']) ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:0.4rem;">
                                <?php else: ?>
                                    <span class="navbar-avatar navbar-avatar-placeholder" style="width:24px;height:24px;font-size:0.75rem;vertical-align:middle;margin-right:0.4rem;"><?= strtoupper(substr($row['username'], 0, 1)) ?></span>
                                <?php endif; ?>
                                <a href="/profile/<?= urlencode($row['username']) ?>"><?= e($row['username']) ?></a>
                            </td>
                            <td class="text-right"><?= number_format((float) $row['win_rate'], 2, '.', '') ?>%</td>
                            <td class="text-right"><?= (int) $row['rounds_won'] ?></td>
                            <td class="text-right"><?= (int) $row['rounds_played'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
