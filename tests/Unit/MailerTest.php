<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Mailer;

/**
 * Tests de la classe Mailer.
 * Teste la configuration et la logique de filtrage sans connexion SMTP réelle.
 */
class MailerTest extends TestCase
{
    protected function setUp(): void
    {
        // Configurer les variables d'environnement SMTP pour les tests
        putenv('SMTP_HOST=localhost');
        putenv('SMTP_PORT=587');
        putenv('SMTP_USER=test@example.com');
        putenv('SMTP_PASS=secret');
        putenv('MAIL_FROM_ADDRESS=noreply@scores.local');
        putenv('MAIL_FROM_NAME=Scores Test');
        putenv('SMTP_ENCRYPTION=tls');
    }

    public function testMailerCanBeInstantiated(): void
    {
        $mailer = new Mailer();
        $this->assertInstanceOf(Mailer::class, $mailer);
    }

    public function testSendBccRejectEmptyArray(): void
    {
        $mailer = new Mailer();
        // sendBcc avec un tableau vide doit retourner false
        $result = $mailer->sendBcc([], 'Test', '<p>Hello</p>');
        $this->assertFalse($result);
    }

    public function testSendBccRejectArrayOfEmptyStrings(): void
    {
        $mailer = new Mailer();
        // array_filter devrait supprimer les chaînes vides
        $result = $mailer->sendBcc(['', '', ''], 'Test', '<p>Hello</p>');
        $this->assertFalse($result);
    }

    public function testBuildHeadersViaReflection(): void
    {
        $mailer = new Mailer();
        $reflection = new \ReflectionMethod($mailer, 'buildHeaders');
        $reflection->setAccessible(true);

        $headers = $reflection->invoke($mailer, 'dest@example.com', 'Mon Sujet');

        $this->assertStringContainsString('From: Scores Test <noreply@scores.local>', $headers);
        $this->assertStringContainsString('To: dest@example.com', $headers);
        $this->assertStringContainsString('Subject:', $headers);
        $this->assertStringContainsString('MIME-Version: 1.0', $headers);
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $headers);
        $this->assertStringContainsString('X-Mailer: Scores-App/1.0', $headers);
    }

    public function testBuildHeadersEncodesSubjectInBase64Utf8(): void
    {
        $mailer = new Mailer();
        $reflection = new \ReflectionMethod($mailer, 'buildHeaders');
        $reflection->setAccessible(true);

        $headers = $reflection->invoke($mailer, 'dest@example.com', 'Alerte suppression');

        // Le sujet doit être encodé en Base64 UTF-8
        $this->assertStringContainsString('=?UTF-8?B?', $headers);
        $this->assertStringContainsString(base64_encode('Alerte suppression'), $headers);
    }

    public function testBuildBccHeadersViaReflection(): void
    {
        $mailer = new Mailer();
        $reflection = new \ReflectionMethod($mailer, 'buildBccHeaders');
        $reflection->setAccessible(true);

        $headers = $reflection->invoke($mailer, 'Notification');

        $this->assertStringContainsString('From: Scores Test <noreply@scores.local>', $headers);
        $this->assertStringContainsString('To: undisclosed-recipients:;', $headers);
        $this->assertStringContainsString('Subject:', $headers);
        $this->assertStringNotContainsString('Bcc:', $headers); // BCC n'apparaît pas dans les headers
    }

    public function testMailerDefaultConfig(): void
    {
        putenv('SMTP_HOST=');
        putenv('SMTP_PORT=');
        putenv('MAIL_FROM_ADDRESS=');
        putenv('MAIL_FROM_NAME=');

        $mailer = new Mailer();

        // Vérifier via buildHeaders que les défauts sont appliqués
        $reflection = new \ReflectionMethod($mailer, 'buildHeaders');
        $reflection->setAccessible(true);
        $headers = $reflection->invoke($mailer, 'test@test.com', 'Test');

        $this->assertStringContainsString('Scores App', $headers);
        $this->assertStringContainsString('noreply@scores.local', $headers);
    }
}
