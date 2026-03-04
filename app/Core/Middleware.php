<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\SpaceMember;

/**
 * Middleware pour les vérifications d'accès.
 */
class Middleware
{
    /**
     * Vérifie que l'utilisateur est membre d'un espace et possède un rôle suffisant.
     *
     * @param int    $spaceId       ID de l'espace
     * @param int    $userId        ID de l'utilisateur
     * @param array  $allowedRoles  Rôles autorisés
     * @return array|null           Données du membre ou null si non autorisé
     */
    public static function checkSpaceAccess(int $spaceId, int $userId, array $allowedRoles = ['admin', 'manager', 'member', 'guest']): ?array
    {
        $memberModel = new SpaceMember();
        $member = $memberModel->findMember($spaceId, $userId);

        if (!$member) {
            return null;
        }

        if (!in_array($member['role'], $allowedRoles, true)) {
            return null;
        }

        return $member;
    }

    /**
     * Vérifie si l'utilisateur est un super admin global.
     */
    public static function isSuperAdmin(): bool
    {
        $role = Session::get('global_role');
        return $role === 'superadmin';
    }

    /**
     * Vérifie si l'utilisateur possède un rôle global d'administration.
     */
    public static function isGlobalStaff(): bool
    {
        $role = Session::get('global_role');
        return in_array($role, ['superadmin', 'admin', 'moderator'], true);
    }
}
