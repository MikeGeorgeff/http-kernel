<?php

namespace Georgeff\HttpKernel\Exception;

final class UnsupportedMediaTypeHttpException extends HttpException
{
    protected string $title = 'Unsupported Media Type';
    protected int $status = 415;
}
