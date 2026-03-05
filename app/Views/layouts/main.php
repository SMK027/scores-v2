<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Accueil') ?> - Scores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
</head>
<body>
    <!-- Barre de navigation -->
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="/" class="navbar-brand">🎲 Scores</a>
            <button class="navbar-toggle" id="navbarToggle" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <div class="navbar-menu" id="navbarMenu">
                <?php if (is_authenticated()): ?>
                    <a href="/spaces" class="navbar-link">Mes espaces</a>
                    <?php if (\App\Core\Middleware::isGlobalStaff()): ?>
                        <a href="/admin" class="navbar-link">Administration</a>
                    <?php endif; ?>
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
                <a href="/spaces/<?= $currentSpace['id'] ?>/stats" class="sidebar-link <?= ($activeMenu ?? '') === 'stats' ? 'active' : '' ?>">
                    📈 Statistiques
                </a>
                <a href="/spaces/<?= $currentSpace['id'] ?>/search" class="sidebar-link <?= ($activeMenu ?? '') === 'search' ? 'active' : '' ?>">
                    🔍 Rechercher
                </a>
                <?php if (isset($spaceRole) && in_array($spaceRole, ['admin', 'manager'])): ?>
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
            <?= $content ?>
        </main>
    </div>
    <?php else: ?>
    <!-- Layout standard sans sidebar -->
    <main class="main-content">
        <div class="container">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </div>
    </main>
    <?php endif; ?>

    <!-- Pied de page -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Scores - Application de gestion de parties de jeux</p>
        </div>
    </footer>

    <script src="/js/app.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/app.js') ?>"></script>
</body>
</html>
