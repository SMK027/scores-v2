<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\User;

/**
 * Contrôleur de profil utilisateur.
 */
class ProfileController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Affiche le profil de l'utilisateur connecté.
     */
    public function show(): void
    {
        $this->requireAuth();

        $user = $this->userModel->find($this->getCurrentUserId());
        if (!$user) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/');
        }

        $this->render('profile/show', [
            'title' => 'Mon profil',
            'user'  => $user,
        ]);
    }

    /**
     * Formulaire de modification du profil.
     */
    public function editForm(): void
    {
        $this->requireAuth();

        $user = $this->userModel->find($this->getCurrentUserId());
        $this->render('profile/edit', [
            'title' => 'Modifier mon profil',
            'user'  => $user,
        ]);
    }

    /**
     * Traite la modification du profil.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data = $this->getPostData(['username', 'email', 'bio', 'current_password', 'new_password', 'new_password_confirm']);

        $errors = [];

        // Validation username
        if (empty($data['username'])) {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } elseif (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
            $errors[] = 'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères.';
        } else {
            $existing = $this->userModel->findByUsername($data['username']);
            if ($existing && $existing['id'] !== $userId) {
                $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
            }
        }

        // Validation email
        if (empty($data['email'])) {
            $errors[] = 'L\'email est requis.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        } else {
            $existing = $this->userModel->findByEmail($data['email']);
            if ($existing && $existing['id'] !== $userId) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }
        }

        // Gestion de l'avatar
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5 Mo

            if (!in_array($file['type'], $allowedTypes, true)) {
                $errors[] = 'Le format d\'image n\'est pas supporté (JPG, PNG, GIF, WebP).';
            }
            if ($file['size'] > $maxSize) {
                $errors[] = 'L\'image ne doit pas dépasser 5 Mo.';
            }

            if (empty($errors)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                
                // Utiliser le chemin absolu depuis la racine du projet
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
                
                // Créer le dossier si nécessaire et forcer les permissions
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                @chmod($uploadDir, 0777);

                $targetPath = $uploadDir . $filename;
                
                // Tenter le move avec suppression d'erreur pour voir si ça passe
                $moved = @move_uploaded_file($file['tmp_name'], $targetPath);
                
                if ($moved) {
                    @chmod($targetPath, 0644);
                    $avatarPath = '/uploads/' . $filename;
                } else {
                    // Si ça échoue toujours, essayer avec copy + unlink
                    if (@copy($file['tmp_name'], $targetPath)) {
                        @unlink($file['tmp_name']);
                        @chmod($targetPath, 0644);
                        $avatarPath = '/uploads/' . $filename;
                    } else {
                        $errors[] = 'Erreur lors du téléchargement de l\'avatar.';
                    }
                }
            }
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Erreur d'upload
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
                UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale du formulaire.',
                UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement téléchargé.',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'enregistrer le fichier sur le disque.',
                UPLOAD_ERR_EXTENSION  => 'Une extension PHP a bloqué l\'upload.',
            ];
            $errors[] = $uploadErrors[$_FILES['avatar']['error']] ?? 'Erreur inconnue lors de l\'upload.';
        }

        // Changement de mot de passe
        if (!empty($data['new_password'])) {
            if (empty($data['current_password'])) {
                $errors[] = 'Le mot de passe actuel est requis pour changer de mot de passe.';
            } else {
                $user = $this->userModel->find($userId);
                if (!password_verify($data['current_password'], $user['password_hash'])) {
                    $errors[] = 'Le mot de passe actuel est incorrect.';
                }
            }

            if (strlen($data['new_password']) < 8) {
                $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
            }
            if ($data['new_password'] !== $data['new_password_confirm']) {
                $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect('/profile/edit');
        }

        // Mise à jour du profil
        $updateData = [
            'username' => $data['username'],
            'email'    => $data['email'],
            'bio'      => $data['bio'],
        ];

        if ($avatarPath) {
            $updateData['avatar'] = $avatarPath;
        }

        $this->userModel->updateProfile($userId, $updateData);

        // Mise à jour du mot de passe si demandé
        if (!empty($data['new_password'])) {
            $this->userModel->updatePassword($userId, $data['new_password']);
        }

        // Mettre à jour la session
        Session::set('username', $data['username']);

        $this->setFlash('success', 'Profil mis à jour avec succès.');
        $this->redirect('/profile');
    }
}
