<?php

namespace Georgeff\HttpKernel\Exception;

use Psr\Http\Message\ServerRequestInterface;

final class TooManyRequestsHttpException extends HttpException
{
    protected string $title = 'Too Many Requests';
    protected int $status = 429;

    public function __construct(
        ServerRequestInterface $request,
        string $message = '',
        private readonly int|null $retryAfter = null
    ) {
        parent::__construct($request, $message);
    }

    public function getRetryAfter(): int|null
    {
        return $this->retryAfter;
    }
}
