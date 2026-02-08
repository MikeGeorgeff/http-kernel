<?php

namespace Georgeff\HttpKernel\Routing;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Route implements RouteInterface
{
    /**
     * @var non-empty-list<string>
     */
    private readonly array $methods;

    private readonly string $path;

    /**
     * @var array<string, string>
     */
    private array $arguments = [];

    /**
     * Route level middleware
     *
     * @var array<MiddlewareInterface|string>
     */
    private array $middleware = [];

    /**
     * @param list<string> $methods
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        array $methods,
        string $path,
        private readonly RequestHandlerInterface|string $handler
    ) {
        if ($methods === []) {
            throw new \InvalidArgumentException('Route methods cannot be empty');
        }

        $this->methods = $methods;

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        $this->path = $path;
    }

    /**
     * @inheritdoc
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): RequestHandlerInterface|string
    {
        return $this->handler;
    }

    /**
     * @inheritdoc
     */
    public function withArguments(array $arguments): RouteInterface
    {
        $r = clone $this;

        $r->arguments = $arguments;

        return $r;
    }

    /**
     * @inheritdoc
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgument(string $name, ?string $default = null): ?string
    {
        return $this->arguments[$name] ?? $default;
    }

    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
