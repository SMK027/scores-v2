#!/usr/bin/env php
<?php
/**
 * Script de migration automatique
 * 
 * Exécute les fichiers SQL de database/migrations/ dans l'ordre,
 * en ignorant ceux déjà appliqués (trackés dans la table `migrations`).
 * 
 * Usage : php database/migrate.php
 */

$maxRetries = 30;
$retryDelay = 2; // secondes

$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'scores_db';
$dbUser = getenv('DB_USER') ?: 'scores_user';
$dbPass = getenv('DB_PASS') ?: 'scores_password';

echo "[migrate] Attente de la base de données...\n";

$pdo = null;
for ($i = 1; $i <= $maxRetries; $i++) {
    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]
        );
        echo "[migrate] Connexion établie.\n";
        break;
    } catch (PDOException $e) {
        echo "[migrate] Tentative {$i}/{$maxRetries} — {$e->getMessage()}\n";
        if ($i === $maxRetries) {
            echo "[migrate] ERREUR : impossible de se connecter à la base de données.\n";
            exit(1);
        }
        sleep($retryDelay);
    }
}

// Créer la table de suivi des migrations
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `filename` VARCHAR(255) NOT NULL UNIQUE,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Récupérer les migrations déjà appliquées
$applied = $pdo->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_flip($applied);

// Scanner les fichiers de migration
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files); // Tri alphabétique = ordre numérique (001_, 002_, ...)

$count = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (isset($appliedSet[$filename])) {
        echo "[migrate] SKIP  {$filename} (déjà appliquée)\n";
        continue;
    }

    echo "[migrate] APPLY {$filename}...\n";

    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);
        $pdo->prepare("INSERT INTO migrations (filename) VALUES (:f)")->execute(['f' => $filename]);
        echo "[migrate] OK    {$filename}\n";
        $count++;
    } catch (PDOException $e) {
        echo "[migrate] ERREUR sur {$filename} : {$e->getMessage()}\n";
        exit(1);
    }
}

if ($count === 0) {
    echo "[migrate] Toutes les migrations sont à jour.\n";
} else {
    echo "[migrate] {$count} migration(s) appliquée(s).\n";
}
