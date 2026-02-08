<?php

namespace Georgeff\HttpKernel\Exception;

final class NotAcceptableHttpException extends HttpException
{
    protected string $title = 'Not Acceptable';
    protected int $status = 406;
}
