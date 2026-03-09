<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Controller;
use App\Core\Session;

/**
 * Tests de la classe abstraite Controller via un stub concret.
 */
class ControllerTest extends TestCase
{
    private ControllerStub $controller;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_POST = [];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = '';
        $this->controller = new ControllerStub();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    // ─── setFlash ────────────────────────────────────────────

    public function testSetFlashStoresInSession(): void
    {
        $this->controller->publicSetFlash('success', 'Bravo !');
        $flash = Session::getFlash();

        $this->assertSame('success', $flash['type']);
        $this->assertSame('Bravo !', $flash['message']);
    }

    // ─── getPostData ─────────────────────────────────────────

    public function testGetPostDataReturnsRequestedFields(): void
    {
        $_POST['name'] = '  Alice  ';
        $_POST['email'] = 'alice@example.com';
        $_POST['extra'] = 'ignored';

        $data = $this->controller->publicGetPostData(['name', 'email']);

        $this->assertSame('Alice', $data['name']); // trim
        $this->assertSame('alice@example.com', $data['email']);
        $this->assertArrayNotHasKey('extra', $data);
    }

    public function testGetPostDataReturnEmptyForMissingFields(): void
    {
        $data = $this->controller->publicGetPostData(['missing']);
        $this->assertSame('', $data['missing']);
    }

    // ─── getCurrentUserId ────────────────────────────────────

    public function testGetCurrentUserIdReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull($this->controller->publicGetCurrentUserId());
    }

    public function testGetCurrentUserIdReturnsInt(): void
    {
        $_SESSION['user_id'] = 42;
        $this->assertSame(42, $this->controller->publicGetCurrentUserId());
    }

    public function testGetCurrentUserIdCastsToInt(): void
    {
        $_SESSION['user_id'] = '55';
        $this->assertSame(55, $this->controller->publicGetCurrentUserId());
    }

    // ─── isAjax ──────────────────────────────────────────────

    public function testIsAjaxReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->controller->publicIsAjax());
    }

    public function testIsAjaxReturnsTrueWithHeader(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue($this->controller->publicIsAjax());
    }

    public function testIsAjaxIsCaseInsensitive(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $this->assertTrue($this->controller->publicIsAjax());
    }
}

/**
 * Stub concret pour tester les méthodes protégées du Controller.
 */
class ControllerStub extends Controller
{
    public function publicSetFlash(string $type, string $message): void
    {
        $this->setFlash($type, $message);
    }

    public function publicGetPostData(array $fields): array
    {
        return $this->getPostData($fields);
    }

    public function publicGetCurrentUserId(): ?int
    {
        return $this->getCurrentUserId();
    }

    public function publicIsAjax(): bool
    {
        return $this->isAjax();
    }
}
