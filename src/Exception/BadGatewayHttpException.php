<?php

namespace Georgeff\HttpKernel\Exception;

final class BadGatewayHttpException extends HttpException
{
    protected string $title = 'Bad Gateway';
    protected int $status = 502;
}
