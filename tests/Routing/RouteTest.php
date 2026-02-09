<?php

namespace Georgeff\HttpKernel\Test\Routing;

use Georgeff\HttpKernel\Routing\Route;
use Georgeff\HttpKernel\Routing\RouteInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouteTest extends TestCase
{
    public function test_it_creates_a_route_with_valid_input(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');

        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertSame(['GET'], $route->getMethods());
        $this->assertSame('/users', $route->getPath());
        $this->assertSame('UserHandler', $route->getHandler());
    }

    public function test_it_accepts_multiple_methods(): void
    {
        $route = new Route(['GET', 'POST'], '/users', 'UserHandler');

        $this->assertSame(['GET', 'POST'], $route->getMethods());
    }

    public function test_it_accepts_request_handler_interface(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Not implemented');
            }
        };

        $route = new Route(['GET'], '/users', $handler);

        $this->assertSame($handler, $route->getHandler());
    }

    public function test_it_prepends_slash_when_path_does_not_start_with_slash(): void
    {
        $route = new Route(['GET'], 'users', 'UserHandler');

        $this->assertSame('/users', $route->getPath());
    }

    public function test_it_prepends_slash_when_path_is_empty(): void
    {
        $route = new Route(['GET'], '', 'UserHandler');

        $this->assertSame('/', $route->getPath());
    }

    public function test_it_does_not_modify_path_starting_with_slash(): void
    {
        $route = new Route(['GET'], '/users/{id}', 'UserHandler');

        $this->assertSame('/users/{id}', $route->getPath());
    }

    public function test_it_throws_when_methods_are_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route methods cannot be empty');

        new Route([], '/users', 'UserHandler');
    }

    public function test_it_normalizes_methods_to_uppercase(): void
    {
        $route = new Route(['get', 'Post'], '/users', 'UserHandler');

        $this->assertSame(['GET', 'POST'], $route->getMethods());
    }

    public function test_it_returns_empty_arguments_by_default(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');

        $this->assertSame([], $route->getArguments());
    }

    public function test_with_arguments_returns_new_instance(): void
    {
        $route = new Route(['GET'], '/users/{id}', 'UserHandler');
        $newRoute = $route->withArguments(['id' => '42']);

        $this->assertNotSame($route, $newRoute);
        $this->assertSame([], $route->getArguments());
        $this->assertSame(['id' => '42'], $newRoute->getArguments());
    }

    public function test_with_arguments_preserves_route_properties(): void
    {
        $route = new Route(['GET'], '/users/{id}', 'UserHandler');
        $newRoute = $route->withArguments(['id' => '42']);

        $this->assertSame($route->getMethods(), $newRoute->getMethods());
        $this->assertSame($route->getPath(), $newRoute->getPath());
        $this->assertSame($route->getHandler(), $newRoute->getHandler());
    }

    public function test_get_argument_returns_value_by_name(): void
    {
        $route = (new Route(['GET'], '/users/{id}', 'UserHandler'))
            ->withArguments(['id' => '42', 'name' => 'john']);

        $this->assertSame('42', $route->getArgument('id'));
        $this->assertSame('john', $route->getArgument('name'));
    }

    public function test_get_argument_returns_default_when_not_found(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');

        $this->assertNull($route->getArgument('id'));
        $this->assertSame('fallback', $route->getArgument('id', 'fallback'));
    }

    public function test_it_returns_empty_middleware_by_default(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');

        $this->assertSame([], $route->getMiddleware());
    }

    public function test_add_middleware_returns_same_instance(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');
        $result = $route->addMiddleware('SomeMiddleware');

        $this->assertSame($route, $result);
    }

    public function test_add_middleware_accepts_string(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');
        $route->addMiddleware('SomeMiddleware');

        $this->assertSame(['SomeMiddleware'], $route->getMiddleware());
    }

    public function test_add_middleware_accepts_middleware_interface(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/users', 'UserHandler');
        $route->addMiddleware($middleware);

        $this->assertSame([$middleware], $route->getMiddleware());
    }

    public function test_add_middleware_preserves_fifo_order(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');
        $route->addMiddleware('FirstMiddleware');
        $route->addMiddleware('SecondMiddleware');
        $route->addMiddleware('ThirdMiddleware');

        $this->assertSame(
            ['FirstMiddleware', 'SecondMiddleware', 'ThirdMiddleware'],
            $route->getMiddleware()
        );
    }

    public function test_add_middleware_is_fluent(): void
    {
        $route = new Route(['GET'], '/users', 'UserHandler');

        $route->addMiddleware('First')
              ->addMiddleware('Second')
              ->addMiddleware('Third');

        $this->assertSame(['First', 'Second', 'Third'], $route->getMiddleware());
    }
}
