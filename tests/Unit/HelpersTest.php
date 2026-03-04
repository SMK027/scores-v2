<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests des fonctions helper.
 */
class HelpersTest extends TestCase
{
    public function testEscapeHtml(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
        $this->assertSame('&amp;', e('&'));
        $this->assertSame('Hello', e('Hello'));
        $this->assertSame('', e(''));
    }

    public function testTruncate(): void
    {
        $this->assertSame('Hello...', truncate('Hello World', 5));
        $this->assertSame('Short', truncate('Short', 10));
        $this->assertSame('', truncate('', 5));
    }

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
    }

    public function testWinConditionLabel(): void
    {
        $this->assertSame('Score le plus élevé', win_condition_label('highest_score'));
        $this->assertSame('Score le plus bas', win_condition_label('lowest_score'));
        $this->assertSame('Victoire/Défaite', win_condition_label('win_loss'));
        $this->assertSame('Classement', win_condition_label('ranking'));
    }

    public function testSpaceRoleLabel(): void
    {
        $this->assertSame('Administrateur', space_role_label('admin'));
        $this->assertSame('Gestionnaire', space_role_label('manager'));
        $this->assertSame('Membre', space_role_label('member'));
        $this->assertSame('Invité', space_role_label('guest'));
    }

    public function testGlobalRoleLabel(): void
    {
        $this->assertSame('Super Admin', global_role_label('superadmin'));
        $this->assertSame('Administrateur', global_role_label('admin'));
        $this->assertSame('Modérateur', global_role_label('moderator'));
        $this->assertSame('Utilisateur', global_role_label('user'));
    }

    public function testFormatDate(): void
    {
        $this->assertSame('01/01/2024 12:00', format_date('2024-01-01 12:00:00'));
    }

    public function testTimeAgo(): void
    {
        // Test « à l'instant »
        $now = date('Y-m-d H:i:s');
        $result = time_ago($now);
        $this->assertStringContainsString('instant', $result);
    }
}
