<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\CSRF;
use App\Core\Session;

/**
 * Tests du système CSRF.
 */
class CSRFTest extends TestCase
{
    protected function setUp(): void
    {
        // Simuler une session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testGenerateTokenReturnsNonEmpty(): void
    {
        $token = CSRF::generate();
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testGenerateTokenIsConsistentWithinSession(): void
    {
        $token1 = CSRF::generate();
        $token2 = CSRF::generate();
        $this->assertSame($token1, $token2);
    }

    public function testValidateReturnsTrueForValidToken(): void
    {
        $token = CSRF::generate();
        $this->assertTrue(CSRF::validate($token));
    }

    public function testValidateReturnsFalseForInvalidToken(): void
    {
        CSRF::generate();
        $this->assertFalse(CSRF::validate('invalid_token'));
    }

    public function testValidateReturnsFalseForEmptyToken(): void
    {
        CSRF::generate();
        $this->assertFalse(CSRF::validate(''));
    }

    public function testRegenerateChangesToken(): void
    {
        $token1 = CSRF::generate();
        CSRF::regenerate();
        $token2 = CSRF::generate();
        $this->assertNotSame($token1, $token2);
    }

    public function testFieldReturnsHiddenInput(): void
    {
        $token = CSRF::generate();
        $field = CSRF::field();
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString($token, $field);
    }
}
