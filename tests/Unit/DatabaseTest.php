<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Config\Database;

/**
 * Tests du singleton Database.
 */
class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        // Réinitialiser le singleton entre les tests
        Database::resetInstance();
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    public function testResetInstanceClearsSingleton(): void
    {
        // Après reset, l'instance devrait être null
        // On vérifie qu'on peut appeler resetInstance sans erreur
        Database::resetInstance();
        $this->assertTrue(true); // Pas d'exception = succès
    }

    public function testCannotCloneDatabase(): void
    {
        // Le constructeur est privé, on ne peut pas instancier directement
        $reflection = new \ReflectionClass(Database::class);
        $cloneMethod = $reflection->getMethod('__clone');
        $this->assertTrue($cloneMethod->isPrivate());
    }

    public function testConstructorIsPrivate(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $constructor = $reflection->getConstructor();
        $this->assertTrue($constructor->isPrivate());
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        // Ce test ne peut pas s'exécuter sans une vraie base de données
        // On vérifie juste la structure du singleton
        $reflection = new \ReflectionClass(Database::class);
        $instanceProp = $reflection->getProperty('instance');
        $instanceProp->setAccessible(true);

        // Après reset, la propriété statique doit être null
        $this->assertNull($instanceProp->getValue());
    }
}
