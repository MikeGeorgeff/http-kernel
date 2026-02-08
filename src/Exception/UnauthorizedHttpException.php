<?php

namespace Georgeff\HttpKernel\Exception;

final class UnauthorizedHttpException extends HttpException
{
    protected string $title = 'Unauthorized';
    protected int $status = 401;
}
