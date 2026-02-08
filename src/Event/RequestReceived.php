<?php

namespace Georgeff\HttpKernel\Event;

use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Event\KernelEvent;
use Psr\Http\Message\ServerRequestInterface;

class RequestReceived extends KernelEvent
{
    public function __construct(KernelInterface $kernel, public readonly ServerRequestInterface $request)
    {
        parent::__construct($kernel);
    }
}
