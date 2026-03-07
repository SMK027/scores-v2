<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Session') ?> - Compétition Scores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
</head>
<body>
    <!-- Barre de navigation minimale -->
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="/competition/login" class="navbar-brand">🏆 Compétition</a>
            <div class="navbar-menu">
                <?php $sessionData = \App\Core\Session::get('competition_session'); ?>
                <?php if ($sessionData): ?>
                    <span class="text-muted text-small" style="margin-right:0.5rem;">
                        <?= e($sessionData['competition_name']) ?> — Session #<?= (int) $sessionData['session_number'] ?>
                        (<?= e($sessionData['referee_name']) ?>)
                    </span>
                    <a href="/competition/dashboard" class="navbar-link">Tableau de bord</a>
                    <a href="/competition/logout" class="btn btn-sm btn-outline">Déconnexion</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            <?php include __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Scores - Compétition</p>
        </div>
    </footer>

    <script src="/js/app.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/app.js') ?>"></script>
</body>
</html>
