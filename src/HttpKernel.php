<?php

namespace Georgeff\HttpKernel;

use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\KernelException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpKernel extends Kernel implements HttpKernelInterface
{
    /**
     * Application routes
     *
     * @var Routing\RouteInterface[]
     */
    protected array $routes = [];

    /**
     * Global middleware stack
     *
     * @var array<MiddlewareInterface|string>
     */
    protected array $middleware = [];

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        if ([] !== $this->routes) {
            $routerInterface = Routing\RouterInterface::class;

            $this->addDefinition($routerInterface, new Routing\RouterFactory($this->routes), true);

            $this->middleware[] = $routerInterface;
        }

        $this->addDefinition(EmitterInterface::class, fn() => new SapiEmitter(), true)
             ->addDefinition(RequestHandlerInterface::class, new RequestHandlerFactory($this->middleware));

        parent::boot();
    }

    /**
     * @inheritdoc
     */
    public function run(): int
    {
        $request = ServerRequestFactory::fromGlobals();

        $response = $this->handle($request);

        /** @var EmitterInterface $emitter */
        $emitter = $this->getContainer()->get(EmitterInterface::class);

        $emitter->emit($response);

        $this->terminate($request, $response);

        return 0;
    }

    /**
     * @inheritdoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->dispatchKernelEvent(new Event\RequestReceived($this, $request));

        /** @var RequestHandlerInterface $handler */
        $handler = $this->getContainer()->get(RequestHandlerInterface::class);

        $response = $handler->handle($request);

        $this->dispatchKernelEvent(new Event\ResponseReady($this, $request, $response));

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->dispatchKernelEvent(new Event\KernelTerminating($this, $request, $response));
    }

    /**
     * @inheritdoc
     */
    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): Routing\RouteInterface
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already booted, cannot add new routes');
        }

        $methods = is_string($methods) ? [$methods] : array_values($methods);

        return $this->routes[] = new Routing\Route($methods, $uri, $handler);
    }

    /**
     * @inheritdoc
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already been booted, cannot modify the global middleware stack');
        }

        $this->middleware[] = $middleware;

        return $this;
    }
}
