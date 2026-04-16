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

<?php $canAdminOnly = $canAdminOnly ?? false; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:1.5rem;">

    <!-- Gestion du contenu -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 .75rem;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted,#6c757d);">Contenu</h3>
            <div class="d-flex gap-1 flex-wrap">
                <a href="/admin/users" class="btn btn-outline">👥 Utilisateurs</a>
                <a href="/admin/spaces" class="btn btn-outline">📦 Espaces</a>
                <a href="/admin/players/deleted" class="btn btn-outline">♻️ Restaurer des joueurs</a>
                <?php if ($canAdminOnly): ?>
                <a href="/admin/game-types" class="btn btn-outline">🃏 Types de jeux globaux</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modération -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 .75rem;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted,#6c757d);">Modération</h3>
            <div class="d-flex gap-1 flex-wrap">
                <a href="/admin/contact" class="btn btn-outline" style="position:relative;">
                    📬 Tickets de contact
                    <?php if (($openTicketsCount ?? 0) > 0): ?>
                    <span style="position:absolute;top:-6px;right:-6px;background:var(--danger,#e63946);color:#fff;font-size:.7rem;font-weight:700;min-width:18px;height:18px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;"><?= $openTicketsCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="/admin/bans/users" class="btn btn-outline">🚫 Bannissements comptes</a>
                <a href="/admin/bans/ips" class="btn btn-outline">🌐 Bannissements IP</a>
            </div>
        </div>
    </div>

    <?php if ($canAdminOnly): ?>
    <!-- Configuration -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 .75rem;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted,#6c757d);">Configuration</h3>
            <div class="d-flex gap-1 flex-wrap">
                <a href="/admin/password-policy" class="btn btn-outline">🔐 Mots de passe</a>
                <a href="/admin/fail2ban" class="btn btn-outline">🛡️ Fail2ban</a>
                <a href="/admin/leaderboard-criteria" class="btn btn-outline">🏆 Critères leaderboard</a>
            </div>
        </div>
    </div>

    <!-- Supervision -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 .75rem;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted,#6c757d);">Supervision</h3>
            <div class="d-flex gap-1 flex-wrap">
                <a href="/admin/logs" class="btn btn-outline">📋 Journal d'activité</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="card mb-3">
    <div class="card-body d-flex align-center gap-1">
        <span>🕐 <strong>CRON purge auto</strong> — prochaine exécution dans</span>
        <span id="cron-countdown" style="font-weight:bold;color:var(--primary,#4361ee);"></span>
    </div>
</div>
<script>
(function(){
    var el = document.getElementById('cron-countdown');
    function update(){
        var now = new Date();
        var remaining = 60 - now.getSeconds();
        if(remaining === 60) remaining = 0;
        el.textContent = remaining + 's';
    }
    update();
    setInterval(update, 1000);
})();
</script>

<div class="admin-two-col" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
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
