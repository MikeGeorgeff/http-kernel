<?php

namespace Georgeff\HttpKernel\Event;

use Throwable;
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Event\KernelEvent;
use Psr\Http\Message\ServerRequestInterface;

final class RequestErrored extends KernelEvent
{
    public function __construct(
        KernelInterface $kernel,
        public readonly Throwable $exception,
        public readonly ServerRequestInterface $request
    ) {
        parent::__construct($kernel);
    }
}
