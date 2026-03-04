<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestionnaire de sessions.
 * Fournit une interface statique pour manipuler les données de session.
 */
class Session
{
    private static bool $started = false;

    /**
     * Démarre la session si elle n'est pas déjà démarrée.
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => false, // Mettre à true en production avec HTTPS
                'httponly'  => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        self::$started = true;
    }

    /**
     * Définit une valeur en session.
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Récupère une valeur de la session.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Vérifie si une clé existe en session.
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Supprime une valeur de la session.
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Récupère et supprime un message flash.
     */
    public static function getFlash(): ?array
    {
        $flash = self::get('flash');
        if ($flash) {
            self::remove('flash');
        }
        return $flash;
    }

    /**
     * Détruit la session.
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Régénère l'ID de session (sécurité).
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
