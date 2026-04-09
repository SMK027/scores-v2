<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\GameType;
use PDO;

/**
 * Tests du modèle GameType.
 */
class GameTypeTest extends TestCase
{
    private PDO $pdo;
    private GameTypeStub $gameType;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('CREATE TABLE game_types (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            space_id INTEGER,
            name TEXT NOT NULL,
            win_condition TEXT NOT NULL DEFAULT "highest_score",
            is_global INTEGER NOT NULL DEFAULT 0,
            interactive_mode TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->pdo->exec('CREATE TABLE games (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_type_id INTEGER NOT NULL,
            space_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            status TEXT DEFAULT "in_progress"
        )');

        $this->gameType = new GameTypeStub($this->pdo);
    }

    // ─── isAccessibleInSpace ─────────────────────────────────

    public function testIsAccessibleInSpaceForOwnType(): void
    {
        $id = $this->gameType->create([
            'space_id' => 1,
            'name' => 'Belote',
            'win_condition' => 'highest_score',
            'is_global' => 0,
        ]);

        $this->assertTrue($this->gameType->isAccessibleInSpace($id, 1));
    }

    public function testIsAccessibleInSpaceForGlobalType(): void
    {
        $id = $this->gameType->create([
            'space_id' => null,
            'name' => 'Morpion',
            'win_condition' => 'win_loss',
            'is_global' => 1,
        ]);

        $this->assertTrue($this->gameType->isAccessibleInSpace($id, 1));
        $this->assertTrue($this->gameType->isAccessibleInSpace($id, 999));
    }

    public function testIsNotAccessibleInOtherSpace(): void
    {
        $id = $this->gameType->create([
            'space_id' => 2,
            'name' => 'Poker',
            'win_condition' => 'highest_score',
            'is_global' => 0,
        ]);

        $this->assertFalse($this->gameType->isAccessibleInSpace($id, 1));
    }

    public function testIsNotAccessibleForNonExistentType(): void
    {
        $this->assertFalse($this->gameType->isAccessibleInSpace(999, 1));
    }

    // ─── replaceWithGlobal ───────────────────────────────────

    public function testReplaceWithGlobalUpdatesGames(): void
    {
        $localId = $this->gameType->create([
            'space_id' => 1,
            'name' => 'Belote locale',
            'win_condition' => 'highest_score',
            'is_global' => 0,
        ]);

        $globalId = $this->gameType->create([
            'space_id' => null,
            'name' => 'Belote globale',
            'win_condition' => 'highest_score',
            'is_global' => 1,
        ]);

        // Créer des parties liées au type local
        $this->pdo->exec("INSERT INTO games (game_type_id, space_id, name) VALUES ({$localId}, 1, 'Partie 1')");
        $this->pdo->exec("INSERT INTO games (game_type_id, space_id, name) VALUES ({$localId}, 1, 'Partie 2')");
        $this->pdo->exec("INSERT INTO games (game_type_id, space_id, name) VALUES ({$globalId}, 1, 'Partie 3')");

        $updatedCount = $this->gameType->replaceWithGlobal($localId, $globalId);

        $this->assertSame(2, $updatedCount);

        // Le type local doit être supprimé
        $this->assertNull($this->gameType->find($localId));

        // Les parties doivent pointer vers le type global
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM games WHERE game_type_id = {$globalId}");
        $this->assertSame(3, (int) $stmt->fetchColumn());
    }

    public function testReplaceWithGlobalNoGames(): void
    {
        $localId = $this->gameType->create([
            'space_id' => 1,
            'name' => 'Vide',
            'win_condition' => 'highest_score',
            'is_global' => 0,
        ]);

        $globalId = $this->gameType->create([
            'space_id' => null,
            'name' => 'Global',
            'win_condition' => 'highest_score',
            'is_global' => 1,
        ]);

        $updatedCount = $this->gameType->replaceWithGlobal($localId, $globalId);

        $this->assertSame(0, $updatedCount);
        $this->assertNull($this->gameType->find($localId));
    }

    // ─── CRUD basique (hérité de Model) ─────────────────────

    public function testCreateAndFind(): void
    {
        $id = $this->gameType->create([
            'space_id' => 1,
            'name' => 'Tarot',
            'win_condition' => 'highest_score',
            'is_global' => 0,
        ]);

        $found = $this->gameType->find($id);
        $this->assertSame('Tarot', $found['name']);
        $this->assertSame('highest_score', $found['win_condition']);
    }

    public function testFindAllByGlobal(): void
    {
        $this->gameType->create(['space_id' => 1, 'name' => 'Local1', 'win_condition' => 'highest_score', 'is_global' => 0]);
        $this->gameType->create(['space_id' => null, 'name' => 'Global1', 'win_condition' => 'win_loss', 'is_global' => 1]);
        $this->gameType->create(['space_id' => null, 'name' => 'Global2', 'win_condition' => 'lowest_score', 'is_global' => 1]);

        $globals = $this->gameType->findBy(['is_global' => 1]);
        $this->assertCount(2, $globals);
    }

    public function testInteractiveMode(): void
    {
        $id = $this->gameType->create([
            'space_id' => null,
            'name' => 'Morpion',
            'win_condition' => 'win_loss',
            'is_global' => 1,
            'interactive_mode' => 'morpion',
        ]);

        $found = $this->gameType->find($id);
        $this->assertSame('morpion', $found['interactive_mode']);
    }

    public function testDeleteGameType(): void
    {
        $id = $this->gameType->create([
            'space_id' => 1,
            'name' => 'Temp',
            'win_condition' => 'highest_score',
            'is_global' => 0,
        ]);

        $this->assertTrue($this->gameType->delete($id));
        $this->assertNull($this->gameType->find($id));
    }
}

/**
 * Stub GameType avec injection PDO.
 */
class GameTypeStub extends GameType
{
    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }
}
