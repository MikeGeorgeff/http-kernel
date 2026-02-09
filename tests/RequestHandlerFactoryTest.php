<?php

namespace Georgeff\HttpKernel\Test;

use Georgeff\HttpKernel\RequestHandlerFactory;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestHandlerFactoryTest extends TestCase
{
    public function test_creates_request_handler(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('ok');
            }
        };

        $factory = new RequestHandlerFactory([$middleware]);

        $container = new class implements ContainerInterface {
            public function get(string $id): mixed { throw new \RuntimeException('no'); }
            public function has(string $id): bool { return false; }
        };

        $handler = $factory($container);

        $this->assertInstanceOf(RequestHandlerInterface::class, $handler);
    }

    public function test_handler_processes_middleware_stack(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('from middleware');
            }
        };

        $factory = new RequestHandlerFactory([$middleware]);

        $container = new class implements ContainerInterface {
            public function get(string $id): mixed { throw new \RuntimeException('no'); }
            public function has(string $id): bool { return false; }
        };

        $handler = $factory($container);
        $request = ServerRequestFactory::fromGlobals();
        $response = $handler->handle($request);

        $this->assertSame('from middleware', (string) $response->getBody());
    }

    public function test_handler_resolves_string_middleware_from_container(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new TextResponse('resolved');
            }
        };

        $factory = new RequestHandlerFactory(['app.middleware']);

        $container = new class ($middleware) implements ContainerInterface {
            public function __construct(private MiddlewareInterface $middleware) {}
            public function get(string $id): mixed { return $this->middleware; }
            public function has(string $id): bool { return true; }
        };

        $handler = $factory($container);
        $request = ServerRequestFactory::fromGlobals();
        $response = $handler->handle($request);

        $this->assertSame('resolved', (string) $response->getBody());
    }
}
