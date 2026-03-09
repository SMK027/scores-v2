<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\SpaceMember;
use PDO;

/**
 * Tests du modèle SpaceMember.
 */
class SpaceMemberTest extends TestCase
{
    private PDO $pdo;
    private SpaceMemberStub $member;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('CREATE TABLE space_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            space_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role TEXT DEFAULT "member",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->member = new SpaceMemberStub($this->pdo);
    }

    // ─── addMember ───────────────────────────────────────────

    public function testAddMemberCreatesRecord(): void
    {
        $id = $this->member->addMember(1, 10, 'member');
        $this->assertGreaterThan(0, $id);

        $found = $this->member->find($id);
        $this->assertEquals(1, $found['space_id']);
        $this->assertEquals(10, $found['user_id']);
        $this->assertSame('member', $found['role']);
    }

    public function testAddMemberDefaultRole(): void
    {
        $id = $this->member->addMember(1, 20);
        $found = $this->member->find($id);
        $this->assertSame('member', $found['role']);
    }

    public function testAddMemberWithAdminRole(): void
    {
        $id = $this->member->addMember(1, 30, 'admin');
        $found = $this->member->find($id);
        $this->assertSame('admin', $found['role']);
    }

    // ─── findMember ──────────────────────────────────────────

    public function testFindMemberExists(): void
    {
        $this->member->addMember(1, 10, 'member');
        $found = $this->member->findMember(1, 10);
        $this->assertNotNull($found);
        $this->assertEquals(1, $found['space_id']);
        $this->assertEquals(10, $found['user_id']);
    }

    public function testFindMemberNotFound(): void
    {
        $this->assertNull($this->member->findMember(1, 999));
    }

    // ─── isMember ────────────────────────────────────────────

    public function testIsMemberTrue(): void
    {
        $this->member->addMember(1, 10);
        $this->assertTrue($this->member->isMember(1, 10));
    }

    public function testIsMemberFalse(): void
    {
        $this->assertFalse($this->member->isMember(1, 999));
    }

    // ─── updateRole ──────────────────────────────────────────

    public function testUpdateRoleWithValidRole(): void
    {
        $id = $this->member->addMember(1, 10, 'member');
        $result = $this->member->updateRole($id, 'admin');

        $this->assertTrue($result);
        $found = $this->member->find($id);
        $this->assertSame('admin', $found['role']);
    }

    public function testUpdateRoleWithInvalidRole(): void
    {
        $id = $this->member->addMember(1, 10, 'member');
        $result = $this->member->updateRole($id, 'superadmin');

        $this->assertFalse($result);
        // Le rôle ne doit pas avoir changé
        $found = $this->member->find($id);
        $this->assertSame('member', $found['role']);
    }

    public function testUpdateRoleAllValidRoles(): void
    {
        $id = $this->member->addMember(1, 10);
        foreach (['admin', 'manager', 'member', 'guest'] as $role) {
            $this->assertTrue($this->member->updateRole($id, $role));
            $found = $this->member->find($id);
            $this->assertSame($role, $found['role']);
        }
    }
}

/**
 * Stub SpaceMember avec injection PDO.
 */
class SpaceMemberStub extends SpaceMember
{
    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }
}
