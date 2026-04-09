<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Middleware;
use App\Config\Database;
use App\Models\Space;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\CompetitionSession;

/**
 * Export / import complet d'un espace.
 */
class SpaceTransferController extends Controller
{
    private const CHECKSUM_FAIL_LIMIT = 3;
    private const CHECKSUM_FAIL_WINDOW_MINUTES = 60;

    private \PDO $pdo;
    private Space $spaceModel;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->spaceModel = new Space();
    }

    /**
     * Exporte toutes les donnees d'un espace en JSON avec checksum.
     */
    public function export(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $spaceId = (int) $id;
        $member = Middleware::checkSpaceAccess($spaceId, (int) $this->getCurrentUserId(), ['admin']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id);
            return;
        }

        $space = $this->spaceModel->find($spaceId);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            return;
        }

        $payloadCore = [
            'format'      => 'scores-space-export-v1',
            'exported_at' => date('c'),
            'space'       => [
                'name'                  => $space['name'],
                'description'           => $space['description'],
                'restrictions'          => $this->spaceModel->getRestrictions($spaceId),
                'restriction_reason'    => $space['restriction_reason'] ?? null,
                'restricted_at'         => $space['restricted_at'] ?? null,
                'scheduled_deletion_at' => $space['scheduled_deletion_at'] ?? null,
                'deletion_reason'       => $space['deletion_reason'] ?? null,
            ],
            'data'        => $this->buildExportData($spaceId),
        ];

        $full = $payloadCore;
        $full['checksum'] = [
            'algorithm' => 'sha256',
            'value'     => $this->computeChecksum($payloadCore),
        ];

        $safeName = preg_replace('/[^a-zA-Z0-9-_]+/', '-', (string) $space['name']);
        $filename = sprintf('space-export-%s-%s.json', trim((string) $safeName, '-'), date('Ymd-His'));

        ActivityLog::logSpace($spaceId, 'space.export', (int) $this->getCurrentUserId(), 'space', $spaceId, [
            'filename' => $filename,
        ]);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($full, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Importe un export JSON et ecrase les donnees de l'espace (sauf membres).
     */
    public function import(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $spaceId = (int) $id;
        $currentUserId = (int) $this->getCurrentUserId();

        $member = Middleware::checkSpaceAccess($spaceId, $currentUserId, ['admin']);
        if (!$member) {
            $this->setFlash('danger', 'Permissions insuffisantes.');
            $this->redirect('/spaces/' . $id);
            return;
        }

        // Peut etre bloque automatiquement apres trop d'echecs checksum.
        $this->checkSpaceRestriction($spaceId, 'imports');

        $space = $this->spaceModel->find($spaceId);
        if (!$space) {
            $this->setFlash('danger', 'Espace introuvable.');
            $this->redirect('/spaces');
            return;
        }

        if (!isset($_FILES['space_import']) || !is_array($_FILES['space_import'])) {
            $this->setFlash('danger', 'Aucun fichier fourni.');
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        $file = $_FILES['space_import'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->setFlash('danger', 'Erreur lors de l\'upload du fichier.');
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        $raw = @file_get_contents((string) $file['tmp_name']);
        if ($raw === false || $raw === '') {
            $this->setFlash('danger', 'Fichier vide ou illisible.');
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->setFlash('danger', 'Format JSON invalide.');
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        if (($decoded['format'] ?? '') !== 'scores-space-export-v1') {
            $this->setFlash('danger', 'Format d\'export non reconnu.');
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        $checksum = $decoded['checksum']['value'] ?? null;
        if (!is_string($checksum) || $checksum === '') {
            $this->setFlash('danger', 'Checksum manquante dans le fichier.');
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        $payloadCore = $decoded;
        unset($payloadCore['checksum']);
        $computed = $this->computeChecksum($payloadCore);
        if (!hash_equals($checksum, $computed)) {
            $lockInfo = $this->registerChecksumFailureAndMaybeRestrict($spaceId, $currentUserId);
            if (!empty($lockInfo['restricted_now'])) {
                $this->setFlash('danger', 'Checksum invalide. Import automatiquement restreint après 3 tentatives échouées en 1 heure. Un administrateur du site doit réautoriser l\'import.');
            } else {
                $remaining = max(0, self::CHECKSUM_FAIL_LIMIT - (int) $lockInfo['recent_failures']);
                $this->setFlash('danger', 'Checksum invalide : le fichier a été altéré ou est corrompu.'
                    . ($remaining > 0
                        ? ' Encore ' . $remaining . ' tentative(s) avant blocage automatique de l\'import.'
                        : ''));
            }
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            $this->setFlash('danger', 'Données d\'export manquantes.');
            $this->redirect('/spaces/' . $id . '/edit');
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $this->applySpaceMetadataFromImport($spaceId, $decoded['space'] ?? [], $currentUserId);
            $this->wipeSpaceData($spaceId);
            $this->importAllData($spaceId, $data, $currentUserId);

            $this->pdo->commit();

            ActivityLog::logSpace($spaceId, 'space.import', $currentUserId, 'space', $spaceId, [
                'source_exported_at' => $decoded['exported_at'] ?? null,
            ]);

            $this->setFlash('success', 'Import termine avec succes. Les donnees de l\'espace ont ete remplacees (membres conserves).');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->setFlash('danger', 'Import impossible: ' . $e->getMessage());
        }

        $this->redirect('/spaces/' . $id . '/edit');
    }

    private function applySpaceMetadataFromImport(int $spaceId, array $spaceData, int $currentUserId): void
    {
        $restrictions = $spaceData['restrictions'] ?? [];
        if (!is_array($restrictions)) {
            $restrictions = [];
        }
        $restrictions = array_filter($restrictions);

        $scheduledDeletionAt = $this->normalizeDateTime($spaceData['scheduled_deletion_at'] ?? null);

        $stmt = $this->pdo->prepare(
            'UPDATE spaces
             SET name = :name,
                 description = :description,
                 restrictions = :restrictions,
                 restriction_reason = :restriction_reason,
                 restricted_by = :restricted_by,
                 restricted_at = :restricted_at,
                 scheduled_deletion_at = :scheduled_deletion_at,
                 deletion_reason = :deletion_reason,
                 deletion_scheduled_by = :deletion_scheduled_by
             WHERE id = :id'
        );

        $stmt->execute([
            'name'                  => (string) ($spaceData['name'] ?? 'Espace importé'),
            'description'           => (string) ($spaceData['description'] ?? ''),
            'restrictions'          => empty($restrictions) ? null : json_encode($restrictions, JSON_UNESCAPED_UNICODE),
            'restriction_reason'    => empty($restrictions) ? null : ($spaceData['restriction_reason'] ?? null),
            'restricted_by'         => empty($restrictions) ? null : $currentUserId,
            'restricted_at'         => empty($restrictions) ? null : $this->normalizeDateTime($spaceData['restricted_at'] ?? null),
            'scheduled_deletion_at' => $scheduledDeletionAt,
            'deletion_reason'       => $scheduledDeletionAt ? ($spaceData['deletion_reason'] ?? null) : null,
            'deletion_scheduled_by' => $scheduledDeletionAt ? $currentUserId : null,
            'id'                    => $spaceId,
        ]);
    }

    private function wipeSpaceData(int $spaceId): void
    {
        // Les membres (space_members) ne sont pas touches volontairement.
        $this->pdo->prepare('DELETE FROM space_invitations WHERE space_id = :space_id')->execute(['space_id' => $spaceId]);
        $this->pdo->prepare('DELETE FROM space_invites WHERE space_id = :space_id')->execute(['space_id' => $spaceId]);

        $this->pdo->prepare('DELETE FROM member_cards WHERE space_id = :space_id')->execute(['space_id' => $spaceId]);
        $this->pdo->prepare('DELETE FROM games WHERE space_id = :space_id')->execute(['space_id' => $spaceId]);
        $this->pdo->prepare('DELETE FROM competitions WHERE space_id = :space_id')->execute(['space_id' => $spaceId]);
        $this->pdo->prepare('DELETE FROM game_types WHERE space_id = :space_id')->execute(['space_id' => $spaceId]);
        $this->pdo->prepare('DELETE FROM players WHERE space_id = :space_id')->execute(['space_id' => $spaceId]);
    }

    private function importAllData(int $spaceId, array $data, int $currentUserId): void
    {
        $gameTypeMap = $this->importGameTypes($spaceId, $data['game_types'] ?? []);
        $playerMap = $this->importPlayers($spaceId, $data['players'] ?? []);

        [$competitionMap, $sessionMap] = $this->importCompetitions($spaceId, $currentUserId, $data['competitions'] ?? []);

        $this->importGames(
            $spaceId,
            $currentUserId,
            $data['games'] ?? [],
            $gameTypeMap,
            $playerMap,
            $competitionMap,
            $sessionMap
        );
    }

    private function importGameTypes(int $spaceId, array $rows): array
    {
        $map = [];

        // Charger les types globaux existants pour le remapping
        $globalStmt = $this->pdo->query('SELECT id, name, win_condition FROM game_types WHERE is_global = 1');
        $existingGlobals = $globalStmt->fetchAll(\PDO::FETCH_ASSOC);

        $localStmt = $this->pdo->prepare(
            'INSERT INTO game_types (space_id, name, description, win_condition, min_players, max_players, created_at, updated_at)
             VALUES (:space_id, :name, :description, :win_condition, :min_players, :max_players, :created_at, :updated_at)'
        );

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $oldId = (int) ($row['id'] ?? 0);
            if ($oldId <= 0) {
                continue;
            }

            // Type global : remapper vers le global existant par nom + win_condition
            if (!empty($row['is_global'])) {
                $matched = null;
                foreach ($existingGlobals as $eg) {
                    if ($eg['name'] === ($row['name'] ?? '') && $eg['win_condition'] === ($row['win_condition'] ?? '')) {
                        $matched = (int) $eg['id'];
                        break;
                    }
                }
                if ($matched !== null) {
                    $map[$oldId] = $matched;
                }
                // Si aucun global correspondant n'existe, on ignore (le type n'est pas disponible)
                continue;
            }

            // Type local : créer dans l'espace
            $localStmt->execute([
                'space_id'      => $spaceId,
                'name'          => (string) ($row['name'] ?? 'Type importé'),
                'description'   => $row['description'] ?? null,
                'win_condition' => (string) ($row['win_condition'] ?? 'highest_score'),
                'min_players'   => (int) ($row['min_players'] ?? 1),
                'max_players'   => isset($row['max_players']) ? (int) $row['max_players'] : null,
                'created_at'    => $this->normalizeDateTime($row['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                'updated_at'    => $this->normalizeDateTime($row['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);

            $map[$oldId] = (int) $this->pdo->lastInsertId();
        }

        return $map;
    }

    private function importPlayers(int $spaceId, array $rows): array
    {
        $map = [];
        $stmt = $this->pdo->prepare(
            'INSERT INTO players (space_id, name, user_id, created_at, updated_at)
             VALUES (:space_id, :name, NULL, :created_at, :updated_at)'
        );

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $oldId = (int) ($row['id'] ?? 0);
            if ($oldId <= 0) {
                continue;
            }

            $stmt->execute([
                'space_id'   => $spaceId,
                'name'       => (string) ($row['name'] ?? 'Joueur importé'),
                'created_at' => $this->normalizeDateTime($row['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);

            $map[$oldId] = (int) $this->pdo->lastInsertId();
        }

        return $map;
    }

    private function importCompetitions(int $spaceId, int $currentUserId, array $rows): array
    {
        $competitionMap = [];
        $sessionMap = [];

        $competitionStmt = $this->pdo->prepare(
            'INSERT INTO competitions (space_id, name, description, status, starts_at, ends_at, created_by, created_at, updated_at)
             VALUES (:space_id, :name, :description, :status, :starts_at, :ends_at, :created_by, :created_at, :updated_at)'
        );

        $sessionStmt = $this->pdo->prepare(
            'INSERT INTO competition_sessions (competition_id, session_number, referee_name, referee_email, password, is_active, is_locked, created_at)
             VALUES (:competition_id, :session_number, :referee_name, :referee_email, :password, :is_active, :is_locked, :created_at)'
        );

        foreach ($rows as $competition) {
            if (!is_array($competition)) {
                continue;
            }
            $oldCompetitionId = (int) ($competition['id'] ?? 0);
            if ($oldCompetitionId <= 0) {
                continue;
            }

            $competitionStmt->execute([
                'space_id'    => $spaceId,
                'name'        => (string) ($competition['name'] ?? 'Compétition importée'),
                'description' => $competition['description'] ?? null,
                'status'      => (string) ($competition['status'] ?? 'planned'),
                'starts_at'   => $this->normalizeDateTime($competition['starts_at'] ?? null) ?? date('Y-m-d H:i:s'),
                'ends_at'     => $this->normalizeDateTime($competition['ends_at'] ?? null) ?? date('Y-m-d H:i:s'),
                'created_by'  => $currentUserId,
                'created_at'  => $this->normalizeDateTime($competition['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                'updated_at'  => $this->normalizeDateTime($competition['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);

            $newCompetitionId = (int) $this->pdo->lastInsertId();
            $competitionMap[$oldCompetitionId] = $newCompetitionId;

            $sessions = $competition['sessions'] ?? [];
            foreach ($sessions as $session) {
                if (!is_array($session)) {
                    continue;
                }
                $oldSessionId = (int) ($session['id'] ?? 0);
                if ($oldSessionId <= 0) {
                    continue;
                }

                $sessionStmt->execute([
                    'competition_id' => $newCompetitionId,
                    'session_number' => (int) ($session['session_number'] ?? 1),
                    'referee_name'   => (string) ($session['referee_name'] ?? 'Arbitre'),
                    'referee_email'  => $session['referee_email'] ?? null,
                    'password'       => (string) ($session['password'] ?? CompetitionSession::generatePassword()),
                    'is_active'      => isset($session['is_active']) ? (int) $session['is_active'] : 1,
                    'is_locked'      => isset($session['is_locked']) ? (int) $session['is_locked'] : 0,
                    'created_at'     => $this->normalizeDateTime($session['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ]);

                $sessionMap[$oldSessionId] = (int) $this->pdo->lastInsertId();
            }
        }

        return [$competitionMap, $sessionMap];
    }

    private function importGames(
        int $spaceId,
        int $currentUserId,
        array $rows,
        array $gameTypeMap,
        array $playerMap,
        array $competitionMap,
        array $sessionMap
    ): void {
        $gameStmt = $this->pdo->prepare(
            'INSERT INTO games (space_id, competition_id, session_id, game_type_id, status, started_at, ended_at, notes, created_by, created_at, updated_at)
             VALUES (:space_id, :competition_id, :session_id, :game_type_id, :status, :started_at, :ended_at, :notes, :created_by, :created_at, :updated_at)'
        );

        $gamePlayerStmt = $this->pdo->prepare(
            'INSERT INTO game_players (game_id, player_id, total_score, `rank`, is_winner)
             VALUES (:game_id, :player_id, :total_score, :rank, :is_winner)'
        );

        $roundStmt = $this->pdo->prepare(
            'INSERT INTO rounds (game_id, round_number, status, started_at, ended_at, created_at, updated_at)
             VALUES (:game_id, :round_number, :status, :started_at, :ended_at, :created_at, :updated_at)'
        );

        $roundScoreStmt = $this->pdo->prepare(
            'INSERT INTO round_scores (round_id, player_id, score)
             VALUES (:round_id, :player_id, :score)'
        );

        $pauseStmt = $this->pdo->prepare(
            'INSERT INTO round_pauses (round_id, paused_at, resumed_at, duration_seconds)
             VALUES (:round_id, :paused_at, :resumed_at, :duration_seconds)'
        );

        $commentStmt = $this->pdo->prepare(
            'INSERT INTO comments (game_id, user_id, content, created_at, updated_at)
             VALUES (:game_id, :user_id, :content, :created_at, :updated_at)'
        );

        $userModel = new User();

        foreach ($rows as $game) {
            if (!is_array($game)) {
                continue;
            }

            $oldGameTypeId = (int) ($game['game_type_id'] ?? 0);
            if (!isset($gameTypeMap[$oldGameTypeId])) {
                continue;
            }

            $oldCompetitionId = isset($game['competition_id']) ? (int) $game['competition_id'] : 0;
            $oldSessionId = isset($game['session_id']) ? (int) $game['session_id'] : 0;

            $gameStmt->execute([
                'space_id'       => $spaceId,
                'competition_id' => $oldCompetitionId > 0 && isset($competitionMap[$oldCompetitionId]) ? $competitionMap[$oldCompetitionId] : null,
                'session_id'     => $oldSessionId > 0 && isset($sessionMap[$oldSessionId]) ? $sessionMap[$oldSessionId] : null,
                'game_type_id'   => $gameTypeMap[$oldGameTypeId],
                'status'         => (string) ($game['status'] ?? 'in_progress'),
                'started_at'     => $this->normalizeDateTime($game['started_at'] ?? null),
                'ended_at'       => $this->normalizeDateTime($game['ended_at'] ?? null),
                'notes'          => $game['notes'] ?? null,
                'created_by'     => $currentUserId,
                'created_at'     => $this->normalizeDateTime($game['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                'updated_at'     => $this->normalizeDateTime($game['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);

            $newGameId = (int) $this->pdo->lastInsertId();

            // Joueurs de la partie
            foreach (($game['players'] ?? []) as $gp) {
                if (!is_array($gp)) {
                    continue;
                }
                $oldPlayerId = (int) ($gp['player_id'] ?? 0);
                if (!isset($playerMap[$oldPlayerId])) {
                    continue;
                }

                $gamePlayerStmt->execute([
                    'game_id'      => $newGameId,
                    'player_id'    => $playerMap[$oldPlayerId],
                    'total_score'  => (float) ($gp['total_score'] ?? 0),
                    'rank'         => isset($gp['rank']) ? (int) $gp['rank'] : null,
                    'is_winner'    => isset($gp['is_winner']) ? (int) $gp['is_winner'] : 0,
                ]);
            }

            // Manches
            foreach (($game['rounds'] ?? []) as $round) {
                if (!is_array($round)) {
                    continue;
                }

                $roundStmt->execute([
                    'game_id'       => $newGameId,
                    'round_number'  => (int) ($round['round_number'] ?? 1),
                    'status'        => (string) ($round['status'] ?? 'in_progress'),
                    'started_at'    => $this->normalizeDateTime($round['started_at'] ?? null),
                    'ended_at'      => $this->normalizeDateTime($round['ended_at'] ?? null),
                    'created_at'    => $this->normalizeDateTime($round['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                    'updated_at'    => $this->normalizeDateTime($round['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ]);
                $newRoundId = (int) $this->pdo->lastInsertId();

                foreach (($round['scores'] ?? []) as $score) {
                    if (!is_array($score)) {
                        continue;
                    }
                    $oldPlayerId = (int) ($score['player_id'] ?? 0);
                    if (!isset($playerMap[$oldPlayerId])) {
                        continue;
                    }

                    $roundScoreStmt->execute([
                        'round_id'  => $newRoundId,
                        'player_id' => $playerMap[$oldPlayerId],
                        'score'     => (float) ($score['score'] ?? 0),
                    ]);
                }

                foreach (($round['pauses'] ?? []) as $pause) {
                    if (!is_array($pause)) {
                        continue;
                    }
                    $pauseStmt->execute([
                        'round_id'         => $newRoundId,
                        'paused_at'        => $this->normalizeDateTime($pause['paused_at'] ?? null) ?? date('Y-m-d H:i:s'),
                        'resumed_at'       => $this->normalizeDateTime($pause['resumed_at'] ?? null),
                        'duration_seconds' => isset($pause['duration_seconds']) ? (int) $pause['duration_seconds'] : null,
                    ]);
                }
            }

            // Commentaires
            foreach (($game['comments'] ?? []) as $comment) {
                if (!is_array($comment)) {
                    continue;
                }

                $username = trim((string) ($comment['author_username'] ?? ''));
                $userId = $currentUserId;
                if ($username !== '') {
                    $found = $userModel->findByUsername($username);
                    if ($found && !empty($found['id'])) {
                        $userId = (int) $found['id'];
                    }
                }

                $content = (string) ($comment['content'] ?? '');
                if ($content === '') {
                    continue;
                }

                if ($username !== '' && $userId === $currentUserId) {
                    $content = '[Importé - ' . $username . '] ' . $content;
                }

                $commentStmt->execute([
                    'game_id'     => $newGameId,
                    'user_id'     => $userId,
                    'content'     => $content,
                    'created_at'  => $this->normalizeDateTime($comment['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                    'updated_at'  => $this->normalizeDateTime($comment['updated_at'] ?? null) ?? date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function buildExportData(int $spaceId): array
    {
        // Types locaux de l'espace
        $gameTypes = $this->pdo->prepare(
            'SELECT id, name, description, win_condition, min_players, max_players, is_global, created_at, updated_at
             FROM game_types WHERE space_id = :space_id ORDER BY id ASC'
        );
        $gameTypes->execute(['space_id' => $spaceId]);
        $localTypes = $gameTypes->fetchAll(\PDO::FETCH_ASSOC);

        // Types globaux utilisés par les parties de l'espace
        $globalTypesStmt = $this->pdo->prepare(
            'SELECT DISTINCT gt.id, gt.name, gt.description, gt.win_condition, gt.min_players, gt.max_players, gt.is_global, gt.created_at, gt.updated_at
             FROM game_types gt
             INNER JOIN games g ON g.game_type_id = gt.id
             WHERE g.space_id = :space_id AND gt.is_global = 1
             ORDER BY gt.id ASC'
        );
        $globalTypesStmt->execute(['space_id' => $spaceId]);
        $globalTypes = $globalTypesStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fusion : locaux + globaux (sans doublons)
        $allGameTypes = $localTypes;
        $existingIds = array_column($localTypes, 'id');
        foreach ($globalTypes as $gt) {
            if (!in_array((int) $gt['id'], $existingIds, true)) {
                $allGameTypes[] = $gt;
            }
        }

        $players = $this->pdo->prepare('SELECT id, name, created_at, updated_at FROM players WHERE space_id = :space_id ORDER BY id ASC');
        $players->execute(['space_id' => $spaceId]);

        $competitionsStmt = $this->pdo->prepare('SELECT * FROM competitions WHERE space_id = :space_id ORDER BY id ASC');
        $competitionsStmt->execute(['space_id' => $spaceId]);
        $competitions = $competitionsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $sessionStmt = $this->pdo->prepare('SELECT * FROM competition_sessions WHERE competition_id = :competition_id ORDER BY session_number ASC, id ASC');
        foreach ($competitions as &$competition) {
            $sessionStmt->execute(['competition_id' => (int) $competition['id']]);
            $competition['sessions'] = $sessionStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        unset($competition);

        $gamesStmt = $this->pdo->prepare('SELECT g.*, gt.name AS game_type_name, gt.win_condition, (SELECT COUNT(*) FROM game_players gp WHERE gp.game_id = g.id) AS player_count FROM games g JOIN game_types gt ON gt.id = g.game_type_id WHERE g.space_id = :space_id ORDER BY g.id ASC');
        $gamesStmt->execute(['space_id' => $spaceId]);
        $games = $gamesStmt->fetchAll(\PDO::FETCH_ASSOC);

        $gamePlayersStmt = $this->pdo->prepare('SELECT gp.*, p.name AS player_name FROM game_players gp JOIN players p ON p.id = gp.player_id WHERE gp.game_id = :game_id ORDER BY gp.id ASC');
        $roundsStmt = $this->pdo->prepare('SELECT * FROM rounds WHERE game_id = :game_id ORDER BY round_number ASC, id ASC');
        $scoresStmt = $this->pdo->prepare('SELECT rs.*, p.name AS player_name FROM round_scores rs JOIN players p ON p.id = rs.player_id WHERE rs.round_id = :round_id ORDER BY rs.id ASC');
        $pausesStmt = $this->pdo->prepare('SELECT * FROM round_pauses WHERE round_id = :round_id ORDER BY paused_at ASC, id ASC');
        $commentsStmt = $this->pdo->prepare('SELECT c.id, c.content, c.created_at, c.updated_at, u.username AS author_username FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.game_id = :game_id ORDER BY c.id ASC');

        foreach ($games as &$game) {
            $gameId = (int) $game['id'];

            $gamePlayersStmt->execute(['game_id' => $gameId]);
            $playersInGame = $gamePlayersStmt->fetchAll(\PDO::FETCH_ASSOC);
            $game['players'] = $playersInGame;

            $roundsStmt->execute(['game_id' => $gameId]);
            $rounds = $roundsStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rounds as &$round) {
                $roundId = (int) $round['id'];

                $scoresStmt->execute(['round_id' => $roundId]);
                $scores = $scoresStmt->fetchAll(\PDO::FETCH_ASSOC);
                $round['scores'] = $scores;

                $pausesStmt->execute(['round_id' => $roundId]);
                $round['pauses'] = $pausesStmt->fetchAll(\PDO::FETCH_ASSOC);

                $round['participants'] = array_map(
                    static fn(array $p): array => [
                        'player_id'   => (int) $p['player_id'],
                        'player_name' => $p['player_name'],
                    ],
                    $playersInGame
                );

                $round['ranking'] = $this->computeRoundRanking($scores, (string) $game['win_condition']);
            }
            unset($round);

            $game['rounds'] = $rounds;

            $commentsStmt->execute(['game_id' => $gameId]);
            $game['comments'] = $commentsStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        unset($game);

        return [
            'game_types'   => $allGameTypes,
            'players'      => $players->fetchAll(\PDO::FETCH_ASSOC),
            'competitions' => $competitions,
            'games'        => $games,
        ];
    }

    /**
     * Calcule un classement de manche a partir des scores exportes.
     */
    private function computeRoundRanking(array $scores, string $winCondition): array
    {
        if (empty($scores)) {
            return [];
        }

        usort($scores, function (array $a, array $b) use ($winCondition): int {
            $sa = (float) ($a['score'] ?? 0);
            $sb = (float) ($b['score'] ?? 0);
            if ($sa === $sb) {
                return strcmp((string) ($a['player_name'] ?? ''), (string) ($b['player_name'] ?? ''));
            }
            if ($winCondition === 'lowest_score' || $winCondition === 'ranking') {
                return $sa <=> $sb;
            }
            return $sb <=> $sa;
        });

        $ranked = [];
        $lastScore = null;
        $rank = 0;
        foreach ($scores as $idx => $s) {
            $score = (float) ($s['score'] ?? 0);
            if ($lastScore === null || $score !== $lastScore) {
                $rank = $idx + 1;
            }
            $lastScore = $score;

            $ranked[] = [
                'rank'        => $rank,
                'player_id'   => (int) ($s['player_id'] ?? 0),
                'player_name' => $s['player_name'] ?? '',
                'score'       => $score,
            ];
        }

        return $ranked;
    }

    private function computeChecksum(array $payloadCore): string
    {
        $normalized = $this->normalizeForChecksum($payloadCore);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', (string) $json);
    }

    /**
     * Enregistre un echec checksum et applique une restriction automatique
     * si 3 echecs sont detectes sur une fenetre d'1 heure.
     *
     * @return array{recent_failures:int,restricted_now:bool}
     */
    private function registerChecksumFailureAndMaybeRestrict(int $spaceId, int $userId): array
    {
        ActivityLog::logSpace(
            $spaceId,
            'space.import.checksum_invalid',
            $userId,
            'space',
            $spaceId,
            ['window_minutes' => self::CHECKSUM_FAIL_WINDOW_MINUTES]
        );

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM activity_logs
             WHERE scope = :scope
               AND scope_id = :space_id
               AND action = :action
               AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue(':scope', 'space');
        $stmt->bindValue(':space_id', $spaceId, \PDO::PARAM_INT);
        $stmt->bindValue(':action', 'space.import.checksum_invalid');
        $stmt->bindValue(':minutes', self::CHECKSUM_FAIL_WINDOW_MINUTES, \PDO::PARAM_INT);
        $stmt->execute();

        $recentFailures = (int) $stmt->fetchColumn();
        $restrictedNow = false;

        if ($recentFailures >= self::CHECKSUM_FAIL_LIMIT) {
            $currentRestrictions = $this->spaceModel->getRestrictions($spaceId);
            if (empty($currentRestrictions['imports'])) {
                $currentRestrictions['imports'] = true;

                $space = $this->spaceModel->find($spaceId);
                $baseReason = trim((string) ($space['restriction_reason'] ?? ''));
                $autoReason = 'Blocage automatique de l\'import après 3 checksums invalides en 1 heure. Réactivation requise par l\'administration du site.';
                $reason = $baseReason !== '' ? ($baseReason . ' | ' . $autoReason) : $autoReason;

                $this->spaceModel->setRestrictions($spaceId, $currentRestrictions, $reason, $userId);

                ActivityLog::logSpace(
                    $spaceId,
                    'space.import.auto_restriction',
                    $userId,
                    'space',
                    $spaceId,
                    [
                        'recent_checksum_failures' => $recentFailures,
                        'threshold' => self::CHECKSUM_FAIL_LIMIT,
                        'window_minutes' => self::CHECKSUM_FAIL_WINDOW_MINUTES,
                    ]
                );

                $restrictedNow = true;
            }
        }

        return [
            'recent_failures' => $recentFailures,
            'restricted_now' => $restrictedNow,
        ];
    }

    private function normalizeForChecksum(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        if ($this->isAssoc($data)) {
            ksort($data);
            foreach ($data as $k => $v) {
                $data[$k] = $this->normalizeForChecksum($v);
            }
            return $data;
        }

        foreach ($data as $i => $v) {
            $data[$i] = $this->normalizeForChecksum($v);
        }
        return $data;
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($str);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
