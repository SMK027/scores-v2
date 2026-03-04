<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\Mailer;
use App\Models\User;
use App\Models\PasswordPolicy;
use App\Models\PasswordReset;

/**
 * Contrôleur d'authentification.
 * Gère la connexion, l'inscription et la déconnexion.
 */
class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Affiche le formulaire de connexion.
     */
    public function loginForm(): void
    {
        if (is_authenticated()) {
            $this->redirect('/spaces');
        }
        $this->render('auth/login', ['title' => 'Connexion']);
    }

    /**
     * Traite la connexion.
     */
    public function login(): void
    {
        $this->validateCSRF();

        $data = $this->getPostData(['email', 'password']);

        // Validation
        if (empty($data['email']) || empty($data['password'])) {
            $this->setFlash('danger', 'Veuillez remplir tous les champs.');
            $this->redirect('/login');
        }

        // Authentification
        $user = $this->userModel->authenticate($data['email'], $data['password']);
        if (!$user) {
            $this->setFlash('danger', 'Email ou mot de passe incorrect.');
            $this->redirect('/login');
        }

        // Créer la session
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('global_role', $user['global_role']);

        // Régénérer le token CSRF
        CSRF::regenerate();

        $this->setFlash('success', 'Bienvenue, ' . $user['username'] . ' !');
        $this->redirect('/spaces');
    }

    /**
     * Affiche le formulaire d'inscription.
     */
    public function registerForm(): void
    {
        if (is_authenticated()) {
            $this->redirect('/spaces');
        }

        $policyModel = new PasswordPolicy();
        $this->render('auth/register', [
            'title'         => 'Inscription',
            'policySummary' => $policyModel->getSummary(),
            'policyJson'    => $policyModel->toJson(),
        ]);
    }

    /**
     * Traite l'inscription.
     */
    public function register(): void
    {
        $this->validateCSRF();

        $data = $this->getPostData(['username', 'email', 'password', 'password_confirm']);

        // Validations
        $errors = [];

        if (empty($data['username'])) {
            $errors[] = 'Le nom d\'utilisateur est requis.';
        } elseif (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
            $errors[] = 'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères.';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['username'])) {
            $errors[] = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores.';
        }

        if (empty($data['email'])) {
            $errors[] = 'L\'email est requis.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        }

        if (empty($data['password'])) {
            $errors[] = 'Le mot de passe est requis.';
        } else {
            $policyModel = new PasswordPolicy();
            $policyErrors = $policyModel->validate($data['password']);
            $errors = array_merge($errors, $policyErrors);
        }

        if ($data['password'] !== $data['password_confirm']) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        // Vérifier l'unicité
        if (empty($errors)) {
            if ($this->userModel->findByUsername($data['username'])) {
                $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
            }
            if ($this->userModel->findByEmail($data['email'])) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect('/register');
        }

        // Créer l'utilisateur
        $userId = $this->userModel->register($data['username'], $data['email'], $data['password']);

        // Connecter automatiquement
        $user = $this->userModel->find($userId);
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('global_role', $user['global_role']);
        CSRF::regenerate();

        $this->setFlash('success', 'Inscription réussie ! Bienvenue, ' . $user['username'] . ' !');
        $this->redirect('/spaces');
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void
    {
        Session::destroy();
        // Redémarrer une session propre pour le flash
        Session::start();
        $this->setFlash('success', 'Vous avez été déconnecté.');
        $this->redirect('/login');
    }

    // =========================================================
    // Réinitialisation de mot de passe
    // =========================================================

    /**
     * Affiche le formulaire "Mot de passe oublié".
     */
    public function forgotPasswordForm(): void
    {
        if (is_authenticated()) {
            $this->redirect('/spaces');
        }
        $this->render('auth/forgot_password', ['title' => 'Mot de passe oublié']);
    }

    /**
     * Traite la demande de réinitialisation : envoie un email avec un lien.
     */
    public function forgotPassword(): void
    {
        $this->validateCSRF();

        $data = $this->getPostData(['email']);

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('danger', 'Veuillez saisir une adresse email valide.');
            $this->redirect('/forgot-password');
        }

        // Toujours afficher le même message pour ne pas révéler l'existence du compte
        $successMsg = 'Si un compte existe avec cette adresse, un email de réinitialisation a été envoyé.';

        $user = $this->userModel->findByEmail($data['email']);
        if (!$user) {
            $this->setFlash('success', $successMsg);
            $this->redirect('/forgot-password');
        }

        // Créer le token
        $resetModel = new PasswordReset();
        $token = $resetModel->createToken($user['id']);

        // Construire le lien
        $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
        $resetLink = $appUrl . '/reset-password/' . $token;

        // Envoyer l'email
        try {
            $mailer = new Mailer();
            $subject = 'Réinitialisation de votre mot de passe – Scores';
            $body = $this->buildResetEmail($user['username'], $resetLink);
            $mailer->send($data['email'], $subject, $body);
        } catch (\RuntimeException $e) {
            if (getenv('APP_DEBUG') === 'true') {
                $this->setFlash('danger', 'Erreur d\'envoi de l\'email : ' . $e->getMessage());
                $this->redirect('/forgot-password');
            }
            // En production, on n'expose pas l'erreur
        }

        $this->setFlash('success', $successMsg);
        $this->redirect('/forgot-password');
    }

    /**
     * Affiche le formulaire de réinitialisation de mot de passe (via le lien email).
     */
    public function resetPasswordForm(string $token): void
    {
        if (is_authenticated()) {
            $this->redirect('/spaces');
        }

        $resetModel = new PasswordReset();
        $resetData = $resetModel->findValidToken($token);

        if (!$resetData) {
            $this->setFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            $this->redirect('/forgot-password');
        }

        $policyModel = new PasswordPolicy();

        $this->render('auth/reset_password', [
            'title'         => 'Nouveau mot de passe',
            'token'         => $token,
            'policySummary' => $policyModel->getSummary(),
            'policyJson'    => $policyModel->toJson(),
        ]);
    }

    /**
     * Traite la réinitialisation du mot de passe.
     */
    public function resetPassword(string $token): void
    {
        $this->validateCSRF();

        $resetModel = new PasswordReset();
        $resetData = $resetModel->findValidToken($token);

        if (!$resetData) {
            $this->setFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            $this->redirect('/forgot-password');
        }

        $data = $this->getPostData(['password', 'password_confirm']);
        $errors = [];

        if (empty($data['password'])) {
            $errors[] = 'Le mot de passe est requis.';
        } else {
            $policyModel = new PasswordPolicy();
            $policyErrors = $policyModel->validate($data['password']);
            $errors = array_merge($errors, $policyErrors);
        }

        if ($data['password'] !== $data['password_confirm']) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect('/reset-password/' . $token);
        }

        // Mettre à jour le mot de passe
        $this->userModel->updatePassword($resetData['user_id'], $data['password']);

        // Marquer le token comme utilisé
        $resetModel->markUsed($token);

        $this->setFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.');
        $this->redirect('/login');
    }

    /**
     * Construit le contenu HTML de l'email de réinitialisation.
     */
    private function buildResetEmail(string $username, string $resetLink): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
    <div style="background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">🎲 Scores</h1>
        <p style="margin: 8px 0 0; opacity: 0.9;">Réinitialisation de mot de passe</p>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 12px 12px;">
        <p>Bonjour <strong>{$username}</strong>,</p>
        <p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{$resetLink}" style="display: inline-block; background: #4361ee; color: white; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px;">
                Réinitialiser mon mot de passe
            </a>
        </div>
        <p style="color: #666; font-size: 14px;">Ce lien est valable <strong>30 minutes</strong> et ne peut être utilisé qu'une seule fois.</p>
        <p style="color: #666; font-size: 14px;">Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="color: #999; font-size: 12px;">Si le bouton ne fonctionne pas, copiez-collez ce lien :<br><a href="{$resetLink}" style="color: #4361ee; word-break: break-all;">{$resetLink}</a></p>
    </div>
</body>
</html>
HTML;
    }
}
