<?php

namespace Georgeff\HttpKernel\Test\Middleware;

use Georgeff\HttpKernel\Middleware\RequestHandlerMiddleware;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestHandlerMiddlewareTest extends TestCase
{
    public function test_it_implements_middleware_interface(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $middleware = new RequestHandlerMiddleware($handler);

        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }

    public function test_it_delegates_to_wrapped_handler(): void
    {
        $response = new TextResponse('from handler');

        $handler = new class ($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $middleware = new RequestHandlerMiddleware($handler);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']
        );

        $next = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Next handler should not be called');
            }
        };

        $result = $middleware->process($request, $next);

        $this->assertSame($response, $result);
    }

    public function test_it_ignores_next_handler(): void
    {
        $nextCalled = false;

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $next = new class ($nextCalled) implements RequestHandlerInterface {
            public function __construct(private bool &$called) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                return new TextResponse('next');
            }
        };

        $middleware = new RequestHandlerMiddleware($handler);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']
        );

        $middleware->process($request, $next);

        $this->assertFalse($nextCalled);
    }

    public function test_it_passes_request_to_wrapped_handler(): void
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

        $middleware = new RequestHandlerMiddleware($handler);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/test']
        );
        $request = $request->withAttribute('foo', 'bar');

        $next = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Next handler should not be called');
            }
        };

        $middleware->process($request, $next);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('bar', $capturedRequest->getAttribute('foo'));
    }
}
