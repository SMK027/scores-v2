<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle PasswordPolicy - Politique de mot de passe dynamique.
 * Stocke et lit la configuration depuis la table password_policy.
 */
class PasswordPolicy extends Model
{
    protected string $table = 'password_policy';

    /**
     * Valeurs par défaut si la table n'existe pas encore.
     */
    private static array $defaults = [
        'min_length'        => '12',
        'require_lowercase' => '1',
        'require_uppercase' => '1',
        'require_digit'     => '1',
        'require_special'   => '1',
    ];

    /**
     * Récupère toute la politique sous forme de tableau associatif.
     *
     * @return array<string, string>
     */
    public function getPolicy(): array
    {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, label FROM {$this->table}");
            $rows = $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Table pas encore créée → valeurs par défaut
            return self::$defaults;
        }

        if (empty($rows)) {
            return self::$defaults;
        }

        $policy = [];
        foreach ($rows as $row) {
            $policy[$row['setting_key']] = $row['setting_value'];
        }

        return $policy;
    }

    /**
     * Récupère la politique avec les labels pour l'admin.
     *
     * @return array<int, array{setting_key: string, setting_value: string, label: string}>
     */
    public function getPolicyWithLabels(): array
    {
        try {
            $stmt = $this->db->query("SELECT id, setting_key, setting_value, label FROM {$this->table} ORDER BY id");
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Met à jour une clé de politique.
     */
    public function updateSetting(string $key, string $value): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET setting_value = :val WHERE setting_key = :key");
        return $stmt->execute(['val' => $value, 'key' => $key]);
    }

    /**
     * Met à jour toute la politique d'un coup.
     *
     * @param array<string, string> $settings
     */
    public function updateAll(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->updateSetting($key, $value);
        }
    }

    /**
     * Valide un mot de passe contre la politique actuelle.
     *
     * @return string[] Liste des erreurs (vide si le mot de passe est conforme)
     */
    public function validate(string $password): array
    {
        $policy = $this->getPolicy();
        $errors = [];

        $minLength = (int) ($policy['min_length'] ?? 8);
        if (strlen($password) < $minLength) {
            $errors[] = "Le mot de passe doit contenir au moins {$minLength} caractères.";
        }

        if (($policy['require_lowercase'] ?? '0') === '1' && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre minuscule.';
        }

        if (($policy['require_uppercase'] ?? '0') === '1' && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une lettre majuscule.';
        }

        if (($policy['require_digit'] ?? '0') === '1' && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }

        if (($policy['require_special'] ?? '0') === '1' && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        }

        return $errors;
    }

    /**
     * Retourne un résumé textuel lisible de la politique pour l'affichage.
     */
    public function getSummary(): string
    {
        $policy = $this->getPolicy();
        $parts = [];

        $parts[] = (int) ($policy['min_length'] ?? 8) . ' caractères minimum';

        if (($policy['require_lowercase'] ?? '0') === '1') {
            $parts[] = '1 minuscule';
        }
        if (($policy['require_uppercase'] ?? '0') === '1') {
            $parts[] = '1 majuscule';
        }
        if (($policy['require_digit'] ?? '0') === '1') {
            $parts[] = '1 chiffre';
        }
        if (($policy['require_special'] ?? '0') === '1') {
            $parts[] = '1 caractère spécial';
        }

        return implode(', ', $parts);
    }

    /**
     * Retourne la politique au format JSON pour le JavaScript côté client.
     */
    public function toJson(): string
    {
        $policy = $this->getPolicy();
        return json_encode([
            'min_length'        => (int) ($policy['min_length'] ?? 8),
            'require_lowercase' => ($policy['require_lowercase'] ?? '0') === '1',
            'require_uppercase' => ($policy['require_uppercase'] ?? '0') === '1',
            'require_digit'     => ($policy['require_digit'] ?? '0') === '1',
            'require_special'   => ($policy['require_special'] ?? '0') === '1',
        ], JSON_UNESCAPED_UNICODE);
    }
}
