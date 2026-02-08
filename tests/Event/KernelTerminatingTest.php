<?php

namespace Georgeff\HttpKernel\Test\Event;

use Georgeff\HttpKernel\Event\KernelTerminating;
use Georgeff\Kernel\Event\KernelEvent;
use Georgeff\Kernel\KernelInterface;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

final class KernelTerminatingTest extends TestCase
{
    public function test_it_extends_kernel_event(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = ServerRequestFactory::fromGlobals();
        $response = new TextResponse('ok');

        $event = new KernelTerminating($kernel, $request, $response);

        $this->assertInstanceOf(KernelEvent::class, $event);
    }

    public function test_it_exposes_kernel(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = ServerRequestFactory::fromGlobals();
        $response = new TextResponse('ok');

        $event = new KernelTerminating($kernel, $request, $response);

        $this->assertSame($kernel, $event->kernel);
    }

    public function test_it_exposes_request(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = ServerRequestFactory::fromGlobals();
        $response = new TextResponse('ok');

        $event = new KernelTerminating($kernel, $request, $response);

        $this->assertSame($request, $event->request);
    }

    public function test_it_exposes_response(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = ServerRequestFactory::fromGlobals();
        $response = new TextResponse('ok');

        $event = new KernelTerminating($kernel, $request, $response);

        $this->assertSame($response, $event->response);
    }
}
