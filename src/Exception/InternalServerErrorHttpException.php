<?php

namespace Georgeff\HttpKernel\Exception;

final class InternalServerErrorHttpException extends HttpException
{
    protected string $title = 'Internal Server Error';
    protected int $status = 500;
}
