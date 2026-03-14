<div class="page-header">
    <h1>Profil de <?= e($user['username']) ?></h1>
    <?php if (is_authenticated() && current_user_id() === (int) $user['id']): ?>
        <a href="/profile/edit" class="btn btn-primary">Modifier</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="profile-header">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= e($user['avatar']) ?>" alt="Avatar" class="profile-avatar">
            <?php else: ?>
                <div class="profile-avatar navbar-avatar-placeholder" style="width:72px;height:72px;font-size:2rem;border-radius:50%;">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="profile-info">
                <h2><?= e($user['username']) ?></h2>
                <p class="profile-role">
                    <span class="badge badge-primary"><?= global_role_label($user['global_role']) ?></span>
                </p>
                <p class="text-muted text-small">Membre depuis le <?= format_date($user['created_at'], 'd/m/Y') ?></p>
            </div>
        </div>

        <?php if (!empty($user['bio'])): ?>
            <div class="mt-2">
                <h3>Biographie</h3>
                <p><?= nl2br(e($user['bio'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($winStats)): ?>
        <div class="card mt-3">
            <div class="card-header"><h3>🏆 Taux de victoire global</h3></div>
            <div class="card-body">
                <div class="stats-grid mb-2" style="grid-template-columns:repeat(3,1fr);">
                    <div class="stat-card">
                        <div class="stat-value"><?= $winStats['win_rate'] ?>%</div>
                        <div class="stat-label">Taux global</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $winStats['rounds_won'] ?></div>
                        <div class="stat-label">Manches gagnées</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $winStats['rounds_played'] ?></div>
                        <div class="stat-label">Manches jouées</div>
                    </div>
                </div>

                <h4 class="mt-2">Détail par espace</h4>
                                <?php if (!empty($user['show_win_rate_public'])): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Espace</th>
                                <th class="text-right">Manches jouées</th>
                                <th class="text-right">Manches gagnées</th>
                                <th class="text-right">Taux</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($winStats['breakdown'] as $row): ?>
                            <tr>
                                <td><?= e($row['space_name']) ?></td>
                                <td class="text-right"><?= $row['played'] ?></td>
                                <td class="text-right"><?= $row['won'] ?></td>
                                <td class="text-right">
                                    <?php if ($row['rate'] !== null): ?>
                                        <?= $row['rate'] ?>%
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted text-small mt-1">* Seules les manches terminées (<code>completed</code>) sont comptabilisées. En cas d'égalité au score, chaque joueur à égalité est compté comme gagnant.</p>
                            <?php else: ?>
                            <p class="text-muted text-small mt-1">Le détail par espace est masqué par cet utilisateur.</p>
                            <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <p class="text-muted mt-2">Aucune statistique disponible pour cet utilisateur.</p>
        <?php endif; ?>
    </div>
</div>

<p class="text-center mt-2">
    <a href="/leaderboard">← Retour au classement</a>
</p>
