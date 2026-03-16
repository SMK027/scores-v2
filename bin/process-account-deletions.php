#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script CLI de finalisation des suppressions de compte.
 *
 * Pour chaque compte en statut pending_deletion arrivé à échéance (J+15) :
 * - suspend définitivement l'accès
 * - anonymise les données personnelles
 * - délie les joueurs du compte
 * - notifie l'utilisateur sur son email de contact
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Accès interdit.');
}

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Core\Mailer;
use App\Models\ActivityLog;
use App\Models\User;

$userModel = new User();
$dueUsers = $userModel->findDueDeletionRequests(200);

if (empty($dueUsers)) {
    echo '[' . date('Y-m-d H:i:s') . "] Aucun compte à anonymiser.\n";
    exit(0);
}

$mailer = new Mailer();
$appName = 'Scores';

foreach ($dueUsers as $user) {
    $userId = (int) $user['id'];
    $contactEmail = (string) ($user['deletion_contact_email'] ?? '');

    try {
        $ok = $userModel->finalizeDeletion($userId);
        if (!$ok) {
            echo '[' . date('Y-m-d H:i:s') . "] Skip user #{$userId} (statut non éligible).\n";
            continue;
        }

        ActivityLog::logAuth('account.deletion.finalized', $userId, [
            'executed_at' => date('Y-m-d H:i:s'),
        ]);

        if ($contactEmail !== '') {
            $subject = "{$appName} — Suppression de compte finalisée";
            $body = '<p>Bonjour,</p>'
                . '<p>Votre demande de suppression de compte a été traitée définitivement.</p>'
                . '<p>Vos données personnelles ont été anonymisées conformément au droit à l\'oubli. '
                . 'Votre compte n\'est plus visible dans les espaces ni dans le leaderboard.</p>'
                . '<p>L\'équipe ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</p>';
            $mailer->send($contactEmail, $subject, $body);
        }

        echo '[' . date('Y-m-d H:i:s') . "] ✓ Compte #{$userId} anonymisé.\n";
    } catch (\Throwable $e) {
        echo '[' . date('Y-m-d H:i:s') . "] ERREUR user #{$userId}: {$e->getMessage()}\n";
    }
}
