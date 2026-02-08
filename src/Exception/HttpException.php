<?php

namespace Georgeff\HttpKernel\Exception;

use Psr\Http\Message\ServerRequestInterface;

class HttpException extends \RuntimeException implements HttpExceptionInterface
{
    protected string $title = 'Internal Server Error';

    protected int $status = 500;

    public function __construct(
        private readonly ServerRequestInterface $request,
        string $message = ''
    ) {
        parent::__construct($message);
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }
}
