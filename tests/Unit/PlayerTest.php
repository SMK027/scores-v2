<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Player;
use PDO;

/**
 * Tests du modèle Player.
 */
class PlayerTest extends TestCase
{
    private PDO $pdo;
    private PlayerStub $player;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('CREATE TABLE players (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            space_id INTEGER NOT NULL,
            user_id INTEGER,
            name TEXT NOT NULL,
            deleted_at TEXT
        )');

        $this->player = new PlayerStub($this->pdo);
    }

    // ─── isUserLinkedInSpace ─────────────────────────────────

    public function testIsUserLinkedInSpaceTrue(): void
    {
        $this->player->create(['space_id' => 1, 'user_id' => 10, 'name' => 'Alice']);

        $this->assertTrue($this->player->isUserLinkedInSpace(1, 10));
    }

    public function testIsUserLinkedInSpaceFalse(): void
    {
        $this->assertFalse($this->player->isUserLinkedInSpace(1, 99));
    }

    public function testIsUserLinkedInSpaceIgnoresDeletedPlayers(): void
    {
        $this->player->create([
            'space_id' => 1,
            'user_id' => 10,
            'name' => 'Alice',
            'deleted_at' => '2024-01-01 00:00:00',
        ]);

        $this->assertFalse($this->player->isUserLinkedInSpace(1, 10));
    }

    public function testIsUserLinkedInSpaceWithExclusion(): void
    {
        $id = $this->player->create(['space_id' => 1, 'user_id' => 10, 'name' => 'Alice']);

        // Exclut le joueur lui-même → pas de doublon
        $this->assertFalse($this->player->isUserLinkedInSpace(1, 10, $id));
    }

    public function testIsUserLinkedInSpaceDifferentSpace(): void
    {
        $this->player->create(['space_id' => 2, 'user_id' => 10, 'name' => 'Alice']);

        $this->assertFalse($this->player->isUserLinkedInSpace(1, 10));
    }

    // ─── getLinkedUserIds ────────────────────────────────────

    public function testGetLinkedUserIds(): void
    {
        $this->player->create(['space_id' => 1, 'user_id' => 10, 'name' => 'Alice']);
        $this->player->create(['space_id' => 1, 'user_id' => 20, 'name' => 'Bob']);
        $this->player->create(['space_id' => 1, 'user_id' => null, 'name' => 'Invité']);

        $ids = array_map('intval', $this->player->getLinkedUserIds(1));
        $this->assertCount(2, $ids);
        $this->assertContains(10, $ids);
        $this->assertContains(20, $ids);
    }

    public function testGetLinkedUserIdsIgnoresDeletedAndOtherSpaces(): void
    {
        $this->player->create(['space_id' => 1, 'user_id' => 10, 'name' => 'Alice']);
        $this->player->create(['space_id' => 1, 'user_id' => 20, 'name' => 'Bob', 'deleted_at' => '2024-01-01']);
        $this->player->create(['space_id' => 2, 'user_id' => 30, 'name' => 'Charlie']);

        $ids = array_map('intval', $this->player->getLinkedUserIds(1));
        $this->assertCount(1, $ids);
        $this->assertContains(10, $ids);
    }

    public function testGetLinkedUserIdsWithExclusion(): void
    {
        $id1 = $this->player->create(['space_id' => 1, 'user_id' => 10, 'name' => 'Alice']);
        $this->player->create(['space_id' => 1, 'user_id' => 20, 'name' => 'Bob']);

        $ids = array_map('intval', $this->player->getLinkedUserIds(1, $id1));
        $this->assertCount(1, $ids);
        $this->assertContains(20, $ids);
    }

    // ─── isActiveInSpace ─────────────────────────────────────

    public function testIsActiveInSpaceTrue(): void
    {
        $id = $this->player->create(['space_id' => 1, 'user_id' => null, 'name' => 'Alice']);
        $this->assertTrue($this->player->isActiveInSpace($id, 1));
    }

    public function testIsActiveInSpaceFalseWhenDeleted(): void
    {
        $id = $this->player->create([
            'space_id' => 1,
            'user_id' => null,
            'name' => 'Alice',
            'deleted_at' => '2024-01-01',
        ]);
        $this->assertFalse($this->player->isActiveInSpace($id, 1));
    }

    public function testIsActiveInSpaceFalseWhenWrongSpace(): void
    {
        $id = $this->player->create(['space_id' => 2, 'user_id' => null, 'name' => 'Alice']);
        $this->assertFalse($this->player->isActiveInSpace($id, 1));
    }

    // ─── findActiveByIdInSpace ───────────────────────────────

    public function testFindActiveByIdInSpaceReturnsPlayer(): void
    {
        $id = $this->player->create(['space_id' => 1, 'user_id' => 5, 'name' => 'Alice']);
        $found = $this->player->findActiveByIdInSpace($id, 1);

        $this->assertNotNull($found);
        $this->assertSame('Alice', $found['name']);
    }

    public function testFindActiveByIdInSpaceNullWhenDeleted(): void
    {
        $id = $this->player->create([
            'space_id' => 1,
            'user_id' => 5,
            'name' => 'Alice',
            'deleted_at' => '2024-01-01',
        ]);
        $this->assertNull($this->player->findActiveByIdInSpace($id, 1));
    }

    public function testFindActiveByIdInSpaceNullWhenWrongSpace(): void
    {
        $id = $this->player->create(['space_id' => 2, 'user_id' => 5, 'name' => 'Alice']);
        $this->assertNull($this->player->findActiveByIdInSpace($id, 1));
    }

    // ─── findByUserInSpace ───────────────────────────────────

    public function testFindByUserInSpace(): void
    {
        $this->player->create(['space_id' => 1, 'user_id' => 10, 'name' => 'Alice']);

        $found = $this->player->findByUserInSpace(1, 10);
        $this->assertNotNull($found);
        $this->assertSame('Alice', $found['name']);
    }

    public function testFindByUserInSpaceNullWhenNotFound(): void
    {
        $this->assertNull($this->player->findByUserInSpace(1, 99));
    }

    public function testFindByUserInSpaceIgnoresDeleted(): void
    {
        $this->player->create([
            'space_id' => 1,
            'user_id' => 10,
            'name' => 'Alice',
            'deleted_at' => '2024-01-01',
        ]);
        $this->assertNull($this->player->findByUserInSpace(1, 10));
    }

    // ─── restore ─────────────────────────────────────────────

    public function testRestoreDeletedPlayer(): void
    {
        $id = $this->player->create([
            'space_id' => 1,
            'user_id' => null,
            'name' => 'Alice',
            'deleted_at' => '2024-01-01 00:00:00',
        ]);

        $this->assertTrue($this->player->restore($id));

        $found = $this->player->find($id);
        $this->assertNull($found['deleted_at']);
    }

    public function testRestoreActivePlayerReturnsFalse(): void
    {
        $id = $this->player->create(['space_id' => 1, 'user_id' => null, 'name' => 'Alice']);
        $this->assertFalse($this->player->restore($id));
    }

    // ─── findDeletedById ─────────────────────────────────────

    public function testFindDeletedByIdReturnsDeletedPlayer(): void
    {
        $id = $this->player->create([
            'space_id' => 1,
            'user_id' => null,
            'name' => 'Alice',
            'deleted_at' => '2024-01-01 00:00:00',
        ]);

        $found = $this->player->findDeletedById($id);
        $this->assertNotNull($found);
        $this->assertSame('Alice', $found['name']);
    }

    public function testFindDeletedByIdNullForActivePlayer(): void
    {
        $id = $this->player->create(['space_id' => 1, 'user_id' => null, 'name' => 'Alice']);
        $this->assertNull($this->player->findDeletedById($id));
    }
}

/**
 * Stub Player avec injection PDO.
 */
class PlayerStub extends Player
{
    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }
}
