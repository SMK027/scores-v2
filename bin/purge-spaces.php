#!/usr/bin/env php
<?php

/**
 * Script CLI de purge automatique des espaces programmés pour suppression.
 *
 * Usage :
 *   php bin/purge-spaces.php
 *
 * Via Docker :
 *   docker exec scores_app php /var/www/html/bin/purge-spaces.php
 *
 * Cron recommandé (toutes les minutes) :
 *   * * * * * docker exec scores_app php /var/www/html/bin/purge-spaces.php >> /var/log/scores-purge.log 2>&1
 */

declare(strict_types=1);

// Empêcher l'exécution via le navigateur
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Models\Space;
use App\Models\ActivityLog;

$spaceModel = new Space();
$due = $spaceModel->findDueForDeletion();

if (empty($due)) {
    echo "[" . date('Y-m-d H:i:s') . "] Aucun espace à purger.\n";
    exit(0);
}

foreach ($due as $space) {
    echo "[" . date('Y-m-d H:i:s') . "] Suppression de l'espace #{$space['id']} \"{$space['name']}\"";
    echo " (prévu le {$space['scheduled_deletion_at']}, motif : {$space['deletion_reason']})\n";

    ActivityLog::logAdmin(
        'space.auto_deleted',
        (int) ($space['deletion_scheduled_by'] ?? 0),
        'space',
        (int) $space['id'],
        [
            'name'   => $space['name'],
            'reason' => $space['deletion_reason'],
            'scheduled_at' => $space['scheduled_deletion_at'],
        ]
    );

    $spaceModel->delete((int) $space['id']);

    echo "[" . date('Y-m-d H:i:s') . "] ✓ Espace #{$space['id']} supprimé.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Purge terminée : " . count($due) . " espace(s) supprimé(s).\n";
