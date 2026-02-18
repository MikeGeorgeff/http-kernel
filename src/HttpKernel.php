<?php

namespace Georgeff\HttpKernel;

use Throwable;
use Georgeff\Kernel\Kernel;
use Georgeff\Kernel\Debug\Profiler;
use Georgeff\Kernel\KernelException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpKernel extends Kernel implements HttpKernelInterface
{
    private bool $shutdown = false;

    private ?Profiler $requestProfile = null;

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
     * @var null|callable(Throwable): ResponseInterface
     */
    protected $exceptionHandler = null;

    private function initRequestProfile(): void
    {
        if (!$this->isDebug()) {
            return;
        }

        $this->requestProfile = new Profiler();

        $this->requestProfile->start();
    }

    /**
     * @throws \Georgeff\Kernel\KernelException
     */
    private function throwIfBooted(string $message): void
    {
        if ($this->isBooted()) {
            throw new KernelException($message);
        }
    }

    /**
     * @throws \Georgeff\Kernel\KernelException
     */
    private function throwIfNotBooted(string $message): void
    {
        if (!$this->isBooted()) {
            throw new KernelException($message);
        }
    }

    /**
     * @throws \Georgeff\Kernel\KernelException
     */
    private function throwIfShutdown(): void
    {
        if ($this->shutdown) {
            throw new KernelException('Kernel is shutdown');
        }
    }

    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        $this->throwIfShutdown();

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
        $this->throwIfShutdown();

        $this->throwIfNotBooted('Kernel cannot run because it has not been booted');

        $this->initRequestProfile();

        $this->requestProfile?->startPhase('requestResolution');

        /** @var ServerRequestInterface $request */
        $request = $this->getContainer()->get(ServerRequestInterface::class);

        $this->requestProfile?->stopPhase('requestResolution');

        $response = $this->handle($request);

        $this->requestProfile?->startPhase('emission');

        /** @var EmitterInterface $emitter */
        $emitter = $this->getContainer()->get(EmitterInterface::class);

        $emitter->emit($response);

        $this->requestProfile?->stopPhase('emission');

        $this->terminate($request, $response);

        $this->requestProfile?->stop();

        return 0;
    }

    /**
     * @inheritdoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->throwIfShutdown();

        $this->throwIfNotBooted('Kernel cannot handle requests because it has not been booted');

        $ownsProfile = null === $this->requestProfile;

        if ($ownsProfile) {
            $this->initRequestProfile();
        }

        $this->requestProfile?->startPhase('handle');

        $this->dispatchKernelEvent(new Event\RequestReceived($this, $request));

        /** @var RequestHandlerInterface $handler */
        $handler = $this->getContainer()->get(RequestHandlerInterface::class);

        try {
            $this->requestProfile?->startPhase('middleware');

            $response = $handler->handle($request);

            $this->requestProfile?->stopPhase('middleware');
        } catch (Throwable $e) {
            $this->requestProfile?->stopPhase('middleware');

            $this->dispatchKernelEvent(new Event\RequestErrored($this, $e, $request));

            if (!$this->exceptionHandler) {
                $this->requestProfile?->stopPhase('handle');

                if ($ownsProfile) {
                    $this->requestProfile?->stop();
                }

                throw $e;
            }

            $this->requestProfile?->startPhase('exceptionHandling');

            $response = ($this->exceptionHandler)($e);

            $this->requestProfile?->stopPhase('exceptionHandling');
        }

        $this->dispatchKernelEvent(new Event\ResponseReady($this, $request, $response));

        $this->requestProfile?->stopPhase('handle');

        if ($ownsProfile) {
            $this->requestProfile?->stop();
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function withExceptionHandler(callable $handler): static
    {
        $this->throwIfBooted('Kernel has already booted, cannot register exception handler');

        $this->exceptionHandler = $handler;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->requestProfile?->startPhase('terminate');

        $this->dispatchKernelEvent(new Event\KernelTerminating($this, $request, $response));

        $this->requestProfile?->stopPhase('terminate');
    }

    /**
     * @inheritdoc
     */
    public function addRoute(array|string $methods, string $uri, RequestHandlerInterface|string $handler): Routing\RouteInterface
    {
        $this->throwIfBooted('Kernel has already booted, cannot add new routes');

        $methods = is_string($methods) ? [$methods] : array_values($methods);

        return $this->routes[] = new Routing\Route($methods, $uri, $handler);
    }

    /**
     * @inheritdoc
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): static
    {
        $this->throwIfBooted('Kernel has already been booted, cannot modify the global middleware stack');

        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function shutdown(): void
    {
        if (!$this->isBooted()) {
            return;
        }

        $this->dispatchKernelEvent(new Event\KernelShutdown($this));

        $this->shutdown = true;
    }

    /**
     * @inheritdoc
     *
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        /** @var array<string, mixed> $info */
        $info = parent::getDebugInfo();

        if (null !== $this->requestProfile) {
            $info['requestProfile'] = $this->requestProfile->getDebugInfo();
        }

        return $info;
    }
}
