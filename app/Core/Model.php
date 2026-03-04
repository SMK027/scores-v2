<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use PDO;
use PDOStatement;

/**
 * Modèle de base.
 * Fournit les méthodes communes d'accès à la base de données.
 * Toutes les requêtes utilisent des requêtes préparées pour prévenir les injections SQL.
 */
abstract class Model
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Trouve un enregistrement par son ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retourne tous les enregistrements.
     */
    public function findAll(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction}");
        return $stmt->fetchAll();
    }

    /**
     * Trouve des enregistrements par critères.
     */
    public function findBy(array $criteria, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $conditions = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $conditions[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy} {$direction}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Trouve un seul enregistrement par critères.
     */
    public function findOneBy(array $criteria): ?array
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $column => $value) {
            $conditions[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} LIMIT 1"
        );
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Crée un nouvel enregistrement.
     *
     * @return int L'ID du nouvel enregistrement
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un enregistrement par ID.
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $setClause = implode(', ', $sets);
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = :id"
        );
        return $stmt->execute($params);
    }

    /**
     * Supprime un enregistrement par ID.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Compte le nombre d'enregistrements correspondant aux critères.
     */
    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
            return (int) $stmt->fetchColumn();
        }

        $conditions = [];
        $params = [];
        foreach ($criteria as $column => $value) {
            $conditions[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Exécute une requête préparée personnalisée.
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
