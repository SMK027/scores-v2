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
     * Les membres du staff global peuvent contourner les restrictions.
     *
     * @param int    $spaceId       ID de l'espace
     * @param int    $userId        ID de l'utilisateur
     * @param array  $allowedRoles  Rôles autorisés
     * @return array|null           Données du membre ou null si non autorisé
     */
    public static function checkSpaceAccess(int $spaceId, int $userId, array $allowedRoles = ['admin', 'manager', 'member', 'guest']): ?array
    {
        $globalRole = Session::get('global_role');
        
        // Super admin : accès complet (simule un admin d'espace)
        if ($globalRole === 'superadmin') {
            return [
                'id' => 0,
                'space_id' => $spaceId,
                'user_id' => $userId,
                'role' => 'admin',
                'created_at' => date('Y-m-d H:i:s'),
                'is_global_staff' => true,
            ];
        }
        
        // Admin global : accès gestionnaire (sauf paramètres espace)
        if ($globalRole === 'admin') {
            // Si les rôles demandés incluent manager ou moins, autoriser
            if (array_intersect(['manager', 'member', 'guest'], $allowedRoles)) {
                return [
                    'id' => 0,
                    'space_id' => $spaceId,
                    'user_id' => $userId,
                    'role' => 'manager',
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_global_staff' => true,
                ];
            }
        }
        
        // Modérateur global : accès lecture seule (guest)
        if ($globalRole === 'moderator') {
            // Si les rôles demandés incluent guest, autoriser
            if (in_array('guest', $allowedRoles)) {
                return [
                    'id' => 0,
                    'space_id' => $spaceId,
                    'user_id' => $userId,
                    'role' => 'guest',
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_global_staff' => true,
                ];
            }
        }
        
        // Vérification normale pour les utilisateurs standards
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
