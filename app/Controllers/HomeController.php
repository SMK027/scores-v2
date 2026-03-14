<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * Contrôleur de la page d'accueil.
 */
class HomeController extends Controller
{
    /**
     * Page d'accueil.
     */
    public function index(): void
    {
        if (is_authenticated()) {
            $this->redirect('/spaces');
        }

        $this->render('home/index', [
            'title' => 'Accueil',
        ]);
    }

    public function legal(): void
    {
        $this->render('home/legal', [
            'title' => 'Conditions Générales d\'Utilisation',
        ]);
    }
}
