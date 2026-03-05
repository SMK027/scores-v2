<?php

declare(strict_types=1);

use App\Core\CSRF;
use App\Core\Session;

/**
 * Fonctions d'aide globales.
 */

/**
 * Échappe une chaîne pour la sortie HTML (protection XSS).
 */
function e(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Retourne l'adresse IP réelle du client.
 * Gère les cas derrière un proxy / reverse proxy / Docker.
 */
function get_client_ip(): string
{
    // X-Forwarded-For peut contenir plusieurs IPs séparées par des virgules
    // La première est l'IP du client original
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $clientIp = $ips[0];
        if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return $clientIp;
        }
    }

    if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Génère un champ CSRF pour les formulaires.
 */
function csrf_field(): string
{
    return CSRF::field();
}

/**
 * Génère le token CSRF courant.
 */
function csrf_token(): string
{
    return CSRF::generate();
}

/**
 * Récupère l'URL complète de l'application.
 */
function url(string $path = ''): string
{
    $base = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * Vérifie si l'utilisateur est connecté.
 */
function is_authenticated(): bool
{
    return Session::get('user_id') !== null;
}

/**
 * Retourne l'ID de l'utilisateur connecté.
 */
function current_user_id(): ?int
{
    $id = Session::get('user_id');
    return $id ? (int) $id : null;
}

/**
 * Retourne le nom d'utilisateur connecté.
 */
function current_username(): string
{
    return Session::get('username') ?? '';
}

/**
 * Retourne le rôle global de l'utilisateur.
 */
function current_global_role(): string
{
    return Session::get('global_role') ?? 'user';
}

/**
 * Formate une date pour l'affichage.
 */
function format_date(?string $date, string $format = 'd/m/Y H:i'): string
{
    if (!$date) {
        return '-';
    }
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Formate une date relative (il y a X minutes, etc.).
 */
function time_ago(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $now = new DateTime();
    $then = new DateTime($date);
    $diff = $now->diff($then);

    if ($diff->y > 0) {
        return "il y a {$diff->y} an" . ($diff->y > 1 ? 's' : '');
    }
    if ($diff->m > 0) {
        return "il y a {$diff->m} mois";
    }
    if ($diff->d > 0) {
        return "il y a {$diff->d} jour" . ($diff->d > 1 ? 's' : '');
    }
    if ($diff->h > 0) {
        return "il y a {$diff->h} heure" . ($diff->h > 1 ? 's' : '');
    }
    if ($diff->i > 0) {
        return "il y a {$diff->i} minute" . ($diff->i > 1 ? 's' : '');
    }
    return "à l'instant";
}

/**
 * Traduit un statut de partie.
 */
function game_status_label(string $status): string
{
    return match ($status) {
        'pending'     => 'En attente',
        'in_progress' => 'En cours',
        'paused'      => 'En pause',
        'completed'   => 'Terminée',
        default       => $status,
    };
}

/**
 * Retourne la classe CSS pour un statut de partie.
 */
function game_status_class(string $status): string
{
    return match ($status) {
        'pending'     => 'badge-secondary',
        'in_progress' => 'badge-success',
        'paused'      => 'badge-warning',
        'completed'   => 'badge-primary',
        default       => 'badge-secondary',
    };
}

/**
 * Traduit une condition de victoire.
 */
function win_condition_label(string $condition): string
{
    return match ($condition) {
        'highest_score' => 'Score le plus élevé',
        'lowest_score'  => 'Score le plus bas',
        'win_loss'      => 'Victoire/Défaite',
        'ranking'       => 'Classement',
        default         => $condition,
    };
}

/**
 * Traduit un rôle d'espace.
 */
function space_role_label(string $role): string
{
    return match ($role) {
        'admin'   => 'Administrateur',
        'manager' => 'Gestionnaire',
        'member'  => 'Membre',
        'guest'   => 'Invité',
        default   => $role,
    };
}

/**
 * Traduit un rôle global.
 */
function global_role_label(string $role): string
{
    return match ($role) {
        'superadmin' => 'Super Admin',
        'admin'      => 'Administrateur',
        'moderator'  => 'Modérateur',
        'user'       => 'Utilisateur',
        default      => $role,
    };
}

/**
 * Tronque un texte à une longueur donnée.
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Formate une durée en secondes en format lisible (ex: 1h 23min 45s).
 */
function format_duration(int $seconds): string
{
    if ($seconds <= 0) {
        return '0s';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;

    $parts = [];
    if ($hours > 0) {
        $parts[] = "{$hours}h";
    }
    if ($minutes > 0) {
        $parts[] = "{$minutes}min";
    }
    if ($secs > 0 || empty($parts)) {
        $parts[] = "{$secs}s";
    }

    return implode(' ', $parts);
}

/**
 * Traduit un statut de manche.
 */
function round_status_label(string $status): string
{
    return match ($status) {
        'in_progress' => 'En cours',
        'paused'      => 'En pause',
        'completed'   => 'Terminée',
        default       => $status,
    };
}

/**
 * Retourne la classe CSS pour un statut de manche.
 */
function round_status_class(string $status): string
{
    return match ($status) {
        'in_progress' => 'badge-info',
        'paused'      => 'badge-warning',
        'completed'   => 'badge-success',
        default       => 'badge-secondary',
    };
}
