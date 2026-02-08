<?php

namespace Georgeff\HttpKernel;

use Georgeff\Kernel\KernelInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Georgeff\Kernel\RunnableKernelInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Georgeff\HttpKernel\Exception\HttpExceptionInterface;

interface HttpKernelInterface extends KernelInterface, RunnableKernelInterface, RequestHandlerInterface
{
    /**
     * Add a route to the collection
     *
     * @param string|string[]                $methods
     * @param string                         $uri
     * @param RequestHandlerInterface|string $handler
     *
     * @return \Georgeff\HttpKernel\Routing\RouteInterface
     */
    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): Routing\RouteInterface;

    /**
     * Add a middleware to the global stack
     *
     * @param \Psr\Http\Server\MiddlewareInterface|string $middleware
     *
     * @return static
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static;

    /**
     * Add an exception handler to the kernel
     *
     * @param callable(HttpExceptionInterface): ResponseInterface $handler
     *
     * @return static
     */
    public function withExceptionHandler(callable $handler): static;

    /**
     * Terminate a request/response cycle
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     *
     * @return void
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void;
}
