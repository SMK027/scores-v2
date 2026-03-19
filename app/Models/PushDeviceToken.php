<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PushDeviceToken extends Model
{
    protected string $table = 'push_device_tokens';

    public function upsertForUser(int $userId, string $expoPushToken, string $platform = 'unknown'): void
    {
        $this->query(
            "INSERT INTO {$this->table} (user_id, expo_push_token, platform, created_at, updated_at)
             VALUES (:user_id, :expo_push_token, :platform, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                platform = VALUES(platform),
                updated_at = NOW()",
            [
                'user_id' => $userId,
                'expo_push_token' => $expoPushToken,
                'platform' => $platform,
            ]
        );
    }

    public function deleteForUser(int $userId, string $expoPushToken): bool
    {
        $stmt = $this->query(
            "DELETE FROM {$this->table} WHERE user_id = :user_id AND expo_push_token = :expo_push_token",
            [
                'user_id' => $userId,
                'expo_push_token' => $expoPushToken,
            ]
        );

        return $stmt->rowCount() > 0;
    }

    public function findTokensByUser(int $userId): array
    {
        $stmt = $this->query(
            "SELECT expo_push_token FROM {$this->table} WHERE user_id = :user_id ORDER BY updated_at DESC",
            ['user_id' => $userId]
        );

        return array_values(array_filter(array_map(
            static fn(array $row): string => (string) ($row['expo_push_token'] ?? ''),
            $stmt->fetchAll()
        )));
    }

    public function isValidExpoPushToken(string $expoPushToken): bool
    {
        return (bool) preg_match('/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9\-]+\]$/', $expoPushToken);
    }
}