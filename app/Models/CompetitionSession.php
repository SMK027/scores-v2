<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle CompetitionSession – Sessions de saisie d'une compétition.
 */
class CompetitionSession extends Model
{
    protected string $table = 'competition_sessions';

    /**
     * Génère un mot de passe aléatoire de 6 caractères (lettres + chiffres).
     */
    public static function generatePassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < 6; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Sessions d'une compétition, triées par numéro.
     */
    public function findByCompetition(int $competitionId): array
    {
        $stmt = $this->db->prepare("
            SELECT cs.*,
                   (SELECT COUNT(*) FROM games g WHERE g.session_id = cs.id) AS game_count
            FROM {$this->table} cs
            WHERE cs.competition_id = :competition_id
            ORDER BY cs.session_number ASC
        ");
        $stmt->execute(['competition_id' => $competitionId]);
        return $stmt->fetchAll();
    }

    /**
     * Authentifie une session par numéro + mot de passe pour une compétition.
     */
    public function authenticate(int $competitionId, int $sessionNumber, string $password): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cs.*, c.space_id, c.name AS competition_name, c.status AS competition_status,
                   c.starts_at, c.ends_at
            FROM {$this->table} cs
            JOIN competitions c ON c.id = cs.competition_id
            WHERE cs.competition_id = :competition_id
              AND cs.session_number = :session_number
              AND cs.password = :password
              AND cs.is_active = 1
        ");
        $stmt->execute([
            'competition_id' => $competitionId,
            'session_number' => $sessionNumber,
            'password'       => $password,
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Prochain numéro de session pour une compétition.
     */
    public function nextSessionNumber(int $competitionId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(session_number), 0) + 1
            FROM {$this->table}
            WHERE competition_id = :competition_id
        ");
        $stmt->execute(['competition_id' => $competitionId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Crée N sessions pour une compétition.
     *
     * @return array Les sessions créées (avec id, session_number, password)
     */
    public function createBatch(int $competitionId, array $refereeNames): array
    {
        $created = [];
        $nextNum = $this->nextSessionNumber($competitionId);

        foreach ($refereeNames as $name) {
            $password = self::generatePassword();
            $id = $this->create([
                'competition_id' => $competitionId,
                'session_number' => $nextNum,
                'referee_name'   => trim($name),
                'password'       => $password,
                'is_active'      => 1,
            ]);
            $created[] = [
                'id'             => $id,
                'session_number' => $nextNum,
                'referee_name'   => trim($name),
                'password'       => $password,
            ];
            $nextNum++;
        }

        return $created;
    }
}
