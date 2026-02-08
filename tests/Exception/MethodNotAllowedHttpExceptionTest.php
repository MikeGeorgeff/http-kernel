<?php

namespace Georgeff\HttpKernel\Test\Exception;

use Georgeff\HttpKernel\Exception\MethodNotAllowedHttpException;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class MethodNotAllowedHttpExceptionTest extends TestCase
{
    public function test_status_code(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new MethodNotAllowedHttpException($request);

        $this->assertSame(405, $exception->getStatusCode());
    }

    public function test_title(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new MethodNotAllowedHttpException($request);

        $this->assertSame('Method Not Allowed', $exception->getTitle());
    }

    public function test_default_allowed_methods_is_empty(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new MethodNotAllowedHttpException($request);

        $this->assertSame([], $exception->getAllowedMethods());
    }

    public function test_allowed_methods(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $exception = new MethodNotAllowedHttpException($request, '', ['GET', 'POST']);

        $this->assertSame(['GET', 'POST'], $exception->getAllowedMethods());
    }
}
