<?php

namespace Georgeff\HttpKernel\Test;

use Georgeff\HttpKernel\MiddlewareResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class MiddlewareResolverTest extends TestCase
{
    public function test_returns_middleware_instance_as_is(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $container = new class implements ContainerInterface {
            public function get(string $id): mixed { throw new RuntimeException('should not be called'); }
            public function has(string $id): bool { return false; }
        };

        $resolver = new MiddlewareResolver($container);

        $this->assertSame($middleware, $resolver($middleware));
    }

    public function test_resolves_string_entry_from_container(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $container = new class ($middleware) implements ContainerInterface {
            public function __construct(private MiddlewareInterface $middleware) {}
            public function get(string $id): mixed { return $this->middleware; }
            public function has(string $id): bool { return true; }
        };

        $resolver = new MiddlewareResolver($container);

        $this->assertSame($middleware, $resolver('app.middleware.auth'));
    }

    public function test_throws_when_resolved_entry_is_not_middleware(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed { return new \stdClass(); }
            public function has(string $id): bool { return true; }
        };

        $resolver = new MiddlewareResolver($container);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid middleware entry [app.middleware.bad]');

        $resolver('app.middleware.bad');
    }
}
