<?php

namespace Georgeff\HttpKernel\Test;

use Georgeff\HttpKernel\EmitterInterface;
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

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

    public function test_on_request_received_returns_kernel(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $result = $kernel->onRequestReceived(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_request_received_after_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->onRequestReceived(function () {});
    }

    public function test_handle_invokes_request_received_callback(): void
    {
        /** @var ServerRequestInterface|null $captured */
        $captured = null;

        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->onRequestReceived(function (HttpKernelInterface $k, ServerRequestInterface $r) use (&$captured) {
            $captured = $r;
        });
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals();
        $kernel->handle($request);

        $this->assertSame($request, $captured);
    }

    public function test_on_response_ready_returns_kernel(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $result = $kernel->onResponseReady(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_response_ready_after_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->onResponseReady(function () {});
    }

    public function test_handle_invokes_response_ready_callback(): void
    {
        /** @var ServerRequestInterface|null $capturedRequest */
        $capturedRequest = null;
        /** @var ResponseInterface|null $capturedResponse */
        $capturedResponse = null;

        $response = new TextResponse('ok');

        $middleware = new class ($response) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $response) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->onResponseReady(function (HttpKernelInterface $k, ServerRequestInterface $r, ResponseInterface $res) use (&$capturedRequest, &$capturedResponse) {
            $capturedRequest = $r;
            $capturedResponse = $res;
        });
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals();
        $kernel->handle($request);

        $this->assertSame($request, $capturedRequest);
        $this->assertSame($response, $capturedResponse);
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

    public function test_on_request_error_returns_kernel(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $result = $kernel->onRequestError(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_request_error_after_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->onRequestError(function () {});
    }

    public function test_handle_invokes_request_error_callback_on_exception(): void
    {
        /** @var Throwable|null $captured */
        $captured = null;

        $original = new \RuntimeException('boom');

        $middleware = new class ($original) implements MiddlewareInterface {
            public function __construct(private \RuntimeException $ex) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw $this->ex;
            }
        };

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->addMiddleware($middleware);
        $kernel->onRequestError(function (HttpKernelInterface $k, Throwable $e) use (&$captured) {
            $captured = $e;
        });
        $kernel->withExceptionHandler(fn(HttpExceptionInterface $e) => new TextResponse('error'));
        $kernel->boot();

        $kernel->handle(ServerRequestFactory::fromGlobals());

        $this->assertSame($original, $captured);
    }

    public function test_on_termination_returns_kernel(): void
    {
        $kernel = new HttpKernel(Environment::Testing);

        $result = $kernel->onTermination(function () {});

        $this->assertSame($kernel, $result);
    }

    public function test_on_termination_after_boot_throws(): void
    {
        $kernel = new HttpKernel(Environment::Testing);
        $kernel->boot();

        $this->expectException(KernelException::class);

        $kernel->onTermination(function () {});
    }

    public function test_terminate_invokes_termination_callback(): void
    {
        /** @var ServerRequestInterface|null $capturedRequest */
        $capturedRequest = null;
        /** @var ResponseInterface|null $capturedResponse */
        $capturedResponse = null;

        $kernel = new HttpKernel(Environment::Testing);
        $kernel->onTermination(function (HttpKernelInterface $k, ServerRequestInterface $r, ResponseInterface $res) use (&$capturedRequest, &$capturedResponse) {
            $capturedRequest = $r;
            $capturedResponse = $res;
        });
        $kernel->boot();

        $request = ServerRequestFactory::fromGlobals();
        $response = new TextResponse('ok');

        $kernel->terminate($request, $response);

        $this->assertSame($request, $capturedRequest);
        $this->assertSame($response, $capturedResponse);
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
}
