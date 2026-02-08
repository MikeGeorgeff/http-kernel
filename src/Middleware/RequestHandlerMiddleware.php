<?php

namespace Georgeff\HttpKernel\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RequestHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RequestHandlerInterface $handler) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
