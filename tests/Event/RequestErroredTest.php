<?php

namespace Georgeff\HttpKernel\Test\Event;

use Georgeff\HttpKernel\Event\RequestErrored;
use Georgeff\Kernel\Event\KernelEvent;
use Georgeff\Kernel\KernelInterface;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RequestErroredTest extends TestCase
{
    public function test_it_extends_kernel_event(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $exception = new RuntimeException('test');
        $request = ServerRequestFactory::fromGlobals();

        $event = new RequestErrored($kernel, $exception, $request);

        $this->assertInstanceOf(KernelEvent::class, $event);
    }

    public function test_it_exposes_kernel(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $exception = new RuntimeException('test');
        $request = ServerRequestFactory::fromGlobals();

        $event = new RequestErrored($kernel, $exception, $request);

        $this->assertSame($kernel, $event->kernel);
    }

    public function test_it_exposes_exception(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $exception = new RuntimeException('test');
        $request = ServerRequestFactory::fromGlobals();

        $event = new RequestErrored($kernel, $exception, $request);

        $this->assertSame($exception, $event->exception);
    }

    public function test_it_exposes_request(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $exception = new RuntimeException('test');
        $request = ServerRequestFactory::fromGlobals();

        $event = new RequestErrored($kernel, $exception, $request);

        $this->assertSame($request, $event->request);
    }
}
