#!/usr/bin/env php
<?php
/**
 * Script de migration automatique
 * 
 * Exécute les fichiers SQL de database/migrations/ dans l'ordre,
 * en ignorant ceux déjà appliqués (trackés dans la table `migrations`).
 * 
 * Tolère les erreurs d'idempotence (duplicate entry, column already exists)
 * pour supporter MySQL et MariaDB.
 * 
 * Usage : php database/migrate.php
 */

// Codes d'erreur MySQL/MariaDB tolérés (idempotence)
const IGNORABLE_ERRORS = [
    '1060', // Duplicate column name
    '1061', // Duplicate key name
    '1062', // Duplicate entry
    '1068', // Multiple primary key
    '1050', // Table already exists
];

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
sort($files);

$count = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (isset($appliedSet[$filename])) {
        echo "[migrate] SKIP  {$filename} (déjà appliquée)\n";
        continue;
    }

    echo "[migrate] APPLY {$filename}...\n";

    $sql = file_get_contents($file);

    // Découper en statements individuels
    $statements = splitStatements($sql);
    $hasError = false;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || $stmt === ';') continue;

        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? '0';
            if (in_array((string)$errorCode, IGNORABLE_ERRORS)) {
                echo "[migrate] WARN  {$filename} : {$e->errorInfo[2]} (ignoré)\n";
            } else {
                echo "[migrate] ERREUR sur {$filename} : {$e->getMessage()}\n";
                $hasError = true;
                break;
            }
        }
    }

    if ($hasError) {
        exit(1);
    }

    $pdo->prepare("INSERT INTO migrations (filename) VALUES (:f)")->execute(['f' => $filename]);
    echo "[migrate] OK    {$filename}\n";
    $count++;
}

if ($count === 0) {
    echo "[migrate] Toutes les migrations sont à jour.\n";
} else {
    echo "[migrate] {$count} migration(s) appliquée(s).\n";
}

/**
 * Découpe un fichier SQL en statements individuels.
 * Gère les chaînes de caractères quotées, les commentaires et les blocs DELIMITER.
 */
function splitStatements(string $sql): array
{
    // Normaliser les blocs DELIMITER // ... // pour compatibilité PDO
    $sql = preprocessDelimiters($sql);

    $statements = [];
    $current = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inLineComment = false;
    $inBlockComment = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        // Gestion des commentaires de bloc
        if (!$inSingleQuote && !$inDoubleQuote && !$inLineComment) {
            if ($inBlockComment) {
                $current .= $char;
                if ($char === '*' && $next === '/') {
                    $current .= '/';
                    $i++;
                    $inBlockComment = false;
                }
                continue;
            }
            if ($char === '/' && $next === '*') {
                $current .= '/*';
                $i++;
                $inBlockComment = true;
                continue;
            }
        }

        // Gestion des commentaires de ligne
        if (!$inSingleQuote && !$inDoubleQuote && !$inBlockComment) {
            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $current .= $char;
                }
                continue;
            }
            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                continue;
            }
        }

        // Gestion des quotes
        if (!$inBlockComment && !$inLineComment) {
            if ($char === "'" && !$inDoubleQuote) {
                if ($inSingleQuote && $next === "'") {
                    $current .= "''";
                    $i++;
                    continue;
                }
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
            }
        }

        // Séparateur de statement
        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBlockComment && !$inLineComment) {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                // Restaurer les ';' protégés dans les blocs DELIMITER (ex: corps de procédure)
                $statements[] = str_replace("\x01", ';', $trimmed);
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $trimmed = trim($current);
    if ($trimmed !== '') {
        $statements[] = str_replace("\x01", ';', $trimmed);
    }

    return $statements;
}

/**
 * Prétraite les blocs DELIMITER pour compatibilité PDO.
 *
 * Les instructions DELIMITER // ... // sont converties en statements ;-terminés.
 * Les ';' internes au bloc sont temporairement remplacés par \x01 pour éviter
 * un découpage prématuré par splitStatements().
 */
function preprocessDelimiters(string $sql): string
{
    $lines  = explode("\n", $sql);
    $output = [];
    $customDelim = null; // null = mode normal ';'
    $buffer = [];

    foreach ($lines as $line) {
        $rtrimmed = rtrim($line);

        // Détecter une directive DELIMITER
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $rtrimmed, $m)) {
            $newDelim = $m[1];
            if ($newDelim === ';') {
                // Retour au mode normal : vider le buffer si nécessaire
                if (!empty($buffer)) {
                    $block = implode("\n", $buffer);
                    $block = str_replace(';', "\x01", $block);
                    $output[] = $block . ';';
                    $buffer = [];
                }
                $customDelim = null;
            } else {
                $customDelim = $newDelim;
            }
            continue; // Ignorer la ligne DELIMITER elle-même
        }

        if ($customDelim === null) {
            // Mode normal : passer la ligne telle quelle
            $output[] = $line;
        } else {
            // Mode délimiteur custom : accumuler jusqu'au délimiteur
            $escapedDelim = preg_quote($customDelim, '/');
            if (preg_match('/' . $escapedDelim . '\s*$/', $rtrimmed)) {
                // Ligne terminatrice : retirer le délimiteur et vider le buffer
                $cleanLine = rtrim(preg_replace('/' . $escapedDelim . '\s*$/', '', $rtrimmed));
                if ($cleanLine !== '') {
                    $buffer[] = $cleanLine;
                }
                $block = implode("\n", $buffer);
                $block = str_replace(';', "\x01", $block);
                $output[] = $block . ';';
                $buffer = [];
            } else {
                $buffer[] = $line;
            }
        }
    }

    // Buffer résiduel (fichier mal terminé)
    if (!empty($buffer)) {
        $output[] = implode("\n", $buffer);
    }

    return implode("\n", $output);
}
