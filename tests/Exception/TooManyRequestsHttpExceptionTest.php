<?php

namespace Georgeff\HttpKernel\Test\Exception;

use Georgeff\HttpKernel\Exception\TooManyRequestsHttpException;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class TooManyRequestsHttpExceptionTest extends TestCase
{
    public function test_status_code(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new TooManyRequestsHttpException($request);

        $this->assertSame(429, $exception->getStatusCode());
    }

    public function test_title(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new TooManyRequestsHttpException($request);

        $this->assertSame('Too Many Requests', $exception->getTitle());
    }

    public function test_default_retry_after_is_null(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new TooManyRequestsHttpException($request);

        $this->assertNull($exception->getRetryAfter());
    }

    public function test_retry_after(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new TooManyRequestsHttpException($request, '', 120);

        $this->assertSame(120, $exception->getRetryAfter());
    }

    public function test_code_matches_status_code(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new TooManyRequestsHttpException($request);

        $this->assertSame(429, $exception->getCode());
    }

    public function test_previous_exception(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $previous = new \RuntimeException('original');
        $exception = new TooManyRequestsHttpException($request, '', null, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
