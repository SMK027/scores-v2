<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests des fonctions helper.
 */
class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        // Simuler une session pour les helpers qui en dépendent
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_SERVER = array_merge($_SERVER, [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        // Supprimer les headers proxy pour un état propre
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─── e() ──────────────────────────────────────────────────

    public function testEscapeHtml(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
        $this->assertSame('&amp;', e('&'));
        $this->assertSame('Hello', e('Hello'));
        $this->assertSame('', e(''));
    }

    public function testEscapeNull(): void
    {
        $this->assertSame('', e(null));
    }

    public function testEscapeQuotes(): void
    {
        $this->assertSame('&quot;hello&quot;', e('"hello"'));
        $this->assertSame('&#039;hello&#039;', e("'hello'"));
    }

    // ─── truncate() ──────────────────────────────────────────

    public function testTruncate(): void
    {
        $this->assertSame('Hello...', truncate('Hello World', 5));
        $this->assertSame('Short', truncate('Short', 10));
        $this->assertSame('', truncate('', 5));
    }

    public function testTruncateCustomSuffix(): void
    {
        $this->assertSame('Hello→', truncate('Hello World', 5, '→'));
    }

    public function testTruncateExactLength(): void
    {
        $this->assertSame('Hello', truncate('Hello', 5));
    }

    public function testTruncateMultibyte(): void
    {
        $this->assertSame('Hé...', truncate('Héllo World', 2));
    }

    // ─── game_status_label / game_status_class ───────────────

    public function testGameStatusLabel(): void
    {
        $this->assertSame('En attente', game_status_label('pending'));
        $this->assertSame('En cours', game_status_label('in_progress'));
        $this->assertSame('En pause', game_status_label('paused'));
        $this->assertSame('Terminée', game_status_label('completed'));
        $this->assertSame('unknown', game_status_label('unknown'));
    }

    public function testGameStatusClass(): void
    {
        $this->assertSame('badge-secondary', game_status_class('pending'));
        $this->assertSame('badge-success', game_status_class('in_progress'));
        $this->assertSame('badge-warning', game_status_class('paused'));
        $this->assertSame('badge-primary', game_status_class('completed'));
        $this->assertSame('badge-secondary', game_status_class('unknown'));
    }

    // ─── win_condition_label ─────────────────────────────────

    public function testWinConditionLabel(): void
    {
        $this->assertSame('Score le plus élevé', win_condition_label('highest_score'));
        $this->assertSame('Score le plus bas', win_condition_label('lowest_score'));
        $this->assertSame('Victoire/Défaite', win_condition_label('win_loss'));
        $this->assertSame('Classement', win_condition_label('ranking'));
        $this->assertSame('custom', win_condition_label('custom'));
    }

    // ─── space_role_label ────────────────────────────────────

    public function testSpaceRoleLabel(): void
    {
        $this->assertSame('Administrateur', space_role_label('admin'));
        $this->assertSame('Gestionnaire', space_role_label('manager'));
        $this->assertSame('Membre', space_role_label('member'));
        $this->assertSame('Invité', space_role_label('guest'));
        $this->assertSame('unknown', space_role_label('unknown'));
    }

    // ─── global_role_label ───────────────────────────────────

    public function testGlobalRoleLabel(): void
    {
        $this->assertSame('Super Admin', global_role_label('superadmin'));
        $this->assertSame('Administrateur', global_role_label('admin'));
        $this->assertSame('Modérateur', global_role_label('moderator'));
        $this->assertSame('Utilisateur', global_role_label('user'));
        $this->assertSame('custom', global_role_label('custom'));
    }

    // ─── format_date ─────────────────────────────────────────

    public function testFormatDate(): void
    {
        $this->assertSame('01/01/2024 12:00', format_date('2024-01-01 12:00:00'));
    }

    public function testFormatDateCustomFormat(): void
    {
        $this->assertSame('2024-01-01', format_date('2024-01-01 12:00:00', 'Y-m-d'));
    }

    public function testFormatDateNull(): void
    {
        $this->assertSame('-', format_date(null));
        $this->assertSame('-', format_date(''));
    }

    // ─── time_ago ────────────────────────────────────────────

    public function testTimeAgoJustNow(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->assertStringContainsString('instant', time_ago($now));
    }

    public function testTimeAgoMinutes(): void
    {
        $date = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $this->assertStringContainsString('5 minute', time_ago($date));
    }

    public function testTimeAgoHours(): void
    {
        $date = date('Y-m-d H:i:s', strtotime('-3 hours'));
        $this->assertStringContainsString('3 heure', time_ago($date));
    }

    public function testTimeAgoDays(): void
    {
        $date = date('Y-m-d H:i:s', strtotime('-2 days'));
        $this->assertStringContainsString('2 jour', time_ago($date));
    }

    public function testTimeAgoNull(): void
    {
        $this->assertSame('-', time_ago(null));
        $this->assertSame('-', time_ago(''));
    }

    // ─── format_duration ─────────────────────────────────────

    public function testFormatDurationZero(): void
    {
        $this->assertSame('0s', format_duration(0));
    }

    public function testFormatDurationNegative(): void
    {
        $this->assertSame('0s', format_duration(-5));
    }

    public function testFormatDurationSecondsOnly(): void
    {
        $this->assertSame('45s', format_duration(45));
    }

    public function testFormatDurationMinutesAndSeconds(): void
    {
        $this->assertSame('2min 30s', format_duration(150));
    }

    public function testFormatDurationHoursMinutesSeconds(): void
    {
        $this->assertSame('1h 23min 45s', format_duration(5025));
    }

    public function testFormatDurationExactHours(): void
    {
        $this->assertSame('2h', format_duration(7200));
    }

    public function testFormatDurationExactMinutes(): void
    {
        $this->assertSame('5min', format_duration(300));
    }

    // ─── round_status_label / round_status_class ─────────────

    public function testRoundStatusLabel(): void
    {
        $this->assertSame('En cours', round_status_label('in_progress'));
        $this->assertSame('En pause', round_status_label('paused'));
        $this->assertSame('Terminée', round_status_label('completed'));
        $this->assertSame('unknown', round_status_label('unknown'));
    }

    public function testRoundStatusClass(): void
    {
        $this->assertSame('badge-info', round_status_class('in_progress'));
        $this->assertSame('badge-warning', round_status_class('paused'));
        $this->assertSame('badge-success', round_status_class('completed'));
        $this->assertSame('badge-secondary', round_status_class('unknown'));
    }

    // ─── get_client_ip ───────────────────────────────────────

    public function testGetClientIpFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $this->assertSame('192.168.1.100', get_client_ip());
    }

    public function testGetClientIpFromXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.41.3.18, 150.172.238.178';
        $this->assertSame('203.0.113.50', get_client_ip());
    }

    public function testGetClientIpFromXRealIp(): void
    {
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.25';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertSame('198.51.100.25', get_client_ip());
    }

    public function testGetClientIpDockerGateway(): void
    {
        $_SERVER['REMOTE_ADDR'] = '172.18.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $this->assertSame('127.0.0.1', get_client_ip());
    }

    public function testGetClientIpFallback(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $this->assertSame('127.0.0.1', get_client_ip());
    }

    // ─── url() ───────────────────────────────────────────────

    public function testUrlBase(): void
    {
        putenv('APP_URL=http://localhost:8080');
        $this->assertSame('http://localhost:8080/', url());
    }

    public function testUrlWithPath(): void
    {
        putenv('APP_URL=http://localhost:8080');
        $this->assertSame('http://localhost:8080/login', url('login'));
        $this->assertSame('http://localhost:8080/login', url('/login'));
    }

    // ─── is_authenticated / current_user_id / current_username / current_avatar / current_global_role ──

    public function testIsAuthenticatedFalse(): void
    {
        $this->assertFalse(is_authenticated());
    }

    public function testIsAuthenticatedTrue(): void
    {
        $_SESSION['user_id'] = 42;
        $this->assertTrue(is_authenticated());
    }

    public function testCurrentUserId(): void
    {
        $this->assertNull(current_user_id());
        $_SESSION['user_id'] = 42;
        $this->assertSame(42, current_user_id());
    }

    public function testCurrentUsername(): void
    {
        $this->assertSame('', current_username());
        $_SESSION['username'] = 'alice';
        $this->assertSame('alice', current_username());
    }

    public function testCurrentAvatar(): void
    {
        $this->assertSame('', current_avatar());
        $_SESSION['avatar'] = '/img/avatar.png';
        $this->assertSame('/img/avatar.png', current_avatar());
    }

    public function testCurrentGlobalRole(): void
    {
        $this->assertSame('user', current_global_role());
        $_SESSION['global_role'] = 'admin';
        $this->assertSame('admin', current_global_role());
    }
}
