<?php

namespace Georgeff\HttpKernel\Exception;

final class ConflictHttpException extends HttpException
{
    protected string $title = 'Conflict';
    protected int $status = 409;
}
