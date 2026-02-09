# HTTP Kernel

A PSR-15 HTTP kernel built on top of [`georgeff/kernel`](https://github.com/MikeGeorgeff/kernel). Provides routing, a global middleware stack, per-route middleware, exception handling, and response emission.

## Installation

```bash
composer require georgeff/http-kernel
```

## Quick Start

```php
use Georgeff\HttpKernel\HttpKernel;
use Georgeff\Kernel\Environment;

$kernel = new HttpKernel(Environment::Production);

$kernel->addMiddleware(SessionMiddleware::class);

$kernel->addRoute('GET', '/users/{id}', UserHandler::class);

$kernel->withExceptionHandler(function (HttpExceptionInterface $e) {
    return new JsonResponse([
        'error' => $e->getTitle(),
        'message' => $e->getMessage(),
    ], $e->getStatusCode());
});

$kernel->boot();
$kernel->run();
```

## Routing

Routes are registered before boot via `addRoute()`. The handler can be a `RequestHandlerInterface` instance or a string service ID resolved from the container.

```php
// Single method
$kernel->addRoute('GET', '/users', ListUsersHandler::class);

// Multiple methods
$kernel->addRoute(['GET', 'POST'], '/users', UsersHandler::class);
```

Route parameters are available through the matched route object, which is stored as the `__route__` request attribute:

```php
$route = $request->getAttribute('__route__');
$id = $route->getArgument('id');
```

### Per-Route Middleware

Routes support their own middleware stack, processed in FIFO order before the route handler:

```php
$route = $kernel->addRoute('GET', '/admin', AdminHandler::class);
$route->addMiddleware(AuthMiddleware::class)
      ->addMiddleware(RoleMiddleware::class);
```

## Middleware

Global middleware is added before boot and runs on every request in FIFO order:

```php
$kernel->addMiddleware(CorsMiddleware::class);
$kernel->addMiddleware($loggingMiddleware);
```

Middleware can be a `MiddlewareInterface` instance or a string service ID resolved from the container.

## Exception Handling

Register an exception handler to convert exceptions into HTTP responses. Without a handler, exceptions are rethrown. Non-HTTP exceptions are automatically wrapped in `InternalServerErrorHttpException`.

```php
$kernel->withExceptionHandler(function (HttpExceptionInterface $e) {
    return new JsonResponse([
        'error' => $e->getTitle(),
    ], $e->getStatusCode());
});
```

### HTTP Exceptions

The package provides a set of HTTP exception classes:

| Class | Status |
|---|---|
| `BadRequestHttpException` | 400 |
| `UnauthorizedHttpException` | 401 |
| `ForbiddenHttpException` | 403 |
| `NotFoundHttpException` | 404 |
| `MethodNotAllowedHttpException` | 405 |
| `NotAcceptableHttpException` | 406 |
| `RequestTimeoutHttpException` | 408 |
| `ConflictHttpException` | 409 |
| `GoneHttpException` | 410 |
| `UnsupportedMediaTypeHttpException` | 415 |
| `UnprocessableEntityHttpException` | 422 |
| `TooManyRequestsHttpException` | 429 |
| `InternalServerErrorHttpException` | 500 |
| `BadGatewayHttpException` | 502 |
| `ServiceUnavailableHttpException` | 503 |

All extend `HttpException` and implement `HttpExceptionInterface`.

`MethodNotAllowedHttpException` provides `getAllowedMethods()` and `TooManyRequestsHttpException` provides `getRetryAfter()`.

## Events

The kernel dispatches PSR-14 events during the request lifecycle:

| Event | When |
|---|---|
| `RequestReceived` | Start of `handle()` |
| `ResponseReady` | After a response is produced |
| `RequestErrored` | When an exception is caught in `handle()` |
| `KernelTerminating` | During `terminate()`, after response emission |

Events require a `Psr\EventDispatcher\EventDispatcherInterface` passed to the kernel constructor.

## Response Helpers

Convenience response classes under `Georgeff\HttpKernel\Response`:

- `JsonResponse`
- `EmptyResponse`
- `RedirectResponse`

## License

MIT
