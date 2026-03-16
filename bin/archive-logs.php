#!/usr/bin/env php
<?php

/**
 * Script CLI d'archivage automatique des logs d'activité.
 *
 * Appelle la procédure stockée archive_activity_logs() qui :
 *   - Chiffre et archive les logs de plus de 3 mois
 *   - Purge les archives de plus de 6 mois
 *
 * Usage :
 *   php bin/archive-logs.php
 *
 * Via Docker :
 *   docker exec scores_app php /var/www/html/bin/archive-logs.php >> /var/log/scores-archive.log 2>&1
 *
 * Cron recommandé (quotidien à minuit) :
 *   0 0 * * * /usr/local/bin/php /var/www/html/bin/archive-logs.php >> /var/log/scores-archive.log 2>&1
 */

declare(strict_types=1);

// Empêcher l'exécution via le navigateur
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}

date_default_timezone_set('Europe/Paris');

$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'scores_db';
$dbUser = getenv('DB_USER') ?: 'scores_user';
$dbPass = getenv('DB_PASS') ?: 'scores_password';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec('CALL archive_activity_logs()');

    echo '[' . date('Y-m-d H:i:s') . '] Archivage des logs exécuté avec succès.' . PHP_EOL;
} catch (PDOException $e) {
    echo '[' . date('Y-m-d H:i:s') . '] ERREUR archivage logs : ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
