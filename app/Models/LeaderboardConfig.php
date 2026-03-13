<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Configuration du leaderboard global (seuils d'eligibilite).
 */
class LeaderboardConfig extends Model
{
    protected string $table = 'leaderboard_config';

    /**
     * Valeurs par defaut.
     */
    private static array $defaults = [
        'min_rounds_played' => '5',
        'min_spaces_played' => '2',
    ];

    /**
     * Retourne la configuration complete.
     * Cree la table et les lignes par defaut si necessaire.
     *
     * @return array<string, string>
     */
    public function getConfig(): array
    {
        $this->ensureTableAndDefaults();

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
     * Met a jour un parametre.
     */
    public function updateSetting(string $key, string $value): bool
    {
        $this->ensureTableAndDefaults();
        $stmt = $this->db->prepare("UPDATE {$this->table} SET setting_value = :val WHERE setting_key = :key");
        return $stmt->execute(['val' => $value, 'key' => $key]);
    }

    /**
     * Met a jour toute la configuration d'un coup.
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
     * Recuperation typage int.
     */
    public function getInt(string $key): int
    {
        $config = $this->getConfig();
        return (int) ($config[$key] ?? self::$defaults[$key] ?? 0);
    }

    /**
     * Cree la table et les valeurs de base si besoin.
     */
    private function ensureTableAndDefaults(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $this->db->prepare("INSERT IGNORE INTO {$this->table} (setting_key, setting_value, label) VALUES
            (:k1, :v1, :l1),
            (:k2, :v2, :l2)");
        $stmt->execute([
            'k1' => 'min_rounds_played',
            'v1' => self::$defaults['min_rounds_played'],
            'l1' => 'Nombre minimum de manches jouees',
            'k2' => 'min_spaces_played',
            'v2' => self::$defaults['min_spaces_played'],
            'l2' => 'Nombre minimum d\'espaces distincts',
        ]);
    }
}
