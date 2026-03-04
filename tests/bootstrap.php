<?php

/**
 * Bootstrap PHPUnit : charge l'autoloader Composer
 * et prépare un environnement minimal pour les tests.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Variables d'environnement pour les tests (base de données non requise pour les tests unitaires)
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=scores_test');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');
