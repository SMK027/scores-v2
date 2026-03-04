<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * Singleton de connexion à la base de données.
 * Garantit qu'une seule instance de connexion PDO est utilisée dans toute l'application.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    /**
     * Constructeur privé pour empêcher l'instanciation directe.
     */
    private function __construct()
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'scores_db';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (getenv('APP_DEBUG') === 'true') {
                throw new PDOException("Connexion échouée : " . $e->getMessage());
            }
            throw new PDOException("Erreur de connexion à la base de données.");
        }
    }

    /**
     * Retourne l'instance unique de Database (Singleton).
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne la connexion PDO.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Réinitialise l'instance (utile pour les tests).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    // Empêcher le clonage
    private function __clone()
    {
    }

    // Empêcher la désérialisation
    public function __wakeup()
    {
        throw new \Exception("Impossible de désérialiser un singleton.");
    }
}
