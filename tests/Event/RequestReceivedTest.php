<?php

namespace Georgeff\HttpKernel\Test\Event;

use Georgeff\HttpKernel\Event\RequestReceived;
use Georgeff\Kernel\Event\KernelEvent;
use Georgeff\Kernel\KernelInterface;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class RequestReceivedTest extends TestCase
{
    public function test_it_extends_kernel_event(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = ServerRequestFactory::fromGlobals();

        $event = new RequestReceived($kernel, $request);

        $this->assertInstanceOf(KernelEvent::class, $event);
    }

    public function test_it_exposes_kernel(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = ServerRequestFactory::fromGlobals();

        $event = new RequestReceived($kernel, $request);

        $this->assertSame($kernel, $event->kernel);
    }

    public function test_it_exposes_request(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = ServerRequestFactory::fromGlobals();

        $event = new RequestReceived($kernel, $request);

        $this->assertSame($request, $event->request);
    }
}
