<div class="page-header">
    <h1>⚙️ Administration</h1>
</div>

<!-- Stats globales -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['total_users'] ?></div>
        <div class="stat-label">Utilisateurs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['total_spaces'] ?></div>
        <div class="stat-label">Espaces</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['total_games'] ?></div>
        <div class="stat-label">Parties</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $stats['total_players'] ?></div>
        <div class="stat-label">Joueurs</div>
    </div>
</div>

<div class="d-flex gap-1 mb-3 flex-wrap">
    <a href="/admin/users" class="btn btn-outline">👥 Gérer les utilisateurs</a>
    <a href="/admin/spaces" class="btn btn-outline">📦 Gérer les espaces</a>
    <a href="/admin/password-policy" class="btn btn-outline">🔐 Politique de mot de passe</a>
    <a href="/admin/fail2ban" class="btn btn-outline">🛡️ Fail2ban</a>
    <a href="/admin/bans/users" class="btn btn-outline">🚫 Bannissements comptes</a>
    <a href="/admin/bans/ips" class="btn btn-outline">🌐 Bannissements IP</a>
    <a href="/admin/logs" class="btn btn-outline">📋 Journal d'activité</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
    <!-- Derniers inscrits -->
    <div class="card">
        <div class="card-header"><h3>👥 Derniers inscrits</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Rôle</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td>
                                    <strong><?= e($user['username']) ?></strong>
                                    <div class="text-muted text-small"><?= e($user['email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $user['global_role'] === 'superadmin' ? 'badge-danger' : ($user['global_role'] === 'admin' ? 'badge-warning' : 'badge-info') ?>">
                                        <?= global_role_label($user['global_role']) ?>
                                    </span>
                                </td>
                                <td class="text-muted text-small"><?= time_ago($user['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Derniers espaces -->
    <div class="card">
        <div class="card-header"><h3>📦 Derniers espaces</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Espace</th>
                            <th>Créateur</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSpaces as $space): ?>
                            <tr>
                                <td>
                                    <a href="/spaces/<?= $space['id'] ?>"><strong><?= e($space['name']) ?></strong></a>
                                </td>
                                <td class="text-muted"><?= e($space['owner_name']) ?></td>
                                <td class="text-muted text-small"><?= time_ago($space['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
