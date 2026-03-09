<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Session;

/**
 * Tests de la classe Session.
 */
class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testSetAndGet(): void
    {
        Session::set('key', 'value');
        $this->assertSame('value', Session::get('key'));
    }

    public function testGetReturnsDefaultWhenNotSet(): void
    {
        $this->assertNull(Session::get('nonexistent'));
        $this->assertSame('default', Session::get('nonexistent', 'default'));
    }

    public function testHas(): void
    {
        $this->assertFalse(Session::has('key'));
        Session::set('key', 'value');
        $this->assertTrue(Session::has('key'));
    }

    public function testHasReturnsFalseForNull(): void
    {
        Session::set('key', null);
        $this->assertFalse(Session::has('key'));
    }

    public function testRemove(): void
    {
        Session::set('key', 'value');
        Session::remove('key');
        $this->assertFalse(Session::has('key'));
    }

    public function testRemoveNonExistentKey(): void
    {
        // Ne doit pas lever d'exception
        Session::remove('nonexistent');
        $this->assertFalse(Session::has('nonexistent'));
    }

    public function testFlash(): void
    {
        Session::set('flash', ['type' => 'success', 'message' => 'Opération réussie']);
        $flash = Session::getFlash();
        $this->assertIsArray($flash);
        $this->assertSame('success', $flash['type']);
        $this->assertSame('Opération réussie', $flash['message']);
        // Flash doit être consommé après lecture
        $this->assertNull(Session::getFlash());
    }

    public function testFlashReturnsNullWhenNotSet(): void
    {
        $this->assertNull(Session::getFlash());
    }

    public function testSetOverwritesExistingValue(): void
    {
        Session::set('key', 'first');
        Session::set('key', 'second');
        $this->assertSame('second', Session::get('key'));
    }

    public function testSetComplexData(): void
    {
        $data = ['user' => ['id' => 1, 'name' => 'Alice'], 'roles' => ['admin']];
        Session::set('data', $data);
        $this->assertSame($data, Session::get('data'));
    }

    public function testGetWithDefaultDoesNotSetValue(): void
    {
        $value = Session::get('key', 'default');
        $this->assertSame('default', $value);
        $this->assertFalse(Session::has('key'));
    }
}
