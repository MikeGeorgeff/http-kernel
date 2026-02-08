<?php

namespace Georgeff\HttpKernel\Exception;

final class RequestTimeoutHttpException extends HttpException
{
    protected string $title = 'Request Timeout';
    protected int $status = 408;
}
