<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\User;
use App\Models\PasswordPolicy;
use App\Models\ActivityLog;

/**
 * API REST pour le profil utilisateur.
 */
class ProfileApiController extends ApiController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * GET /api/profile
     */
    public function show(): void
    {
        $this->requireAuth();

        $user = $this->userModel->find($this->userId);
        if (!$user) {
            $this->error('Utilisateur introuvable.', 404);
        }

        $this->json([
            'success' => true,
            'user'    => [
                'id'          => (int) $user['id'],
                'username'    => $user['username'],
                'email'       => $user['email'],
                'bio'         => $user['bio'] ?? '',
                'avatar'      => $user['avatar'] ?? null,
                'global_role' => $user['global_role'],
                'created_at'  => $user['created_at'],
            ],
        ]);
    }

    /**
     * PUT /api/profile
     * Body: { username?, email?, bio? }
     */
    public function update(): void
    {
        $this->requireAuth();

        $data = $this->getJsonBody();
        $user = $this->userModel->find($this->userId);

        $updateData = [];
        $errors = [];

        // Username
        if (isset($data['username'])) {
            $username = trim($data['username']);
            if (strlen($username) < 3 || strlen($username) > 50) {
                $errors[] = 'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères.';
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
                $errors[] = 'Caractères invalides dans le nom d\'utilisateur.';
            } elseif ($username !== $user['username'] && $this->userModel->findByUsername($username)) {
                $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
            } else {
                $updateData['username'] = $username;
            }
        }

        // Email
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse email invalide.';
            } else {
                $existing = $this->userModel->findByEmail($email);
                if ($existing && (int) $existing['id'] !== (int) $user['id']) {
                    $errors[] = 'Cette adresse email est déjà utilisée.';
                } else {
                    $updateData['email'] = $email;
                }
            }
        }

        // Bio
        if (isset($data['bio'])) {
            $updateData['bio'] = trim($data['bio']);
        }

        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        if (!empty($updateData)) {
            $this->userModel->updateProfile($this->userId, $updateData);
        }

        $updatedUser = $this->userModel->find($this->userId);
        $this->json([
            'success' => true,
            'user'    => [
                'id'          => (int) $updatedUser['id'],
                'username'    => $updatedUser['username'],
                'email'       => $updatedUser['email'],
                'bio'         => $updatedUser['bio'] ?? '',
                'avatar'      => $updatedUser['avatar'] ?? null,
                'global_role' => $updatedUser['global_role'],
                'created_at'  => $updatedUser['created_at'],
            ],
        ]);
    }

    /**
     * PUT /api/profile/password
     * Body: { current_password, new_password, new_password_confirm }
     */
    public function updatePassword(): void
    {
        $this->requireAuth();

        $data = $this->getJsonBody();
        $current = $data['current_password'] ?? '';
        $newPass = $data['new_password'] ?? '';
        $confirm = $data['new_password_confirm'] ?? '';

        if (empty($current) || empty($newPass)) {
            $this->error('Tous les champs sont requis.');
        }

        // Vérifier le mot de passe actuel
        $user = $this->userModel->find($this->userId);
        $fullUser = $this->userModel->findOneBy(['id' => $this->userId]);
        if (!password_verify($current, $fullUser['password_hash'])) {
            $this->error('Mot de passe actuel incorrect.', 401);
        }

        $errors = [];
        $policyModel = new PasswordPolicy();
        $policyErrors = $policyModel->validate($newPass);
        $errors = array_merge($errors, $policyErrors);

        if ($newPass !== $confirm) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $this->userModel->updatePassword($this->userId, $newPass);

        ActivityLog::logAuth('password.change.api', $this->userId);

        $this->json(['success' => true, 'message' => 'Mot de passe mis à jour.']);
    }
}
