<?php

namespace Georgeff\HttpKernel;

use Throwable;
use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\KernelException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Georgeff\HttpKernel\Exception\HttpExceptionInterface;
use Georgeff\HttpKernel\Exception\InternalServerErrorHttpException;

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
     * Exception handler
     *
     * @var null|callable(HttpExceptionInterface): ResponseInterface
     */
    protected $exceptionHandler = null;

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        $this->onBooting(function (): void {
            if ([] !== $this->routes) {
                $routerInterface = Routing\RouterInterface::class;

                $this->addDefinition($routerInterface, new Routing\RouterFactory($this->routes), true);

                $this->middleware[] = $routerInterface;
            }

            $this->addDefinition(EmitterInterface::class, fn() => new SapiEmitter(), true)
                 ->addDefinition(RequestHandlerInterface::class, new RequestHandlerFactory($this->middleware))
                 ->addDefinition(ServerRequestInterface::class, fn() => ServerRequestFactory::fromGlobals(), true);
        });

        parent::boot();
    }

    /**
     * @inheritdoc
     */
    public function run(): int
    {
        /** @var ServerRequestInterface $request */
        $request = $this->getContainer()->get(ServerRequestInterface::class);

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

        try {
            $response = $handler->handle($request);
        } catch (Throwable $e) {
            $this->dispatchKernelEvent(new Event\RequestErrored($this, $e, $request));

            if (!$this->exceptionHandler) {
                throw $e;
            }

            if (!$e instanceof HttpExceptionInterface) {
                $e = new InternalServerErrorHttpException($request, $e->getMessage(), $e);
            }

            $response = ($this->exceptionHandler)($e);
        }

        $this->dispatchKernelEvent(new Event\ResponseReady($this, $request, $response));

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function withExceptionHandler(callable $handler): static
    {
        if ($this->isBooted()) {
            throw new KernelException('Kernel has already booted, cannot register exception handler');
        }

        $this->exceptionHandler = $handler;

        return $this;
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
