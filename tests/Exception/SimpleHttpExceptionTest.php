<?php

namespace Georgeff\HttpKernel\Test\Exception;

use Georgeff\HttpKernel\Exception\BadGatewayHttpException;
use Georgeff\HttpKernel\Exception\BadRequestHttpException;
use Georgeff\HttpKernel\Exception\ConflictHttpException;
use Georgeff\HttpKernel\Exception\ForbiddenHttpException;
use Georgeff\HttpKernel\Exception\GoneHttpException;
use Georgeff\HttpKernel\Exception\HttpException;
use Georgeff\HttpKernel\Exception\InternalServerErrorHttpException;
use Georgeff\HttpKernel\Exception\NotAcceptableHttpException;
use Georgeff\HttpKernel\Exception\NotFoundHttpException;
use Georgeff\HttpKernel\Exception\RequestTimeoutHttpException;
use Georgeff\HttpKernel\Exception\ServiceUnavailableHttpException;
use Georgeff\HttpKernel\Exception\UnauthorizedHttpException;
use Georgeff\HttpKernel\Exception\UnprocessableEntityHttpException;
use Georgeff\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SimpleHttpExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<HttpException>, int, string}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'BadRequest' => [BadRequestHttpException::class, 400, 'Bad Request'];
        yield 'Unauthorized' => [UnauthorizedHttpException::class, 401, 'Unauthorized'];
        yield 'Forbidden' => [ForbiddenHttpException::class, 403, 'Forbidden'];
        yield 'NotFound' => [NotFoundHttpException::class, 404, 'Not Found'];
        yield 'NotAcceptable' => [NotAcceptableHttpException::class, 406, 'Not Acceptable'];
        yield 'Conflict' => [ConflictHttpException::class, 409, 'Conflict'];
        yield 'Gone' => [GoneHttpException::class, 410, 'Gone'];
        yield 'RequestTimeout' => [RequestTimeoutHttpException::class, 408, 'Request Timeout'];
        yield 'UnsupportedMediaType' => [UnsupportedMediaTypeHttpException::class, 415, 'Unsupported Media Type'];
        yield 'UnprocessableEntity' => [UnprocessableEntityHttpException::class, 422, 'Unprocessable Entity'];
        yield 'InternalServerError' => [InternalServerErrorHttpException::class, 500, 'Internal Server Error'];
        yield 'BadGateway' => [BadGatewayHttpException::class, 502, 'Bad Gateway'];
        yield 'ServiceUnavailable' => [ServiceUnavailableHttpException::class, 503, 'Service Unavailable'];
    }

    /**
     * @param class-string<HttpException> $class
     */
    #[DataProvider('exceptionProvider')]
    public function test_status_code(string $class, int $expectedStatus, string $expectedTitle): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new $class($request);

        $this->assertSame($expectedStatus, $exception->getStatusCode());
    }

    /**
     * @param class-string<HttpException> $class
     */
    #[DataProvider('exceptionProvider')]
    public function test_title(string $class, int $expectedStatus, string $expectedTitle): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new $class($request);

        $this->assertSame($expectedTitle, $exception->getTitle());
    }

    /**
     * @param class-string<HttpException> $class
     */
    #[DataProvider('exceptionProvider')]
    public function test_code_matches_status_code(string $class, int $expectedStatus, string $expectedTitle): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new $class($request);

        $this->assertSame($expectedStatus, $exception->getCode());
    }

    /**
     * @param class-string<HttpException> $class
     */
    #[DataProvider('exceptionProvider')]
    public function test_extends_http_exception(string $class, int $expectedStatus, string $expectedTitle): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new $class($request);

        $this->assertInstanceOf(HttpException::class, $exception);
    }
}
