<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\CSRF;
use App\Core\Mailer;
use App\Models\User;
use App\Models\EmailVerification;
use App\Models\PasswordPolicy;
use App\Models\PasswordReset;
use App\Models\UserBan;
use App\Models\IpBan;
use App\Models\LoginAttempt;
use App\Models\LoginLock;
use App\Models\Fail2banConfig;
use App\Models\ActivityLog;
use App\Models\RememberToken;

/**
 * Contrôleur d'authentification.
 * Gère la connexion, l'inscription et la déconnexion.
 */
class AuthController extends Controller
{
    private const REMEMBER_COOKIE = 'remember_me';
    private const REMEMBER_DAYS = 7;

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
        $clientIp = get_client_ip();

        // Validation
        if (empty($data['email']) || empty($data['password'])) {
            $this->setFlash('danger', 'Veuillez remplir tous les champs.');
            $this->redirect('/login');
        }

        // ── Vérifier verrou fail2ban sur l'IP (bloque uniquement la connexion) ──
        $lockModel = new LoginLock();
        $ipLock = $lockModel->findActiveByIp($clientIp);
        if ($ipLock) {
            $unlockAt = date('d/m/Y à H:i', strtotime($ipLock['locked_until']));
            $this->setFlash('danger', "Connexion temporairement verrouillée pour cette adresse IP jusqu'au {$unlockAt}.");
            $this->redirect('/login');
        }

        // Authentification
        $user = $this->userModel->authenticate($data['email'], $data['password']);
        if (!$user) {
            // Enregistrer la tentative échouée et vérifier fail2ban
            $remainingInfo = $this->recordFailedAttempt($clientIp, $data['email']);

            // Construire le message avec tentatives restantes
            $msg = 'Email ou mot de passe incorrect.';
            if ($remainingInfo !== null) {
                if ($remainingInfo['remaining'] <= 0) {
                    $unlockAt = date('d/m/Y à H:i', strtotime($remainingInfo['locked_until']));
                    $msg = "Trop de tentatives échouées. Connexion verrouillée jusqu'au {$unlockAt}.";
                } else {
                    $msg .= " Il vous reste {$remainingInfo['remaining']} tentative(s) avant le verrouillage.";
                }
            }
            $this->setFlash('danger', $msg);
            $this->redirect('/login');
        }

        $accountStatus = (string) ($user['account_status'] ?? User::ACCOUNT_STATUS_ACTIVE);
        $isAnonymized = !empty($user['is_anonymized']);
        if ($accountStatus !== User::ACCOUNT_STATUS_ACTIVE || $isAnonymized) {
            $this->setFlash('danger', $this->buildAccountAccessDeniedMessage($user));
            $this->redirect('/login');
        }

        // ── Vérifier verrou fail2ban sur le compte (bloque uniquement la connexion) ──
        $userLock = $lockModel->findActiveByUser((int) $user['id']);
        if ($userLock) {
            $unlockAt = date('d/m/Y à H:i', strtotime($userLock['locked_until']));
            $this->setFlash('danger', "Connexion temporairement verrouillée pour ce compte jusqu'au {$unlockAt}.");
            $this->redirect('/login');
        }

        // Vérifier bannissement administratif du compte
        $userBanModel = new UserBan();
        $ban = $userBanModel->findActiveBan((int) $user['id']);
        if ($ban) {
            $msg = 'Votre compte est banni. Raison : ' . $ban['reason'];
            if ($ban['expires_at']) {
                $msg .= ' — Débannissement le ' . date('d/m/Y à H:i', strtotime($ban['expires_at'])) . '.';
            } else {
                $msg .= ' — Bannissement permanent.';
            }
            $this->setFlash('danger', $msg);
            $this->redirect('/login');
        }

        // Vérifier bannissement administratif IP
        $ipBanModel = new IpBan();
        $ipBan = $ipBanModel->findActiveBan($clientIp);
        if ($ipBan) {
            $msg = 'Votre adresse IP est bannie du site. Raison : ' . $ipBan['reason'];
            if ($ipBan['expires_at']) {
                $msg .= ' — Débannissement le ' . date('d/m/Y à H:i', strtotime($ipBan['expires_at'])) . '.';
            } else {
                $msg .= ' — Bannissement permanent.';
            }
            $this->setFlash('danger', $msg);
            $this->redirect('/login');
        }

