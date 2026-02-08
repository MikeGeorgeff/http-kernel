<?php

namespace Georgeff\HttpKernel\Exception;

use Throwable;
use Psr\Http\Message\ServerRequestInterface;

final class TooManyRequestsHttpException extends HttpException
{
    protected string $title = 'Too Many Requests';
    protected int $status = 429;

    public function __construct(
        ServerRequestInterface $request,
        string $message = '',
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($request, $message, $previous);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
