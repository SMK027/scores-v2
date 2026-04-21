<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Accueil') ?> - Scores</title>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
</head>
<body>
    <?php
        $__activeUserRestrictionLabels = [];
        $__activeUserRestrictionReason = null;
        if (is_authenticated()) {
            $__userModel = new \App\Models\User();
            $__uid = (int) current_user_id();
            $__restrictionMap = $__userModel->getRestrictions($__uid);
            foreach ($__restrictionMap as $__k => $__v) {
                if (!empty($__v) && !empty(\App\Models\User::RESTRICTION_KEYS[$__k])) {
                    $__activeUserRestrictionLabels[] = \App\Models\User::RESTRICTION_KEYS[$__k];
                }
            }
            if (!empty($__activeUserRestrictionLabels)) {
                $__u = $__userModel->find($__uid);
                $__activeUserRestrictionReason = $__u['restriction_reason'] ?? null;
            }
        }
    ?>
    <!-- Barre de navigation -->
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="/" class="navbar-brand">🎲 Scores</a>
            <button class="navbar-toggle" id="navbarToggle" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <div class="navbar-menu" id="navbarMenu">
                <?php if (is_authenticated()): ?>
                    <?php
                        $__pendingInvCount = (new \App\Models\SpaceInvitation())->countPendingForUser(current_user_id());
                    ?>
                    <a href="/spaces" class="navbar-link">Mes espaces<?php if ($__pendingInvCount > 0): ?> <span class="badge badge-danger" style="font-size:0.7em;vertical-align:middle;"><?= $__pendingInvCount ?></span><?php endif; ?></a>
                    <a href="/leaderboard" class="navbar-link">Leaderboard</a>
                    <a href="/profile/calendar" class="navbar-link">Mon calendrier</a>
                    <?php if (\App\Core\Middleware::isGlobalStaff()): ?>
                        <a href="/admin" class="navbar-link">Administration</a>
                    <?php endif; ?>
                    <!-- Cloche de notifications -->
                    <div class="notif-bell" id="notifBell">
                        <button class="notif-bell-btn" id="notifBellBtn"
                                aria-label="Notifications" aria-expanded="false"
                                title="Notifications">
                            <i class="bi bi-bell"></i>
                            <span class="notif-badge" id="notifBadge" hidden>0</span>
                        </button>
                        <div class="notif-dropdown" id="notifDropdown" hidden>
                            <div class="notif-dropdown-header">
                                <strong>Notifications</strong>
                                <button type="button" id="notifMarkAll" class="btn btn-sm btn-outline"
                                        style="font-size:.75rem;padding:.2rem .6rem;">Tout lire</button>
                            </div>
                            <div class="notif-list" id="notifList">
                                <p class="notif-empty">Chargement…</p>
                            </div>
                        </div>
                    </div>
                    <div class="navbar-user">
                        <a href="/profile" class="navbar-link navbar-profile-link">
                            <?php if (current_avatar()): ?>
                                <img src="<?= e(current_avatar()) ?>" alt="" class="navbar-avatar">
                            <?php else: ?>
                                <span class="navbar-avatar navbar-avatar-placeholder"><?= strtoupper(substr(current_username(), 0, 1)) ?></span>
                            <?php endif; ?>
                            <?= e(current_username()) ?>
                        </a>
                        <a href="/logout" class="btn btn-sm btn-outline">Déconnexion</a>
                    </div>
                <?php else: ?>
                    <a href="/login" class="btn btn-sm btn-outline">Connexion</a>
                    <a href="/register" class="btn btn-sm btn-primary">Inscription</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php if (\App\Core\Session::get('impersonator_id')): ?>
    <div style="background:var(--warning,#f59e0b);color:#000;text-align:center;padding:.5rem 1rem;font-weight:600;font-size:.95em;position:sticky;top:0;z-index:1100;">
        🎭 Vous contrôlez le compte de <strong><?= e(\App\Core\Session::get('username') ?? '') ?></strong>
        <form method="POST" action="/admin/stop-impersonate" style="display:inline;margin-left:.75rem;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm" style="background:#000;color:#fff;padding:.2rem .75rem;font-size:.85em;">✕ Reprendre mon compte</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (isset($currentSpace)): ?>
    <!-- Layout avec sidebar pour les espaces -->
    <div class="layout-with-sidebar">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3><?= e($currentSpace['name']) ?></h3>
            </div>
            <nav class="sidebar-nav">
                <a href="/spaces/<?= $currentSpace['id'] ?>" class="sidebar-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
                    📊 Tableau de bord
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/games" class="sidebar-link <?= ($activeMenu ?? '') === 'games' ? 'active' : '' ?>">
                    🎮 Parties
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/players" class="sidebar-link <?= ($activeMenu ?? '') === 'players' ? 'active' : '' ?>">
                    👥 Joueurs
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/game-types" class="sidebar-link <?= ($activeMenu ?? '') === 'game-types' ? 'active' : '' ?>">
                    🃏 Types de jeux
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/play" class="sidebar-link <?= ($activeMenu ?? '') === 'play' ? 'active' : '' ?>">
                    🕹️ Jouer en ligne
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/stats" class="sidebar-link <?= ($activeMenu ?? '') === 'stats' ? 'active' : '' ?>">
                    📈 Statistiques
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/search" class="sidebar-link <?= ($activeMenu ?? '') === 'search' ? 'active' : '' ?>">
                    🔍 Rechercher
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/competitions" class="sidebar-link <?= ($activeMenu ?? '') === 'competitions' ? 'active' : '' ?>">
                    🏆 Compétitions
                </a>
                <?php if (isset($spaceRole) && in_array($spaceRole, ['admin', 'manager'])): ?>
                <a href="/spaces/<?= $currentSpace['id'] ?>/contact" class="sidebar-link <?= ($activeMenu ?? '') === 'contact' ? 'active' : '' ?>">
                    📬 Contact
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/members" class="sidebar-link <?= ($activeMenu ?? '') === 'members' ? 'active' : '' ?>">
                    ⚙️ Membres
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/edit" class="sidebar-link <?= ($activeMenu ?? '') === 'settings' ? 'active' : '' ?>">
                    🔧 Paramètres
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="/spaces" class="sidebar-link">← Retour aux espaces</a>
            </div>
        </aside>
        <main class="main-content with-sidebar">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?php if (!empty($__activeUserRestrictionLabels)): ?>
            <div class="alert alert-warning alert-persistent" style="border-left:4px solid var(--warning,#f59e0b);margin-bottom:1rem;">
                <strong>⚠️ Des restrictions sont actives sur votre compte :</strong>
                <ul style="margin:0.5rem 0 0 1.25rem;">
                    <?php foreach ($__activeUserRestrictionLabels as $__label): ?>
                        <li><?= e($__label) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($__activeUserRestrictionReason)): ?>
                    <span class="text-muted">Motif : <?= e((string) $__activeUserRestrictionReason) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php
                $__spaceRestrictions = (new \App\Models\Space())->getRestrictions($currentSpace['id']);
                if (!empty($__spaceRestrictions)):
            ?>
            <div class="alert alert-warning alert-persistent" style="border-left:4px solid var(--danger,#dc3545);margin-bottom:1rem;">
                <strong>⚠️ Certaines fonctionnalités de cet espace sont temporairement restreintes par l'administration du site.</strong>
                <?php if (!empty($currentSpace['restriction_reason'])): ?>
                    <br><span class="text-muted">Motif : <?= e($currentSpace['restriction_reason']) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($currentSpace['scheduled_deletion_at'])):
                $__delParis = new \DateTimeZone('Europe/Paris');
                $__delDt = new \DateTimeImmutable($currentSpace['scheduled_deletion_at'], $__delParis);
                $__delIso = $__delDt->format('c');
            ?>
            <div class="alert alert-danger alert-persistent" style="border-left:4px solid var(--danger,#dc3545);margin-bottom:1rem;">
                <div>
                    <strong>💣 Cet espace est programmé pour suppression le <?= $__delDt->format('d/m/Y à H:i') ?> (heure de Paris).</strong>
                    <?php if (!empty($currentSpace['deletion_reason'])): ?>
                        <br><span class="text-muted">Motif : <?= e($currentSpace['deletion_reason']) ?></span>
                    <?php endif; ?>
                    <br><span id="deletion-countdown" style="font-weight:bold;font-size:1.1em;color:var(--danger,#dc3545);"></span>
                </div>
            </div>
            <script>
            (function(){
                var target = new Date(<?= json_encode($__delIso) ?>).getTime();
                var el = document.getElementById('deletion-countdown');
                function update(){
                    var diff = target - Date.now();
                    if(diff <= 0){ el.textContent = '⏰ Délai expiré — suppression imminente'; return; }
                    var d = Math.floor(diff/86400000);
                    var h = Math.floor((diff%86400000)/3600000);
                    var m = Math.floor((diff%3600000)/60000);
                    var s = Math.floor((diff%60000)/1000);
                    var parts = [];
                    if(d > 0) parts.push(d + 'j');
                    parts.push(h + 'h');
                    parts.push(m + 'min');
                    parts.push(s + 's');
                    el.textContent = '⏳ Suppression dans ' + parts.join(' ');
                }
                update();
                setInterval(update, 1000);
            })();
            </script>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
    <?php else: ?>
    <!-- Layout standard sans sidebar -->
    <main class="main-content">
        <div class="container">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?php if (!empty($__activeUserRestrictionLabels)): ?>
            <div class="alert alert-warning alert-persistent" style="border-left:4px solid var(--warning,#f59e0b);margin-bottom:1rem;">
                <strong>⚠️ Des restrictions sont actives sur votre compte :</strong>
                <ul style="margin:0.5rem 0 0 1.25rem;">
                    <?php foreach ($__activeUserRestrictionLabels as $__label): ?>
                        <li><?= e($__label) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($__activeUserRestrictionReason)): ?>
                    <span class="text-muted">Motif : <?= e((string) $__activeUserRestrictionReason) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?= $content ?>
        </div>
    </main>
    <?php endif; ?>

    <!-- Pied de page -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Scores - Application de gestion de parties de jeux</p>
            <p style="margin-top: 0.4rem;">
                <a href="/legal" style="color: var(--gray); text-decoration: underline;">Conditions Générales d'Utilisation</a>
            </p>
        </div>
    </footer>

    <script src="/js/app.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/app.js') ?>"></script>
</body>
</html>
