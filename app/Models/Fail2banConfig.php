<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Fail2banConfig – configuration du fail2ban lue depuis la BDD.
 * Pattern identique à PasswordPolicy (clé/valeur).
 */
class Fail2banConfig extends Model
{
    protected string $table = 'fail2ban_config';

    /**
     * Valeurs par défaut si la table n'existe pas encore.
     */
    private static array $defaults = [
        'enabled'        => '1',
        'max_attempts'   => '3',
        'window_minutes' => '15',
        'ban_duration'   => '30',
        'ban_ip'         => '1',
        'ban_account'    => '1',
        'exempt_staff'   => '1',
    ];

    /**
     * Récupère la configuration complète.
     *
     * @return array<string, string>
     */
    public function getConfig(): array
    {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM {$this->table}");
            $rows = $stmt->fetchAll();
        } catch (\PDOException $e) {
            return self::$defaults;
        }

        if (empty($rows)) {
            return self::$defaults;
        }

        $config = [];
        foreach ($rows as $row) {
            $config[$row['setting_key']] = $row['setting_value'];
        }

        return array_merge(self::$defaults, $config);
    }

    /**
     * Récupère la configuration avec les labels pour l'affichage admin.
     *
     * @return array<int, array{setting_key: string, setting_value: string, label: string}>
     */
    public function getConfigWithLabels(): array
    {
        try {
            $stmt = $this->db->query("SELECT id, setting_key, setting_value, label FROM {$this->table} ORDER BY id");
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Met à jour un paramètre.
     */
    public function updateSetting(string $key, string $value): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET setting_value = :val WHERE setting_key = :key");
        return $stmt->execute(['val' => $value, 'key' => $key]);
    }

    /**
     * Met à jour tous les paramètres d'un coup.
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
     * Vérifie si le fail2ban est activé.
     */
    public function isEnabled(): bool
    {
        $config = $this->getConfig();
        return ($config['enabled'] ?? '0') === '1';
    }

    /**
     * Raccourci : récupère un paramètre numérique.
     */
    public function getInt(string $key): int
    {
        $config = $this->getConfig();
        return (int) ($config[$key] ?? self::$defaults[$key] ?? 0);
    }

    /**
     * Raccourci : récupère un paramètre booléen.
     */
    public function getBool(string $key): bool
    {
        $config = $this->getConfig();
        return ($config[$key] ?? '0') === '1';
    }
}
