<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Competition;

/**
 * API REST mobile pour les competitions d'un espace.
 */
class CompetitionApiController extends ApiController
{
    /**
     * GET /api/spaces/{id}/competitions
     */
    public function index(string $id): void
    {
        $this->requireAuth();
        $this->checkSpaceAccess((int) $id);

        $competitionModel = new Competition();
        $rows = $competitionModel->findBySpace((int) $id);

        $competitions = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'space_id' => (int) $row['space_id'],
                'name' => (string) $row['name'],
                'description' => $row['description'] ?? null,
                'status' => (string) $row['status'],
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
                'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
                'creator_name' => $row['creator_name'] ?? null,
                'session_count' => isset($row['session_count']) ? (int) $row['session_count'] : 0,
            ];
        }, $rows);

        $this->json([
            'success' => true,
            'competitions' => $competitions,
            'total' => count($competitions),
        ]);
    }
}
