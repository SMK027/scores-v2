<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Space;
use PDO;

/**
 * Tests du modèle Space.
 */
class SpaceTest extends TestCase
{
    private PDO $pdo;
    private Space $space;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('CREATE TABLE spaces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            created_by INTEGER,
            restrictions TEXT,
            restriction_reason TEXT,
            restricted_by INTEGER,
            restricted_at TEXT,
            scheduled_deletion_at TEXT,
            deletion_reason TEXT,
            deletion_scheduled_by INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->space = new SpaceStub($this->pdo);
    }

    // ─── RESTRICTION_KEYS ────────────────────────────────────

    public function testRestrictionKeysAreDefined(): void
    {
        $keys = Space::RESTRICTION_KEYS;
        $this->assertArrayHasKey('games', $keys);
        $this->assertArrayHasKey('members', $keys);
        $this->assertArrayHasKey('invites', $keys);
        $this->assertArrayHasKey('competitions', $keys);
        $this->assertArrayHasKey('game_types', $keys);
        $this->assertArrayHasKey('imports', $keys);
        $this->assertArrayHasKey('contact', $keys);
        $this->assertCount(7, $keys);
    }

    // ─── getRestrictions ─────────────────────────────────────

    public function testGetRestrictionsEmptyWhenNoRestrictions(): void
    {
        $id = $this->space->create(['name' => 'Test', 'created_by' => 1]);
        $this->assertSame([], $this->space->getRestrictions($id));
    }

    public function testGetRestrictionsReturnsArray(): void
    {
        $id = $this->space->create([
            'name' => 'Restricted',
            'created_by' => 1,
            'restrictions' => json_encode(['games' => true, 'members' => true]),
        ]);
        $restrictions = $this->space->getRestrictions($id);
        $this->assertSame(['games' => true, 'members' => true], $restrictions);
    }

    public function testGetRestrictionsReturnsEmptyForNonExistentSpace(): void
    {
        $this->assertSame([], $this->space->getRestrictions(999));
    }

    // ─── isRestricted ────────────────────────────────────────

    public function testIsRestrictedReturnsTrueForActiveRestriction(): void
    {
        $id = $this->space->create([
            'name' => 'Test',
            'created_by' => 1,
            'restrictions' => json_encode(['games' => true]),
        ]);
        $this->assertTrue($this->space->isRestricted($id, 'games'));
    }

    public function testIsRestrictedReturnsFalseForInactiveKey(): void
    {
        $id = $this->space->create([
            'name' => 'Test',
            'created_by' => 1,
            'restrictions' => json_encode(['games' => true]),
        ]);
        $this->assertFalse($this->space->isRestricted($id, 'members'));
    }

    public function testIsRestrictedReturnsFalseWhenNoRestrictions(): void
    {
        $id = $this->space->create(['name' => 'Free', 'created_by' => 1]);
        $this->assertFalse($this->space->isRestricted($id, 'games'));
    }

    // ─── hasAnyRestriction ───────────────────────────────────

    public function testHasAnyRestrictionReturnsTrueWhenRestricted(): void
    {
        $id = $this->space->create([
            'name' => 'Test',
            'created_by' => 1,
            'restrictions' => json_encode(['invites' => true]),
        ]);
        $this->assertTrue($this->space->hasAnyRestriction($id));
    }

    public function testHasAnyRestrictionReturnsFalseWhenClean(): void
    {
        $id = $this->space->create(['name' => 'Clean', 'created_by' => 1]);
        $this->assertFalse($this->space->hasAnyRestriction($id));
    }

    // ─── setRestrictions ─────────────────────────────────────

    public function testSetRestrictionsStoresData(): void
    {
        $id = $this->space->create(['name' => 'Test', 'created_by' => 1]);
        $this->space->setRestrictions($id, ['games' => true, 'members' => true], 'Abus détecté', 5);

        $restrictions = $this->space->getRestrictions($id);
        $this->assertTrue($restrictions['games']);
        $this->assertTrue($restrictions['members']);

        $space = $this->space->find($id);
        $this->assertSame('Abus détecté', $space['restriction_reason']);
    }

    public function testSetRestrictionsClearsWhenEmpty(): void
    {
        $id = $this->space->create([
            'name' => 'Test',
            'created_by' => 1,
            'restrictions' => json_encode(['games' => true]),
        ]);
        $this->space->setRestrictions($id, [], null, 5);

        $this->assertSame([], $this->space->getRestrictions($id));
        $space = $this->space->find($id);
        $this->assertNull($space['restriction_reason']);
    }

    // ─── scheduleDeletion / cancelDeletion / isScheduledForDeletion ──

    public function testScheduleDeletion(): void
    {
        $id = $this->space->create(['name' => 'Doomed', 'created_by' => 1]);
        $result = $this->space->scheduleDeletion($id, '2025-12-31 23:59:59', 'Inactivité', 5);

        $this->assertTrue($result);
        $this->assertTrue($this->space->isScheduledForDeletion($id));

        $space = $this->space->find($id);
        $this->assertSame('2025-12-31 23:59:59', $space['scheduled_deletion_at']);
        $this->assertSame('Inactivité', $space['deletion_reason']);
        $this->assertEquals(5, $space['deletion_scheduled_by']);
    }

    public function testCancelDeletion(): void
    {
        $id = $this->space->create(['name' => 'Saved', 'created_by' => 1]);
        $this->space->scheduleDeletion($id, '2025-12-31 23:59:59', 'Test', 5);
        $this->assertTrue($this->space->isScheduledForDeletion($id));

        $result = $this->space->cancelDeletion($id);
        $this->assertTrue($result);
        $this->assertFalse($this->space->isScheduledForDeletion($id));

        $space = $this->space->find($id);
        $this->assertNull($space['scheduled_deletion_at']);
        $this->assertNull($space['deletion_reason']);
    }

    public function testIsScheduledForDeletionReturnsFalseByDefault(): void
    {
        $id = $this->space->create(['name' => 'Safe', 'created_by' => 1]);
        $this->assertFalse($this->space->isScheduledForDeletion($id));
    }

    public function testIsScheduledForDeletionReturnsFalseForNonExistent(): void
    {
        $this->assertFalse($this->space->isScheduledForDeletion(999));
    }

    // ─── findDueForDeletion ──────────────────────────────────

    public function testFindDueForDeletionReturnsPastDates(): void
    {
        $pastDate = (new \DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $futureDate = (new \DateTime('+1 day'))->format('Y-m-d H:i:s');

        $id1 = $this->space->create(['name' => 'Past', 'created_by' => 1, 'scheduled_deletion_at' => $pastDate]);
        $id2 = $this->space->create(['name' => 'Future', 'created_by' => 1, 'scheduled_deletion_at' => $futureDate]);
        $id3 = $this->space->create(['name' => 'None', 'created_by' => 1]);

        $due = $this->space->findDueForDeletion();
        $dueIds = array_column($due, 'id');

        $this->assertContains($id1, $dueIds);
        $this->assertNotContains($id2, $dueIds);
        $this->assertNotContains($id3, $dueIds);
    }
}

/**
 * Stub pour le modèle Space avec injection PDO.
 */
class SpaceStub extends Space
{
    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Override findDueForDeletion pour utiliser le format SQLite (pas de timezone).
     */
    public function findDueForDeletion(): array
    {
        $now = (new \DateTime('now'))->format('Y-m-d H:i:s');
        $stmt = $this->query(
            "SELECT * FROM {$this->table}
             WHERE scheduled_deletion_at IS NOT NULL
               AND scheduled_deletion_at <= :now",
            ['now' => $now]
        );
        return $stmt->fetchAll();
    }
}
