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
     * Génère un mot de passe aléatoire de 12 caractères (lettres + chiffres).
     */
    public static function generatePassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
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
            SELECT cs.*, u.username AS referee_user_username,
                   (SELECT COUNT(*) FROM games g WHERE g.session_id = cs.id) AS game_count
            FROM {$this->table} cs
            LEFT JOIN users u ON u.id = cs.referee_user_id
            WHERE cs.competition_id = :competition_id
            ORDER BY cs.session_number ASC
        ");
        $stmt->execute(['competition_id' => $competitionId]);
        return $stmt->fetchAll();
    }

    /**
     * Trouve une session par compétition et numéro (pour vérifier le verrouillage).
     */
    public function findByCompetitionAndNumber(int $competitionId, int $sessionNumber): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE competition_id = :competition_id
              AND session_number = :session_number
        ");
        $stmt->execute([
            'competition_id' => $competitionId,
            'session_number' => $sessionNumber,
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Authentifie une session par numéro + mot de passe pour une compétition.
     * Refuse l'accès si la session est verrouillée (is_locked).
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
              AND cs.is_locked = 0
        ");
        $stmt->execute([
            'competition_id' => $competitionId,
            'session_number' => $sessionNumber,
            'password'       => $password,
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    // ================================================================
    // Tentatives de connexion & verrouillage
    // ================================================================

    /**
     * Enregistre une tentative de connexion échouée.
     */
    public function recordFailedAttempt(int $sessionId, string $ip): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO session_login_attempts (session_id, ip_address) VALUES (:sid, :ip)
        ");
        $stmt->execute(['sid' => $sessionId, 'ip' => $ip]);
    }

    /**
     * Compte les tentatives échouées récentes pour une session.
     */
    public function countRecentFailedAttempts(int $sessionId, int $windowMinutes = 15): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM session_login_attempts
            WHERE session_id = :sid
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->bindValue(':sid', $sessionId, \PDO::PARAM_INT);
        $stmt->bindValue(':minutes', $windowMinutes, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Verrouille une session (bloque l'accès après trop de tentatives).
     */
    public function lock(int $sessionId): bool
    {
        return $this->update($sessionId, ['is_locked' => 1]);
    }

    /**
     * Déverrouille une session et efface les tentatives échouées.
     */
    public function unlock(int $sessionId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM session_login_attempts WHERE session_id = :sid");
        $stmt->execute(['sid' => $sessionId]);
        return $this->update($sessionId, ['is_locked' => 0]);
    }

    /**
     * Réinitialise le mot de passe d'une session, la déverrouille et la réactive.
     */
    public function resetPassword(int $sessionId): string
    {
        $newPassword = self::generatePassword();
        $this->update($sessionId, [
            'password'  => $newPassword,
            'is_locked' => 0,
            'is_active' => 1,
        ]);
        // Effacer les tentatives échouées
        $stmt = $this->db->prepare("DELETE FROM session_login_attempts WHERE session_id = :sid");
        $stmt->execute(['sid' => $sessionId]);
        return $newPassword;
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
    /**
     * Crée N sessions pour une compétition.
     *
     * @param array $referees  Tableau de ['name' => ..., 'email' => ...]
     * @return array Les sessions créées (avec id, session_number, password, referee_email)
     */
    public function createBatch(int $competitionId, array $referees): array
    {
        $created = [];
        $nextNum = $this->nextSessionNumber($competitionId);

        foreach ($referees as $referee) {
            $name  = is_array($referee) ? trim($referee['name'] ?? '') : trim($referee);
            $email = is_array($referee) ? trim($referee['email'] ?? '') : '';
            $userId = is_array($referee) ? (int) ($referee['user_id'] ?? 0) : 0;

            $password = self::generatePassword();
            $id = $this->create([
                'competition_id' => $competitionId,
                'session_number' => $nextNum,
                'referee_name'   => $name,
                'referee_email'  => $email ?: null,
                'referee_user_id' => $userId > 0 ? $userId : null,
                'password'       => $password,
                'is_active'      => 1,
            ]);
            $created[] = [
                'id'             => $id,
                'session_number' => $nextNum,
                'referee_name'   => $name,
                'referee_email'  => $email ?: null,
                'referee_user_id' => $userId > 0 ? $userId : null,
                'password'       => $password,
            ];
            $nextNum++;
        }

        return $created;
    }

    /**
     * Désactive une session (interruption à distance).
     */
    public function deactivate(int $sessionId): bool
    {
        return $this->update($sessionId, ['is_active' => 0]);
    }

    /**
     * Réactive une session.
     */
    public function reactivate(int $sessionId): bool
    {
        return $this->update($sessionId, ['is_active' => 1]);
    }

    /**
     * Met une session en pause temporaire (désactivation jusqu'à pause_until).
     */
    public function pauseTemporarily(int $sessionId, int $minutes): bool
    {
        $minutes = max(1, $minutes);
        $pauseUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));

        return $this->update($sessionId, [
            'is_active' => 0,
            'pause_until' => $pauseUntil,
        ]);
    }

    /**
     * Réactive une session uniquement si sa pause est expirée.
     */
    public function reactivateIfPauseExpired(int $sessionId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table}
             SET is_active = 1,
                 pause_until = NULL
             WHERE id = :sid
               AND is_active = 0
               AND pause_until IS NOT NULL
               AND pause_until <= NOW()
               AND closed_at IS NULL"
        );

        return $stmt->execute(['sid' => $sessionId]);
    }

    /**
     * Ferme définitivement une session arbitre.
     */
    public function closePermanently(int $sessionId): bool
    {
        return $this->update($sessionId, [
            'is_active' => 0,
            'pause_until' => null,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Retourne une session active d'une compétition assignée à un compte arbitre.
     */
    public function findAssignedSession(int $competitionId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cs.*, c.space_id, c.name AS competition_name, c.status AS competition_status
             FROM {$this->table} cs
             INNER JOIN competitions c ON c.id = cs.competition_id
             WHERE cs.competition_id = :cid
               AND cs.referee_user_id = :uid
               AND cs.is_active = 1
             ORDER BY cs.session_number ASC
             LIMIT 1"
        );
        $stmt->execute(['cid' => $competitionId, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