        // Vérifier si le compte est en attente de vérification email (nouveau compte uniquement)
        if ($user['email_verification_required'] && !$user['email_verified_at']) {
            $verifyModel = new EmailVerification();
            Session::set('pending_verification_user_id', (int) $user['id']);
            if ($verifyModel->countRecentCodes((int) $user['id']) >= 3) {
                $this->setFlash('warning', "Votre adresse email n'est pas encore vérifiée. Vous avez atteint la limite de 3 envois par jour. Réessayez demain ou vérifiez votre boîte de réception (y compris les spams).");
                $this->redirect('/verify-email');
            }
            $code = $verifyModel->generateCode((int) $user['id'], $user['email']);
            try {
                $mailer = new Mailer();
                $mailer->send(
                    $user['email'],
                    'Vérifiez votre adresse email – Scores',
                    $this->buildVerificationEmail($user['username'], $code)
                );
            } catch (\RuntimeException $e) {
                if (getenv('APP_DEBUG') === 'true') {
                    error_log('Email verification send error: ' . $e->getMessage());
                }
            }
            $this->setFlash('info', "Votre adresse email n'est pas encore vérifiée. Un code vous a été envoyé.");
            $this->redirect('/verify-email');
        }

        // Connexion réussie : effacer les tentatives et verrous pour cette IP/compte
        $attemptModel = new LoginAttempt();
        $attemptModel->clearByIp($clientIp);
        if ($user['id']) {
            $attemptModel->clearByUser((int) $user['id']);
        }
        $lockModel->cleanExpired();

        // Créer la session
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('global_role', $user['global_role']);
        Session::set('avatar', $user['avatar'] ?? '');

        // Gérer l'option "Se souvenir de moi" (cookie persistant 7 jours)
        // On révoque d'abord le cookie/token courant sur ce navigateur pour éviter l'accumulation.
        $this->revokeCurrentRememberMeToken();
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
        if ($rememberMe) {
            $this->issueRememberMeToken((int) $user['id']);
        }

        // Régénérer le token CSRF
        CSRF::regenerate();

        ActivityLog::logAuth('login.success', (int) $user['id']);

