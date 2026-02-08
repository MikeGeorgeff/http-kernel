<?php

namespace Georgeff\HttpKernel;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    /**
     * Send the response to the client
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    public function emit(ResponseInterface $response): void;
}
