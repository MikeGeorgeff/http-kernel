<?php

namespace Georgeff\HttpKernel\Routing;

use Psr\Container\ContainerInterface;

final class RouterFactory
{
    /**
     * @param RouteInterface[] $routes
     */
    public function __construct(private readonly array $routes) {}

    public function __invoke(ContainerInterface $container): RouterInterface
    {
        $dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute($route->getMethods(), $route->getPath(), $route);
            }
        });

        return new Router($dispatcher, $container);
    }
}
