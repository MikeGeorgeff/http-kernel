<?php

namespace Georgeff\HttpKernel\Routing;

use Relay\Relay;
use FastRoute\Dispatcher;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Georgeff\HttpKernel\MiddlewareResolver;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Georgeff\HttpKernel\Exception\NotFoundHttpException;
use Georgeff\HttpKernel\Middleware\RequestHandlerMiddleware;
use Georgeff\HttpKernel\Exception\MethodNotAllowedHttpException;

final class Router implements RouterInterface
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ContainerInterface $container
    ) {}

    /**
     * @inheritdoc
     *
     * @throws \RuntimeException
     * @throws \Georgeff\HttpKernel\Exception\NotFoundHttpException
     * @throws \Georgeff\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $path   = $request->getUri()->getPath();

        $result = $this->dispatcher->dispatch($method, $path);

        if ($result[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            /** @var string[] $allowedMethods */
            $allowedMethods = $result[1];

            $this->handleMethodNotAllowed($request, $method, $path, $allowedMethods);
        }

        if ($result[0] !== Dispatcher::FOUND) {
            $this->handleNotFound($request, $method, $path);
        }

        /** @var RouteInterface $route */
        $route = $result[1];

        /** @var array<string, string> $arguments */
        $arguments = $result[2];

        return $this->handleFound($route, $arguments, $request);
    }

    /**
     * @param string[] $allowedMethods
     *
     * @throws \Georgeff\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    private function handleMethodNotAllowed(ServerRequestInterface $request, string $method, string $uri, array $allowedMethods): never
    {
        throw new MethodNotAllowedHttpException($request, sprintf(
            'Method not allowed for route %s %s',
            $method,
            $uri,
        ), $allowedMethods);
    }

    /**
     * @throws \Georgeff\HttpKernel\Exception\NotFoundHttpException
     */
    private function handleNotFound(ServerRequestInterface $request, string $method, string $uri): never
    {
        throw new NotFoundHttpException($request, sprintf(
            'Route not found %s %s',
            $method,
            $uri
        ));
    }

    /**
     * @param array<string, string> $arguments
     */
    private function handleFound(RouteInterface $route, array $arguments, ServerRequestInterface $request): ResponseInterface
    {
        $handler = $route->getHandler();

        $handler = is_string($handler) ? $this->container->get($handler) : $handler;

        if (!$handler instanceof RequestHandlerInterface) {
            throw new \RuntimeException(sprintf(
                'Invalid route handler [%s], route handlers must implement %s',
                get_debug_type($handler),
                RequestHandlerInterface::class
            ));
        }

        $route = $route->withArguments($arguments);

        $request = $request->withAttribute('__route__', $route);

        if ($middleware = $route->getMiddleware()) {
            $stack = [...$middleware, new RequestHandlerMiddleware($handler)];

            $handler = new Relay($stack, new MiddlewareResolver($this->container));
        }

        return $handler->handle($request);
    }
}
