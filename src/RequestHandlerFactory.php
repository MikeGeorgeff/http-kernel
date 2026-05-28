<?php

namespace Georgeff\HttpKernel;

use Closure;
use Relay\Relay;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestHandlerFactory
{
    /**
     * @param \Closure(): array<MiddlewareInterface|string> $stack
     */
    public function __construct(private readonly Closure $stack) {}

    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        return new Relay(($this->stack)(), new MiddlewareResolver($container));
    }
}
