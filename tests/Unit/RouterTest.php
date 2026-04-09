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

    public function testDispatchCallsCorrectAction(): void
    {
        $this->router->get('/hello', RouterTestController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', '/hello');
        $output = ob_get_clean();

        $this->assertSame('index_called', $output);
    }

    public function testDispatchExtractsNamedParameters(): void
    {
        $this->router->get('/users/{id}', RouterTestController::class, 'show');

        ob_start();
        $this->router->dispatch('GET', '/users/42');
        $output = ob_get_clean();

        $this->assertSame('show_42', $output);
    }

    public function testDispatchMultipleParameters(): void
    {
        $this->router->get('/spaces/{spaceId}/games/{gameId}', RouterTestController::class, 'nested');

        ob_start();
        $this->router->dispatch('GET', '/spaces/5/games/10');
        $output = ob_get_clean();

        $this->assertSame('nested_5_10', $output);
    }

    public function testDispatch404ForUnknownRoute(): void
    {
        $this->router->get('/exists', RouterTestController::class, 'index');

        ob_start();
        @$this->router->dispatch('GET', '/does-not-exist');
        $output = ob_get_clean();

        // En CLI, http_response_code() peut échouer (headers already sent),
        // on vérifie que la vue d'erreur est rendue
        $this->assertStringContainsString('introuvable', strtolower($output));
    }

    public function testDispatchMethodMismatch(): void
    {
        $this->router->get('/only-get', RouterTestController::class, 'index');

        ob_start();
        @$this->router->dispatch('POST', '/only-get');
        $output = ob_get_clean();

        $this->assertStringContainsString('introuvable', strtolower($output));
    }

    public function testDispatchTrimsTrailingSlash(): void
    {
        $this->router->get('/clean', RouterTestController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', '/clean/');
        $output = ob_get_clean();

        $this->assertSame('index_called', $output);
    }

    public function testDispatchStripsQueryString(): void
    {
        $this->router->get('/search', RouterTestController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', '/search?q=test&page=1');
        $output = ob_get_clean();

        $this->assertSame('index_called', $output);
    }
}

/**
 * Contrôleur factice pour les tests du Router.
 */
class RouterTestController
{
    public function index(): void
    {
        echo 'index_called';
    }

    public function show(string $id): void
    {
        echo 'show_' . $id;
    }

    public function nested(string $spaceId, string $gameId): void
    {
        echo 'nested_' . $spaceId . '_' . $gameId;
    }
}
