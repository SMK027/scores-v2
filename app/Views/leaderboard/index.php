<div class="page-header">
    <h1>🏆 Leaderboard global</h1>
</div>

<div class="card mb-2">
    <div class="card-body" style="padding:0.8rem 1rem;">
        <span class="text-muted">
            Note: pour figurer dans ce classement, un joueur doit avoir au moins
            <strong><?= (int) ($criteria['min_rounds_played'] ?? 5) ?></strong> manches jouees
            et une activite sur
            <strong><?= (int) ($criteria['min_spaces_played'] ?? 2) ?></strong> espace(s) distinct(s).
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
                                <?php endif; ?>
                                <?= e($row['username']) ?>
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
