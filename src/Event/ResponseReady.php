<?php

namespace Georgeff\HttpKernel\Event;

use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Event\KernelEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ResponseReady extends KernelEvent
{
    public function __construct(
        KernelInterface $kernel,
        public readonly ServerRequestInterface $request,
        public readonly ResponseInterface $response
    ) {
        parent::__construct($kernel);
    }
}
