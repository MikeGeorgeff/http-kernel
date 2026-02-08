<?php

namespace Georgeff\HttpKernel\Exception;

final class TooManyRequestsHttpException extends HttpException
{
    protected string $title = 'Too Many Requests';
    protected int $status = 429;
}
