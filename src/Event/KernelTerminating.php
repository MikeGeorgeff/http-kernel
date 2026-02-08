<?php

namespace Georgeff\HttpKernel\Event;

use Georgeff\Kernel\Event\KernelEvent;
use Georgeff\Kernel\KernelInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class KernelTerminating extends KernelEvent
{
    public function __construct(
        KernelInterface $kernel,
        public readonly ServerRequestInterface $request,
        public readonly ResponseInterface $response
    ) {
        parent::__construct($kernel);
    }
}
