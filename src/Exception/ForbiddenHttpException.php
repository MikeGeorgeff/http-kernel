<?php

namespace Georgeff\HttpKernel\Exception;

final class ForbiddenHttpException extends HttpException
{
    protected string $title = 'Forbidden';
    protected int $status = 403;
}
