<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Model;
use App\Config\Database;
use PDO;
use PDOStatement;

/**
 * Tests de la classe abstraite Model via un stub concret.
 */
class ModelTest extends TestCase
{
    private PDO $pdo;
    private ConcreteModelStub $model;

    protected function setUp(): void
    {
        // Créer une base SQLite en mémoire pour les tests
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('CREATE TABLE items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            status TEXT DEFAULT "active",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->model = new ConcreteModelStub($this->pdo);
    }

    // ─── find ────────────────────────────────────────────────

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->model->find(999));
    }

    public function testFindReturnsRecord(): void
    {
        $id = $this->model->create(['name' => 'Test Item', 'status' => 'active']);
        $item = $this->model->find($id);

        $this->assertNotNull($item);
        $this->assertSame('Test Item', $item['name']);
        $this->assertSame('active', $item['status']);
    }

    // ─── findAll ─────────────────────────────────────────────

    public function testFindAllReturnsEmptyArrayWhenNoRecords(): void
    {
        $this->assertSame([], $this->model->findAll());
    }

    public function testFindAllReturnsAllRecords(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'active']);
        $this->model->create(['name' => 'C', 'status' => 'inactive']);

        $all = $this->model->findAll();
        $this->assertCount(3, $all);
    }

    public function testFindAllOrdersCorrectly(): void
    {
        $this->model->create(['name' => 'B', 'status' => 'active']);
        $this->model->create(['name' => 'A', 'status' => 'active']);

        $asc = $this->model->findAll('name', 'ASC');
        $this->assertSame('A', $asc[0]['name']);
        $this->assertSame('B', $asc[1]['name']);

        $desc = $this->model->findAll('name', 'DESC');
        $this->assertSame('B', $desc[0]['name']);
        $this->assertSame('A', $desc[1]['name']);
    }

    public function testFindAllSanitizesDirection(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        // Direction invalide → doit retomber en ASC
        $result = $this->model->findAll('name', 'INVALID');
        $this->assertCount(1, $result);
    }

    // ─── findBy ──────────────────────────────────────────────

    public function testFindByReturnsMatchingRecords(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'inactive']);
        $this->model->create(['name' => 'C', 'status' => 'active']);

        $active = $this->model->findBy(['status' => 'active']);
        $this->assertCount(2, $active);
    }

    public function testFindByReturnsEmptyWhenNoMatch(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $result = $this->model->findBy(['status' => 'deleted']);
        $this->assertSame([], $result);
    }

    // ─── findOneBy ───────────────────────────────────────────

    public function testFindOneByReturnsFirstMatch(): void
    {
        $this->model->create(['name' => 'Alice', 'status' => 'active']);
        $this->model->create(['name' => 'Bob', 'status' => 'active']);

        $result = $this->model->findOneBy(['name' => 'Alice']);
        $this->assertNotNull($result);
        $this->assertSame('Alice', $result['name']);
    }

    public function testFindOneByReturnsNullWhenNoMatch(): void
    {
        $result = $this->model->findOneBy(['name' => 'Nobody']);
        $this->assertNull($result);
    }

    // ─── create ──────────────────────────────────────────────

    public function testCreateReturnsId(): void
    {
        $id = $this->model->create(['name' => 'New', 'status' => 'active']);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateInsertsRecord(): void
    {
        $id = $this->model->create(['name' => 'Created', 'status' => 'pending']);
        $item = $this->model->find($id);

        $this->assertSame('Created', $item['name']);
        $this->assertSame('pending', $item['status']);
    }

    public function testCreateAutoIncrements(): void
    {
        $id1 = $this->model->create(['name' => 'First', 'status' => 'active']);
        $id2 = $this->model->create(['name' => 'Second', 'status' => 'active']);
        $this->assertSame($id1 + 1, $id2);
    }

    // ─── update ──────────────────────────────────────────────

    public function testUpdateModifiesRecord(): void
    {
        $id = $this->model->create(['name' => 'Original', 'status' => 'active']);
        $result = $this->model->update($id, ['name' => 'Updated']);

        $this->assertTrue($result);
        $item = $this->model->find($id);
        $this->assertSame('Updated', $item['name']);
        $this->assertSame('active', $item['status']); // Non modifié
    }

    public function testUpdateMultipleFields(): void
    {
        $id = $this->model->create(['name' => 'Original', 'status' => 'active']);
        $this->model->update($id, ['name' => 'New Name', 'status' => 'inactive']);

        $item = $this->model->find($id);
        $this->assertSame('New Name', $item['name']);
        $this->assertSame('inactive', $item['status']);
    }

    // ─── delete ──────────────────────────────────────────────

    public function testDeleteRemovesRecord(): void
    {
        $id = $this->model->create(['name' => 'To Delete', 'status' => 'active']);
        $result = $this->model->delete($id);

        $this->assertTrue($result);
        $this->assertNull($this->model->find($id));
    }

    public function testDeleteNonExistentRecord(): void
    {
        // Ne doit pas lever d'exception
        $result = $this->model->delete(999);
        $this->assertTrue($result); // execute() retourne true même si 0 lignes affectées
    }

    // ─── count ───────────────────────────────────────────────

    public function testCountAll(): void
    {
        $this->assertSame(0, $this->model->count());

        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'active']);
        $this->assertSame(2, $this->model->count());
    }

    public function testCountWithCriteria(): void
    {
        $this->model->create(['name' => 'A', 'status' => 'active']);
        $this->model->create(['name' => 'B', 'status' => 'inactive']);
        $this->model->create(['name' => 'C', 'status' => 'active']);

        $this->assertSame(2, $this->model->count(['status' => 'active']));
        $this->assertSame(1, $this->model->count(['status' => 'inactive']));
        $this->assertSame(0, $this->model->count(['status' => 'deleted']));
    }
}

/**
 * Implémentation concrète de Model pour les tests.
 * Utilise une PDO injectée au lieu du singleton Database.
 */
class ConcreteModelStub extends Model
{
    protected string $table = 'items';

    public function __construct(PDO $pdo)
    {
        // Contourner le constructeur parent qui utilise Database::getInstance()
        $this->db = $pdo;
    }
}
