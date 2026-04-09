<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use PDO;

/**
 * Tests du modèle User.
 */
class UserTest extends TestCase
{
    private PDO $pdo;
    private UserStub $user;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            bio TEXT,
            avatar TEXT,
            global_role TEXT DEFAULT "user",
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->user = new UserStub($this->pdo);
    }

    // ─── register ────────────────────────────────────────────

    public function testRegisterCreatesUser(): void
    {
        $id = $this->user->register('alice', 'alice@example.com', 'password123');
        $this->assertGreaterThan(0, $id);

        $found = $this->user->find($id);
        $this->assertSame('alice', $found['username']);
        $this->assertSame('alice@example.com', $found['email']);
        $this->assertSame('user', $found['global_role']);
    }

    public function testRegisterHashesPassword(): void
    {
        $id = $this->user->register('bob', 'bob@example.com', 'secret');
        $found = $this->user->find($id);

        $this->assertNotSame('secret', $found['password_hash']);
        $this->assertTrue(password_verify('secret', $found['password_hash']));
    }

    // ─── authenticate ────────────────────────────────────────

    public function testAuthenticateSuccess(): void
    {
        $this->user->register('alice', 'alice@example.com', 'mypassword');
        $result = $this->user->authenticate('alice@example.com', 'mypassword');

        $this->assertNotNull($result);
        $this->assertSame('alice', $result['username']);
        $this->assertArrayNotHasKey('password_hash', $result);
    }

    public function testAuthenticateWrongPassword(): void
    {
        $this->user->register('alice', 'alice@example.com', 'mypassword');
        $result = $this->user->authenticate('alice@example.com', 'wrong');

        $this->assertNull($result);
    }

    public function testAuthenticateUnknownEmail(): void
    {
        $result = $this->user->authenticate('nobody@example.com', 'password');
        $this->assertNull($result);
    }

    // ─── findByUsername / findByEmail ─────────────────────────

    public function testFindByUsername(): void
    {
        $this->user->register('charlie', 'charlie@example.com', 'pass');
        $found = $this->user->findByUsername('charlie');

        $this->assertNotNull($found);
        $this->assertSame('charlie', $found['username']);
    }

    public function testFindByUsernameNotFound(): void
    {
        $this->assertNull($this->user->findByUsername('nobody'));
    }

    public function testFindByEmail(): void
    {
        $this->user->register('dave', 'dave@example.com', 'pass');
        $found = $this->user->findByEmail('dave@example.com');

        $this->assertNotNull($found);
        $this->assertSame('dave@example.com', $found['email']);
    }

    // ─── updateProfile ───────────────────────────────────────

    public function testUpdateProfileAllowedFields(): void
    {
        $id = $this->user->register('eve', 'eve@example.com', 'pass');
        $result = $this->user->updateProfile($id, [
            'username' => 'eve_updated',
            'bio' => 'Hello!',
        ]);

        $this->assertTrue($result);
        $user = $this->user->find($id);
        $this->assertSame('eve_updated', $user['username']);
        $this->assertSame('Hello!', $user['bio']);
    }

    public function testUpdateProfileIgnoresDisallowedFields(): void
    {
        $id = $this->user->register('frank', 'frank@example.com', 'pass');
        $result = $this->user->updateProfile($id, [
            'password_hash' => 'hacked',
            'global_role' => 'superadmin',
        ]);

        $this->assertFalse($result); // Aucun champ autorisé → retourne false
    }

    public function testUpdateProfileEmptyData(): void
    {
        $id = $this->user->register('grace', 'grace@example.com', 'pass');
        $this->assertFalse($this->user->updateProfile($id, []));
    }

    // ─── updatePassword ──────────────────────────────────────

    public function testUpdatePasswordHashesNew(): void
    {
        $id = $this->user->register('helen', 'helen@example.com', 'old');
        $this->user->updatePassword($id, 'new_password');

        $user = $this->user->find($id);
        $this->assertTrue(password_verify('new_password', $user['password_hash']));
        $this->assertFalse(password_verify('old', $user['password_hash']));
    }

    // ─── updateGlobalRole ────────────────────────────────────

    public function testUpdateGlobalRoleValid(): void
    {
        $id = $this->user->register('ivan', 'ivan@example.com', 'pass');

        $this->assertTrue($this->user->updateGlobalRole($id, 'admin'));
        $user = $this->user->find($id);
        $this->assertSame('admin', $user['global_role']);
    }

    public function testUpdateGlobalRoleInvalid(): void
    {
        $id = $this->user->register('jane', 'jane@example.com', 'pass');
        $this->assertFalse($this->user->updateGlobalRole($id, 'hacker'));
    }

    public function testUpdateGlobalRoleAllValidRoles(): void
    {
        $id = $this->user->register('kate', 'kate@example.com', 'pass');

        foreach (['superadmin', 'admin', 'moderator', 'user'] as $role) {
            $this->assertTrue($this->user->updateGlobalRole($id, $role));
            $user = $this->user->find($id);
            $this->assertSame($role, $user['global_role']);
        }
    }

    // ─── normalizeEmail (méthode statique, pas de DB) ────────

    public function testNormalizeEmailLowercase(): void
    {
        $this->assertSame('alice@example.com', User::normalizeEmail('ALICE@EXAMPLE.COM'));
    }

    public function testNormalizeEmailTrimsWhitespace(): void
    {
        $this->assertSame('alice@example.com', User::normalizeEmail('  alice@example.com  '));
    }

    public function testNormalizeEmailGmailRemovesDots(): void
    {
        $this->assertSame('alicebob@gmail.com', User::normalizeEmail('alice.bob@gmail.com'));
    }

    public function testNormalizeEmailGmailRemovesPlusAlias(): void
    {
        $this->assertSame('alice@gmail.com', User::normalizeEmail('alice+spam@gmail.com'));
    }

    public function testNormalizeEmailGmailDotsAndPlus(): void
    {
        $this->assertSame('alicebob@gmail.com', User::normalizeEmail('a.lice.bob+work@gmail.com'));
    }

    public function testNormalizeEmailGooglemailBecomesGmail(): void
    {
        $this->assertSame('alice@gmail.com', User::normalizeEmail('alice@googlemail.com'));
    }

    public function testNormalizeEmailNonGmailKeepsDotsAndPlus(): void
    {
        $this->assertSame('alice.bob+tag@outlook.com', User::normalizeEmail('alice.bob+tag@outlook.com'));
    }

    public function testNormalizeEmailNoAtSign(): void
    {
        $this->assertSame('notanemail', User::normalizeEmail('notanemail'));
    }

    // ─── RESTRICTION_KEYS ────────────────────────────────────

    public function testUserRestrictionKeysAreDefined(): void
    {
        $keys = User::RESTRICTION_KEYS;
        $this->assertArrayHasKey('space_create', $keys);
        $this->assertArrayHasKey('games_manage', $keys);
        $this->assertArrayHasKey('games_participation', $keys);
        $this->assertArrayHasKey('profile_photo_manage', $keys);
        $this->assertArrayHasKey('comments_manage', $keys);
        $this->assertCount(8, $keys);
    }
}

/**
 * Stub User avec injection PDO.
 * Surcharge les méthodes MySQL-spécifiques pour la compatibilité SQLite.
 */
class UserStub extends User
{
    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * Override : SQLite ne supporte pas SHOW COLUMNS.
     * On retourne false pour rester sur les requêtes basiques.
     */
    private ?bool $hasEmailNormalizedColumn = null;
    private ?bool $hasAccountDeletionColumns = null;

    public function findByEmail(string $email): ?array
    {
        $email = trim($email);
        return $this->findOneBy(['email' => $email]);
    }

    public function register(string $username, string $email, string $password): int
    {
        return $this->create([
            'username'      => $username,
            'email'         => trim($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'global_role'   => 'user',
        ]);
    }

    public function updateProfile(int $id, array $data): bool
    {
        $allowed = ['username', 'email', 'bio', 'avatar', 'show_win_rate_public'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        if (empty($filtered)) {
            return false;
        }
        return $this->update($id, $filtered);
    }
}
