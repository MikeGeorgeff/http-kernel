<?php

namespace Georgeff\HttpKernel\Exception;

final class UnprocessableEntityHttpException extends HttpException
{
    protected string $title = 'Unprocessable Entity';
    protected int $status = 422;
}
