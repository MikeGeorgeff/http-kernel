<?php

namespace Georgeff\HttpKernel\Exception;

final class BadRequestHttpException extends HttpException
{
    protected string $title = 'Bad Request';
    protected int $status = 400;
}
