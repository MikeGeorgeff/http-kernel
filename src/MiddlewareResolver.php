<?php

namespace Georgeff\HttpKernel;

use RuntimeException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

final class MiddlewareResolver
{
    public function __construct(private ContainerInterface $container) {}

    public function __invoke(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        if (is_string($middleware)) {
            $str = $middleware;
            $middleware = $this->container->get($middleware);

            if (!$middleware instanceof MiddlewareInterface) {
                throw new RuntimeException(sprintf(
                    'Invalid middleware entry [%s], middleware must implement %s',
                    $str,
                    MiddlewareInterface::class
                ));
            }
        }

        return $middleware;
    }
}
