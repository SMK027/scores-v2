<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\CSRF;
use App\Models\User;

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
        $this->render('auth/register', ['title' => 'Inscription']);
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
        } elseif (strlen($data['password']) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
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
}
