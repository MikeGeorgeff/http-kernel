<?php

namespace Georgeff\HttpKernel\Test;

use Georgeff\HttpKernel\Event;
use Georgeff\HttpKernel\Exception\HttpExceptionInterface;
use Georgeff\HttpKernel\Exception\InternalServerErrorHttpException;
use Georgeff\HttpKernel\Exception\NotFoundHttpException;
use Georgeff\HttpKernel\HttpKernel;
use Georgeff\HttpKernel\HttpKernelInterface;
use Georgeff\HttpKernel\Routing\RouteInterface;
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\KernelException;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpKernelTest extends TestCase
{
    public function test_implements_http_kernel_interface(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $this->assertInstanceOf(HttpKernelInterface::class, $kernel);
    }

    public function test_implements_request_handler_interface(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $this->assertInstanceOf(RequestHandlerInterface::class, $kernel);
    }

    public function test_add_middleware_returns_kernel(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $result = $kernel->addMiddleware($middleware);

        $this->assertSame($kernel, $result);
    }

    public function test_add_middleware_after_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->addMiddleware('some.middleware');
    }

    public function test_add_route_returns_route_interface(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $route = $kernel->addRoute('GET', '/users', $handler);

        $this->assertInstanceOf(RouteInterface::class, $route);
    }

    public function test_add_route_with_array_methods(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $route = $kernel->addRoute(['GET', 'POST'], '/users', $handler);

        $this->assertSame(['GET', 'POST'], $route->getMethods());
    }

    public function test_add_route_after_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->addRoute('GET', '/users', 'handler');
    }

    public function test_with_exception_handler_returns_kernel(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $result = $kernel->withExceptionHandler(fn(HttpExceptionInterface $e) => new TextResponse('error'));

        $this->assertSame($kernel, $result);
    }

    public function test_with_exception_handler_after_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->withExceptionHandler(fn(HttpExceptionInterface $e) => new TextResponse('error'));
    }

    public function test_run_before_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $this->expectException(KernelException::class);

        $kernel->run();
    }

    public function test_handle_before_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $this->expectException(KernelException::class);

        $kernel->handle(ServerRequestFactory::fromGlobals());
    }

    public function test_handle_returns_response_from_middleware(): void
    {
        $response = new TextResponse('hello');

        $middleware = new class ($response) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $response) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals();
        $result = $kernel->handle($request);

        $this->assertSame($response, $result);
    }

    public function test_handle_dispatches_request_received_event(): void
    {
        /** @var list<object> $events */
        $events = [];

        $dispatcher = new class ($events) implements EventDispatcherInterface {
            /** @param list<object> $events */
            public function __construct(private array &$events) {}
            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };

        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addDefinition(EventDispatcherInterface::class, fn() => $dispatcher);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals();
        $kernel->handle($request);

        $receivedEvents = array_values(array_filter($events, fn($e) => $e instanceof Event\RequestReceived));
        $this->assertCount(1, $receivedEvents);
        $this->assertSame($request, $receivedEvents[0]->request);
        $this->assertSame($kernel, $receivedEvents[0]->kernel);
    }

    public function test_handle_dispatches_response_ready_event(): void
    {
        /** @var list<object> $events */
        $events = [];

        $dispatcher = new class ($events) implements EventDispatcherInterface {
            /** @param list<object> $events */
            public function __construct(private array &$events) {}
            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };

        $response = new TextResponse('ok');

        $middleware = new class ($response) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $response) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addDefinition(EventDispatcherInterface::class, fn() => $dispatcher);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals();
        $kernel->handle($request);

        $last = end($events);
        $this->assertInstanceOf(Event\ResponseReady::class, $last);
        $this->assertSame($request, $last->request);
        $this->assertSame($response, $last->response);
    }

    public function test_handle_rethrows_exception_without_handler(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw new \RuntimeException('boom');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $kernel->handle(ServerRequestFactory::fromGlobals());
    }

    public function test_handle_passes_http_exception_to_handler(): void
    {
        /** @var HttpExceptionInterface|null $captured */
        $captured = null;

        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw new NotFoundHttpException($request, 'not here');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->withExceptionHandler(function (HttpExceptionInterface $e) use (&$captured) {
            $captured = $e;
            return new TextResponse('handled', $e->getStatusCode());
        });
        $kernel->boot();

        $response = $kernel->handle(ServerRequestFactory::fromGlobals());

        $this->assertNotNull($captured);
        $this->assertInstanceOf(NotFoundHttpException::class, $captured);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_handle_wraps_non_http_exception_in_internal_server_error(): void
    {
        /** @var HttpExceptionInterface|null $captured */
        $captured = null;

        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw new \RuntimeException('unexpected');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->withExceptionHandler(function (HttpExceptionInterface $e) use (&$captured) {
            $captured = $e;
            return new TextResponse('error', $e->getStatusCode());
        });
        $kernel->boot();

        $response = $kernel->handle(ServerRequestFactory::fromGlobals());

        $this->assertNotNull($captured);
        $this->assertInstanceOf(InternalServerErrorHttpException::class, $captured);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('unexpected', $captured->getMessage());
    }

    public function test_handle_dispatches_request_errored_event_on_exception(): void
    {
        /** @var list<object> $events */
        $events = [];

        $dispatcher = new class ($events) implements EventDispatcherInterface {
            /** @param list<object> $events */
            public function __construct(private array &$events) {}
            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };

        $original = new \RuntimeException('boom');

        $middleware = new class ($original) implements MiddlewareInterface {
            public function __construct(private \RuntimeException $ex) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw $this->ex;
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addDefinition(EventDispatcherInterface::class, fn() => $dispatcher);
        $kernel->addMiddleware($middleware);
        $kernel->withExceptionHandler(fn(HttpExceptionInterface $e) => new TextResponse('error'));
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $erroredEvents = array_values(array_filter($events, fn($e) => $e instanceof Event\RequestErrored));
        $this->assertCount(1, $erroredEvents);
        $this->assertSame($original, $erroredEvents[0]->exception);
    }

    public function test_terminate_dispatches_kernel_terminating_event(): void
    {
        /** @var list<object> $events */
        $events = [];

        $dispatcher = new class ($events) implements EventDispatcherInterface {
            /** @param list<object> $events */
            public function __construct(private array &$events) {}
            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addDefinition(EventDispatcherInterface::class, fn() => $dispatcher);
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals();
        $response = new TextResponse('ok');

        $kernel->terminate($request, $response);

        $terminatingEvents = array_values(array_filter($events, fn($e) => $e instanceof Event\KernelTerminating));
        $this->assertCount(1, $terminatingEvents);
        $this->assertSame($request, $terminatingEvents[0]->request);
        $this->assertSame($response, $terminatingEvents[0]->response);
    }

    public function test_run_handles_request_emits_response_and_returns_zero(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('run output');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        ob_start();
        $result = $kernel->run();
        $output = ob_get_clean();

        $this->assertSame(0, $result);
        $this->assertSame('run output', $output);
    }

    public function test_boot_registers_router_when_routes_exist(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('routed');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addRoute('GET', '/test', $handler);
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']
        );

        $response = $kernel->handle($request);

        $this->assertSame('routed', (string) $response->getBody());
    }

    public function test_handle_middleware_runs_in_fifo_order(): void
    {
        /** @var list<string> $order */
        $order = [];

        $first = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'first';
                return $handler->handle($request);
            }
        };

        $second = new class ($order) implements MiddlewareInterface {
            /** @param list<string> $order */
            public function __construct(private array &$order) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'second';
                return new TextResponse('done');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($first);
        $kernel->addMiddleware($second);
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_add_middleware_accepts_string(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $result = $kernel->addMiddleware('app.middleware.auth');

        $this->assertSame($kernel, $result);
    }

    public function test_add_route_with_string_handler(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $route = $kernel->addRoute('GET', '/users', 'app.handler.users');

        $this->assertInstanceOf(RouteInterface::class, $route);
        $this->assertSame('/users', $route->getPath());
    }

    public function test_boot_is_idempotent(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function test_middleware_added_in_pre_boot_callback_is_included(): void
    {
        $response = new TextResponse('from callback');

        $middleware = new class ($response) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $response) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->onBooting(function () use ($kernel, $middleware): void {
            $kernel->addMiddleware($middleware);
        });
        $kernel->boot();

        $result = $kernel->handle(ServerRequestFactory::fromGlobals());

        $this->assertSame($response, $result);
    }

    public function test_get_debug_info_returns_empty_when_debug_is_off(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $this->assertSame([], $kernel->getDebugInfo());
    }

    public function test_get_debug_info_includes_request_profile_after_handle(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $kernel = new HttpKernel(Environment::Testing, debug: true);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('requestProfile', $info);
        $this->assertArrayHasKey('phases', $info['requestProfile']);
        $this->assertArrayHasKey('handle', $info['requestProfile']['phases']);
        $this->assertArrayHasKey('middleware', $info['requestProfile']['phases']);
    }

    public function test_get_debug_info_includes_all_phases_after_run(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $kernel = new HttpKernel(Environment::Testing, debug: true);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        ob_start();
        $kernel->run();
        ob_get_clean();

        $phases = $kernel->getDebugInfo()['requestProfile']['phases'];

        $this->assertArrayHasKey('requestResolution', $phases);
        $this->assertArrayHasKey('handle', $phases);
        $this->assertArrayHasKey('middleware', $phases);
        $this->assertArrayHasKey('emission', $phases);
        $this->assertArrayHasKey('terminate', $phases);
    }

    public function test_request_profile_records_exception_handling_phase(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw new NotFoundHttpException($request, 'not here');
            }
        };

        $kernel = new HttpKernel(Environment::Testing, debug: true);
        $kernel->addMiddleware($middleware);
        $kernel->withExceptionHandler(fn(HttpExceptionInterface $e) => new TextResponse('error', $e->getStatusCode()));
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $phases = $kernel->getDebugInfo()['requestProfile']['phases'];

        $this->assertArrayHasKey('exceptionHandling', $phases);
    }

    public function test_request_profile_captures_timing_on_rethrow(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw new \RuntimeException('boom');
            }
        };

        $kernel = new HttpKernel(Environment::Testing, debug: true);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        try {
            $kernel->handle(ServerRequestFactory::fromGlobals());
        } catch (\RuntimeException) {
        }

        $phases = $kernel->getDebugInfo()['requestProfile']['phases'];

        $this->assertArrayHasKey('handle', $phases);
        $this->assertArrayHasKey('middleware', $phases);
        $this->assertArrayNotHasKey('exceptionHandling', $phases);
    }

    public function test_get_debug_info_merges_boot_and_request_profiles(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $kernel = new HttpKernel(Environment::Testing, debug: true);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $info = $kernel->getDebugInfo();

        $this->assertArrayHasKey('bootProfile', $info);
        $this->assertArrayHasKey('requestProfile', $info);
    }

    public function test_handle_standalone_stops_profiler(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $kernel = new HttpKernel(Environment::Testing, debug: true);
        $kernel->addMiddleware($middleware);
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $requestProfile = $kernel->getDebugInfo()['requestProfile'];

        $this->assertGreaterThanOrEqual(0, $requestProfile['duration']);
    }

    public function test_route_added_in_pre_boot_callback_is_included(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('callback route');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->onBooting(function () use ($kernel, $handler): void {
            $kernel->addRoute('GET', '/callback', $handler);
        });
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/callback']
        );

        $response = $kernel->handle($request);

        $this->assertSame('callback route', (string) $response->getBody());
    }
}
