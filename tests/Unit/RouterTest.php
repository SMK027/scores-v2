<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Router;

/**
 * Tests du Router.
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testCanRegisterGetRoute(): void
    {
        $result = $this->router->get('/test', 'TestController', 'index');
        $this->assertInstanceOf(Router::class, $result);
    }

    public function testCanRegisterPostRoute(): void
    {
        $result = $this->router->post('/test', 'TestController', 'store');
        $this->assertInstanceOf(Router::class, $result);
    }

    public function testFluentRouteRegistration(): void
    {
        $result = $this->router
            ->get('/a', 'A', 'a')
            ->get('/b', 'B', 'b')
            ->post('/c', 'C', 'c');

        $this->assertInstanceOf(Router::class, $result);
    }
}
