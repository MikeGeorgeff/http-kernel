<?php

namespace Georgeff\HttpKernel\Test\Exception;

use Georgeff\HttpKernel\Exception\HttpException;
use Georgeff\HttpKernel\Exception\HttpExceptionInterface;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class HttpExceptionTest extends TestCase
{
    public function test_implements_http_exception_interface(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new HttpException($request);

        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
    }

    public function test_returns_request(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new HttpException($request);

        $this->assertSame($request, $exception->getRequest());
    }

    public function test_default_title(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new HttpException($request);

        $this->assertSame('Internal Server Error', $exception->getTitle());
    }

    public function test_default_status_code(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new HttpException($request);

        $this->assertSame(500, $exception->getStatusCode());
    }

    public function test_default_message_is_empty(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new HttpException($request);

        $this->assertSame('', $exception->getMessage());
    }

    public function test_custom_message(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new HttpException($request, 'Something went wrong');

        $this->assertSame('Something went wrong', $exception->getMessage());
    }
}
