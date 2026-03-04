<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protection contre les attaques CSRF (Cross-Site Request Forgery).
 * Génère et valide des tokens de sécurité pour les formulaires.
 */
class CSRF
{
    private const TOKEN_KEY = 'csrf_token';

    /**
     * Génère ou récupère le token CSRF de la session en cours.
     */
    public static function generate(): string
    {
        $token = Session::get(self::TOKEN_KEY);
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::TOKEN_KEY, $token);
        }
        return $token;
    }

    /**
     * Valide un token CSRF soumis.
     */
    public static function validate(string $token): bool
    {
        $sessionToken = Session::get(self::TOKEN_KEY);
        if (!$sessionToken || !$token) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    /**
     * Génère un champ HTML hidden avec le token CSRF.
     */
    public static function field(): string
    {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Régénère le token CSRF (après validation réussie ou connexion).
     */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::TOKEN_KEY, $token);
        return $token;
    }
}
