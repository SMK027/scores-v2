<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Classe utilitaire pour les JSON Web Tokens (HMAC-SHA256).
 * Utilisée pour l'authentification de l'API mobile.
 */
class JWT
{
    /**
     * Génère un JWT.
     *
     * @param array  $payload Données à encoder (ex: user_id, username, etc.)
     * @param int    $ttl     Durée de validité en secondes (défaut : 30 jours)
     * @return string Token JWT
     */
    public static function encode(array $payload, int $ttl = 2592000): string
    {
        $secret = self::getSecret();

        $header = self::base64url(['alg' => 'HS256', 'typ' => 'JWT']);

        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;
        $body = self::base64url($payload);

        $signature = self::base64urlRaw(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * Décode et valide un JWT.
     *
     * @param string $token Token JWT
     * @return array|null   Payload décodé ou null si invalide/expiré
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;
        $secret = self::getSecret();

        // Vérifier la signature
        $expected = self::base64urlRaw(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(
            base64_decode(strtr($body, '-_', '+/'), true),
            true
        );

        if (!is_array($payload)) {
            return null;
        }

        // Vérifier l'expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function getSecret(): string
    {
        $key = getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? 'default_insecure_key');
        return $key;
    }

    private static function base64url(array $data): string
    {
        return self::base64urlRaw(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private static function base64urlRaw(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