        $this->setFlash('success', 'Bienvenue, ' . $user['username'] . ' !');
        $this->redirect('/spaces');
    }

    /**
     * Message utilisateur lorsque l'accès au compte est suspendu.
     */
    private function buildAccountAccessDeniedMessage(array $user): string
    {
        $status = (string) ($user['account_status'] ?? User::ACCOUNT_STATUS_ACTIVE);
        if ($status === User::ACCOUNT_STATUS_PENDING_DELETION) {
            $effectiveAt = (string) ($user['deletion_effective_at'] ?? '');
            if ($effectiveAt !== '') {
                $formatted = date('d/m/Y à H:i', strtotime($effectiveAt));
                return "Votre compte est désactivé suite à une demande de suppression. Anonymisation prévue le {$formatted}.";
            }
            return 'Votre compte est désactivé suite à une demande de suppression.';
        }

        return 'Ce compte est définitivement suspendu et anonymisé.';
    }

    /**
     * Enregistre une tentative de connexion échouée et déclenche le fail2ban si nécessaire.
     *
     * @return array|null  null si fail2ban désactivé, sinon ['remaining' => int, 'locked_until' => string|null]
     */
    private function recordFailedAttempt(string $clientIp, ?string $email): ?array
    {
        $f2bConfig = new Fail2banConfig();
        if (!$f2bConfig->isEnabled()) {
            return null;
        }

        $attemptModel = new LoginAttempt();

        // Chercher l'utilisateur par email (pour le verrou de compte)
        $targetUser = null;
        if ($email) {
            $targetUser = $this->userModel->findByEmail($email);
        }

        // Enregistrer la tentative
        $attemptModel->record($clientIp, $email, $targetUser ? (int) $targetUser['id'] : null);

        $maxAttempts   = $f2bConfig->getInt('max_attempts');
        $windowMinutes = $f2bConfig->getInt('window_minutes');
        $banDuration   = $f2bConfig->getInt('ban_duration');
        $banIp         = $f2bConfig->getBool('ban_ip');
        $banAccount    = $f2bConfig->getBool('ban_account');
        $exemptStaff   = $f2bConfig->getBool('exempt_staff');

        $ipAttempts = $attemptModel->countRecentByIp($clientIp, $windowMinutes);
        $remaining  = $maxAttempts - $ipAttempts;

        // Seuil atteint → appliquer les verrous de connexion (pas de ban global)
        if ($remaining <= 0) {
            $lockedUntil = (new \DateTime())->modify("+{$banDuration} minutes")->format('Y-m-d H:i:s');
            $reason = "Fail2ban : {$maxAttempts} tentatives de connexion échouées en {$windowMinutes} min.";

            $lockModel = new LoginLock();

            // Verrou sur l'IP
            if ($banIp) {
                $existingLock = $lockModel->findActiveByIp($clientIp);
                if (!$existingLock) {
                    $lockModel->lockIp($clientIp, $lockedUntil, $reason);
                }
            }

            // Verrou sur le compte (si identifié et non-staff ou staff non-exempté)
            if ($banAccount && $targetUser) {
                $isStaff = in_array($targetUser['global_role'], ['moderator', 'admin', 'superadmin'], true);
                if (!$isStaff || !$exemptStaff) {
                    $existingUserLock = $lockModel->findActiveByUser((int) $targetUser['id']);
                    if (!$existingUserLock) {
                        $lockModel->lockUser((int) $targetUser['id'], $lockedUntil, $reason);
                    }
                }
            }

            // Nettoyer les tentatives après verrouillage
            $attemptModel->clearByIp($clientIp);
            if ($targetUser) {
                $attemptModel->clearByUser((int) $targetUser['id']);
            }

            return ['remaining' => 0, 'locked_until' => $lockedUntil];
        }

        return ['remaining' => $remaining, 'locked_until' => null];
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

        // Créer l'utilisateur (email_verification_required = 1 automatiquement)
        $userId = $this->userModel->register($data['username'], $data['email'], $data['password']);

        // Générer un code de vérification et l'envoyer par email
        $verifyModel = new EmailVerification();
        $code = $verifyModel->generateCode($userId, $data['email']);
        try {
            $mailer = new Mailer();
            $mailer->send(
                $data['email'],
                'Vérifiez votre adresse email – Scores',
                $this->buildVerificationEmail($data['username'], $code)
            );
        } catch (\RuntimeException $e) {
            if (getenv('APP_DEBUG') === 'true') {
                error_log('Email verification send error: ' . $e->getMessage());
            }
        }

        // Stocker l'ID en session pour la page de vérification (sans connecter l'utilisateur)
        Session::set('pending_verification_user_id', $userId);

        ActivityLog::logAuth('register', $userId);

        $this->setFlash('info', 'Compte créé ! Consultez votre boîte email et saisissez le code à 6 chiffres pour activer votre compte.');
        $this->redirect('/verify-email');
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void
    {
        $userId = $this->getCurrentUserId();
        ActivityLog::logAuth('logout', $userId);

        $this->revokeCurrentRememberMeToken();

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

        ActivityLog::logAuth('password.reset', (int) $resetData['user_id']);

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

    // =========================================================
    // Vérification d'adresse email — nouveaux comptes (non connectés)
    // =========================================================

    /**
     * Affiche la page de saisie du code de vérification (utilisateur non connecté).
     */
    public function verifyEmailForm(): void
    {
        if (is_authenticated()) {
            $this->redirect('/spaces');
        }

        $pendingUserId = Session::get('pending_verification_user_id');
        if (!$pendingUserId) {
            $this->redirect('/login');
        }

        $user = $this->userModel->find((int) $pendingUserId);
        if (!$user || !$user['email_verification_required'] || $user['email_verified_at']) {
            Session::remove('pending_verification_user_id');
            $this->redirect('/login');
        }

        $this->render('auth/verify_email', [
            'title' => 'Vérifier votre adresse email',
            'email' => $user['email'],
        ]);
    }

    /**
     * Traite la saisie du code de vérification (utilisateur non connecté).
     */
    public function verifyEmail(): void
    {
        $this->validateCSRF();

        if (is_authenticated()) {
            $this->redirect('/spaces');
        }

        $pendingUserId = Session::get('pending_verification_user_id');
        if (!$pendingUserId) {
            $this->redirect('/login');
        }

        $data = $this->getPostData(['code']);
        $code = preg_replace('/\s+/', '', $data['code']); // supprimer les espaces éventuels

        if (empty($code)) {
            $this->setFlash('danger', 'Veuillez saisir le code reçu par email.');
            $this->redirect('/verify-email');
        }

        $verifyModel = new EmailVerification();
        $token = $verifyModel->findValidCode((int) $pendingUserId, $code);

        if (!$token) {
            $this->setFlash('danger', 'Code invalide ou expiré. Vérifiez le code reçu ou demandez-en un nouveau.');
            $this->redirect('/verify-email');
        }

        // Marquer le token comme utilisé et valider l'email
        $verifyModel->markUsed((int) $token['id']);
        $this->userModel->markEmailVerified((int) $pendingUserId);
        Session::remove('pending_verification_user_id');

        // Connecter l'utilisateur
        $user = $this->userModel->find((int) $pendingUserId);
        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('global_role', $user['global_role']);
        Session::set('avatar', $user['avatar'] ?? '');
        CSRF::regenerate();

        ActivityLog::logAuth('email.verified', (int) $user['id']);

        $this->setFlash('success', 'Adresse email vérifiée. Bienvenue, ' . $user['username'] . ' !');
        $this->redirect('/spaces');
    }

    /**
     * Renvoie un nouveau code de vérification (utilisateur non connecté).
     */
    public function resendVerification(): void
    {
        $this->validateCSRF();

        if (is_authenticated()) {
            $this->redirect('/spaces');
        }

        $pendingUserId = Session::get('pending_verification_user_id');
        if (!$pendingUserId) {
            $this->redirect('/login');
        }

        $user = $this->userModel->find((int) $pendingUserId);
        if (!$user) {
            $this->redirect('/login');
        }

        $verifyModel = new EmailVerification();

        if ($verifyModel->countRecentCodes((int) $user['id']) >= 3) {
            $this->setFlash('danger', 'Vous avez atteint la limite de 3 envois de code par jour. Réessayez demain ou vérifiez votre boîte de réception (y compris les spams).');
            $this->redirect('/verify-email');
        }

        $code = $verifyModel->generateCode((int) $user['id'], $user['email']);

        try {
            $mailer = new Mailer();
            $mailer->send(
                $user['email'],
                'Vérifiez votre adresse email – Scores',
                $this->buildVerificationEmail($user['username'], $code)
            );
            $this->setFlash('success', 'Un nouveau code a été envoyé à ' . $user['email'] . '.');
        } catch (\RuntimeException $e) {
            $this->setFlash('danger', "Erreur lors de l'envoi de l'email. Veuillez réessayer dans quelques instants.");
        }

        $this->redirect('/verify-email');
    }

    // =========================================================
    // Vérification d'adresse email — comptes existants (connectés)
    // =========================================================

    /**
     * Affiche la page de vérification email pour un utilisateur authentifié.
     * Étape 1 si aucun code envoyé, étape 2 si le code a été envoyé.
     */
    public function requestVerifyEmailForm(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $user = $this->userModel->find($userId);

        if ($user['email_verified_at']) {
            $this->setFlash('info', 'Votre adresse email est déjà vérifiée.');
            $this->redirect('/profile');
        }

        $step = Session::get('email_verify_step') ?? 'request';

        $this->render('account/verify_email', [
            'title' => 'Vérifier mon adresse email',
            'email' => $user['email'],
            'step'  => $step,
        ]);
    }

    /**
     * Envoie le code de vérification à l'utilisateur authentifié.
     */
    public function requestVerifyEmail(): void
    {
        $this->validateCSRF();
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $user = $this->userModel->find($userId);

        if ($user['email_verified_at']) {
            $this->redirect('/profile');
        }

        $verifyModel = new EmailVerification();

        if ($verifyModel->countRecentCodes($userId) >= 3) {
            $this->setFlash('danger', 'Vous avez atteint la limite de 3 envois de code par jour. Réessayez demain ou vérifiez votre boîte de réception (y compris les spams).');
            $this->redirect('/account/verify-email');
        }

        $code = $verifyModel->generateCode($userId, $user['email']);

        try {
            $mailer = new Mailer();
            $mailer->send(
                $user['email'],
                'Vérifiez votre adresse email – Scores',
                $this->buildVerificationEmail($user['username'], $code)
            );
            Session::set('email_verify_step', 'verify');
            $this->setFlash('info', 'Un code de vérification a été envoyé à ' . $user['email'] . '.');
        } catch (\RuntimeException $e) {
            $this->setFlash('danger', "Erreur lors de l'envoi de l'email. Réessayez plus tard.");
        }

        $this->redirect('/account/verify-email');
    }

    /**
     * Valide le code de vérification saisi par l'utilisateur authentifié.
     */
    public function confirmVerifyEmail(): void
    {
        $this->validateCSRF();
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $user = $this->userModel->find($userId);

        if ($user['email_verified_at']) {
            $this->redirect('/profile');
        }

        $data = $this->getPostData(['code']);
        $code = preg_replace('/\s+/', '', $data['code']);

        if (empty($code)) {
            $this->setFlash('danger', 'Veuillez saisir le code reçu par email.');
            $this->redirect('/account/verify-email');
        }

        $verifyModel = new EmailVerification();
        $token = $verifyModel->findValidCode($userId, $code);

        if (!$token) {
            $this->setFlash('danger', 'Code invalide ou expiré. Demandez un nouveau code.');
            $this->redirect('/account/verify-email');
        }

        $verifyModel->markUsed((int) $token['id']);
        $this->userModel->markEmailVerified($userId);
        Session::remove('email_verify_step');

        ActivityLog::logAuth('email.verified', $userId);

        $this->setFlash('success', 'Votre adresse email a été vérifiée avec succès. ✓');
        $this->redirect('/profile');
    }

    /**
     * Emet un cookie persistant "Se souvenir de moi" valable 7 jours.
     */
    private function issueRememberMeToken(int $userId): void
    {
        $tokenModel = new RememberToken();
        $tokenModel->purgeExpired();

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expiresAt = (new \DateTimeImmutable('now'))->modify('+' . self::REMEMBER_DAYS . ' days');

        $tokenModel->createToken(
            $userId,
            $selector,
            $tokenHash,
            $expiresAt->format('Y-m-d H:i:s'),
            get_client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        $cookieValue = $selector . ':' . $validator;
        $this->setRememberMeCookie($cookieValue, $expiresAt->getTimestamp());
    }

    /**
     * Supprime le token courant et nettoie le cookie remember_me.
     */
    private function revokeCurrentRememberMeToken(): void
    {
        $cookieValue = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if ($cookieValue !== '') {
            $parts = explode(':', $cookieValue, 2);
            if (count($parts) === 2 && $parts[0] !== '') {
                $tokenModel = new RememberToken();
                $tokenModel->deleteBySelector($parts[0]);
            }
        }

        $this->clearRememberMeCookie();
    }

    /**
     * Dépose le cookie remember_me avec les attributs de sécurité.
     */
    private function setRememberMeCookie(string $value, int $expiresAt): void
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expiresAt,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Efface le cookie remember_me côté navigateur.
     */
    private function clearRememberMeCookie(): void
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Construit le contenu HTML de l'email de vérification.
     */
    private function buildVerificationEmail(string $username, string $code): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
    <div style="background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">🎲 Scores</h1>
        <p style="margin: 8px 0 0; opacity: 0.9;">Vérification de votre adresse email</p>
    </div>
    <div style="background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 12px 12px;">
        <p>Bonjour <strong>{$username}</strong>,</p>
        <p>Voici votre code de vérification à usage unique, valable <strong>15 minutes</strong> :</p>
        <div style="text-align: center; margin: 30px 0;">
            <div style="display: inline-block; background: #f0f4ff; border: 2px solid #4361ee; border-radius: 12px; padding: 20px 40px;">
                <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #4361ee; font-family: monospace;">{$code}</span>
            </div>
        </div>
        <p style="color: #666; font-size: 14px;">Saisissez ce code sur la page de vérification pour activer votre compte.</p>
        <p style="color: #666; font-size: 14px;">Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.</p>
    </div>
</body>
</html>
HTML;
    }
}
