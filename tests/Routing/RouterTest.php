<?php

namespace Georgeff\HttpKernel\Test\Routing;

use FastRoute\Dispatcher;
use Georgeff\HttpKernel\Exception\MethodNotAllowedHttpException;
use Georgeff\HttpKernel\Exception\NotFoundHttpException;
use Georgeff\HttpKernel\Routing\Route;
use Georgeff\HttpKernel\Routing\RouteInterface;
use Georgeff\HttpKernel\Routing\Router;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class RouterTest extends TestCase
{
    public function test_it_dispatches_matched_route_with_handler_instance(): void
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

        $router = new Router(
            $this->createFoundDispatcher($route, []),
            $this->createEmptyContainer()
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $result = $router->process($request, $this->createFallbackHandler());

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

        $router = new Router(
            $this->createFoundDispatcher($route, []),
            $container
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $result = $router->process($request, $this->createFallbackHandler());

        $this->assertSame($response, $result);
    }

    public function test_it_stores_route_with_arguments_as_request_attribute(): void
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

        $router = new Router(
            $this->createFoundDispatcher($route, ['id' => '42']),
            $this->createEmptyContainer()
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']
        );

        $router->process($request, $this->createFallbackHandler());

        $this->assertNotNull($capturedRequest);

        $routeAttr = $capturedRequest->getAttribute('__route__');

        $this->assertInstanceOf(RouteInterface::class, $routeAttr);
        $this->assertSame(['id' => '42'], $routeAttr->getArguments());
        $this->assertSame('42', $routeAttr->getArgument('id'));
    }

    public function test_it_throws_not_found_http_exception(): void
    {
        $dispatcher = new class implements Dispatcher {
            public function dispatch($httpMethod, $uri): array
            {
                return [Dispatcher::NOT_FOUND];
            }
        };

        $router = new Router($dispatcher, $this->createEmptyContainer());

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/missing']
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Route not found GET /missing');

        $router->process($request, $this->createFallbackHandler());
    }

    public function test_it_throws_method_not_allowed_http_exception(): void
    {
        $dispatcher = new class implements Dispatcher {
            public function dispatch($httpMethod, $uri): array
            {
                return [Dispatcher::METHOD_NOT_ALLOWED, ['GET', 'PUT']];
            }
        };

        $router = new Router($dispatcher, $this->createEmptyContainer());

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users']
        );

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->expectExceptionMessage('Method not allowed for route POST /users');

        $router->process($request, $this->createFallbackHandler());
    }

    public function test_method_not_allowed_exception_carries_allowed_methods(): void
    {
        $dispatcher = new class implements Dispatcher {
            public function dispatch($httpMethod, $uri): array
            {
                return [Dispatcher::METHOD_NOT_ALLOWED, ['GET', 'PUT']];
            }
        };

        $router = new Router($dispatcher, $this->createEmptyContainer());

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users']
        );

        try {
            $router->process($request, $this->createFallbackHandler());
            $this->fail('Expected MethodNotAllowedHttpException');
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertSame(['GET', 'PUT'], $e->getAllowedMethods());
        }
    }

    public function test_it_throws_runtime_exception_when_resolved_handler_is_invalid(): void
    {
        $route = new Route(['GET'], '/users', 'app.handler.invalid');

        $container = new class implements ContainerInterface {
            public function get(string $id): mixed { return new \stdClass(); }
            public function has(string $id): bool { return true; }
        };

        $router = new Router(
            $this->createFoundDispatcher($route, []),
            $container
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('route handlers must implement');

        $router->process($request, $this->createFallbackHandler());
    }

    public function test_it_runs_route_middleware_before_handler(): void
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

        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $request = $request->withAttribute('middleware_ran', true);
                return $handler->handle($request);
            }
        };

        $route = new Route(['GET'], '/users', $handler);
        $route->addMiddleware($middleware);

        $router = new Router(
            $this->createFoundDispatcher($route, []),
            $this->createEmptyContainer()
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $router->process($request, $this->createFallbackHandler());

        $this->assertNotNull($capturedRequest);
        $this->assertTrue($capturedRequest->getAttribute('middleware_ran'));
    }

    public function test_route_middleware_has_access_to_route_attribute(): void
    {
        /** @var RouteInterface|null $capturedRoute */
        $capturedRoute = null;

        $middleware = new class ($capturedRoute) implements MiddlewareInterface {
            public function __construct(private ?RouteInterface &$captured) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->captured = $request->getAttribute('__route__');
                return $handler->handle($request);
            }
        };

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $route = new Route(['GET'], '/users/{id}', $handler);
        $route->addMiddleware($middleware);

        $router = new Router(
            $this->createFoundDispatcher($route, ['id' => '42']),
            $this->createEmptyContainer()
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']
        );

        $router->process($request, $this->createFallbackHandler());

        $this->assertNotNull($capturedRoute);
        $this->assertSame('42', $capturedRoute->getArgument('id'));
    }

    public function test_route_middleware_processes_in_fifo_order(): void
    {
        /** @var list<string> $order */
        $order = [];

        $firstMiddleware = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'first';
                return $handler->handle($request);
            }
        };

        $secondMiddleware = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'second';
                return $handler->handle($request);
            }
        };

        $thirdMiddleware = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'third';
                return $handler->handle($request);
            }
        };

        $handler = new class ($order) implements RequestHandlerInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->order[] = 'handler';
                return new TextResponse('ok');
            }
        };

        $route = new Route(['GET'], '/users', $handler);
        $route->addMiddleware($firstMiddleware)
              ->addMiddleware($secondMiddleware)
              ->addMiddleware($thirdMiddleware);

        $router = new Router(
            $this->createFoundDispatcher($route, []),
            $this->createEmptyContainer()
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $router->process($request, $this->createFallbackHandler());

        $this->assertSame(['first', 'second', 'third', 'handler'], $order);
    }

    public function test_route_middleware_resolves_string_entries_from_container(): void
    {
        /** @var ServerRequestInterface|null $capturedRequest */
        $capturedRequest = null;

        $middleware = new class ($capturedRequest) implements MiddlewareInterface {
            public function __construct(private ?ServerRequestInterface &$captured) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $request = $request->withAttribute('resolved_middleware', true);
                $this->captured = $request;
                return $handler->handle($request);
            }
        };

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $route = new Route(['GET'], '/users', $handler);
        $route->addMiddleware('app.middleware.auth');

        $container = new class ($middleware) implements ContainerInterface {
            public function __construct(private MiddlewareInterface $middleware) {}
            public function get(string $id): mixed { return $this->middleware; }
            public function has(string $id): bool { return true; }
        };

        $router = new Router(
            $this->createFoundDispatcher($route, []),
            $container
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $router->process($request, $this->createFallbackHandler());

        $this->assertNotNull($capturedRequest);
        $this->assertTrue($capturedRequest->getAttribute('resolved_middleware'));
    }

    public function test_it_dispatches_directly_to_handler_without_route_middleware(): void
    {
        $response = new TextResponse('direct');

        $handler = new class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $route = new Route(['GET'], '/users', $handler);
        // no middleware added

        $router = new Router(
            $this->createFoundDispatcher($route, []),
            $this->createEmptyContainer()
        );

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']
        );

        $result = $router->process($request, $this->createFallbackHandler());

        $this->assertSame($response, $result);
    }

    /**
     * @param array<string, string> $vars
     */
    private function createFoundDispatcher(RouteInterface $route, array $vars): Dispatcher
    {
        return new class ($route, $vars) implements Dispatcher {
            public function __construct(
                private RouteInterface $route,
                private array $vars
            ) {}
            public function dispatch($httpMethod, $uri): array
            {
                return [Dispatcher::FOUND, $this->route, $this->vars];
            }
        };
    }

    private function createEmptyContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed { throw new \RuntimeException("No entry: $id"); }
            public function has(string $id): bool { return false; }
        };
    }

    private function createFallbackHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Fallback handler should not be called');
            }
        };
    }
}
