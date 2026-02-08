<?php

namespace Georgeff\HttpKernel\Test\Routing;

use Georgeff\HttpKernel\Routing\Route;
use Georgeff\HttpKernel\Routing\RouterFactory;
use Georgeff\HttpKernel\Routing\RouterInterface;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterFactoryTest extends TestCase
{
    public function test_it_creates_router_interface_instance(): void
    {
        $factory = new RouterFactory([]);

        $router = $factory($this->createEmptyContainer());

        $this->assertInstanceOf(RouterInterface::class, $router);
    }

    public function test_it_creates_functional_router(): void
    {
        $response = new TextResponse('ok');

        $handler = new class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $route = new Route(['GET'], '/users', $handler);

        $factory = new RouterFactory([$route]);

        $router = $factory($this->createEmptyContainer());

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Fallback handler should not be called');
            }
        };

        $result = $router->process($request, $fallback);

        $this->assertSame($response, $result);
    }

    public function test_it_resolves_string_handler_from_container(): void
    {
        $response = new TextResponse('ok');

        $handler = new class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $route = new Route(['GET'], '/users', 'app.handler.users');

        $container = new class ($handler) implements ContainerInterface {
            public function __construct(private RequestHandlerInterface $handler) {}
            public function get(string $id): mixed { return $this->handler; }
            public function has(string $id): bool { return true; }
        };

        $factory = new RouterFactory([$route]);
        $router = $factory($container);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Fallback handler should not be called');
            }
        };

        $result = $router->process($request, $fallback);

        $this->assertSame($response, $result);
    }

    public function test_it_builds_router_with_route_parameters(): void
    {
        /** @var ServerRequestInterface|null $capturedRequest */
        $capturedRequest = null;

        $handler = new class ($capturedRequest) implements RequestHandlerInterface {
            public function __construct(private ?ServerRequestInterface &$captured) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;
                return new TextResponse('ok');
            }
        };

        $route = new Route(['GET'], '/users/{id}', $handler);

        $factory = new RouterFactory([$route]);
        $router = $factory($this->createEmptyContainer());

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']
        );

        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Fallback handler should not be called');
            }
        };

        $router->process($request, $fallback);

        $this->assertNotNull($capturedRequest);

        $routeAttr = $capturedRequest->getAttribute('__route__');

        $this->assertSame('42', $routeAttr->getArgument('id'));
    }

    private function createEmptyContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed { throw new \RuntimeException("No entry: $id"); }
            public function has(string $id): bool { return false; }
        };
    }
}
