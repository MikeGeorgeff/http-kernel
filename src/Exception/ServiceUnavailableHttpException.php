<?php

namespace Georgeff\HttpKernel\Exception;

final class ServiceUnavailableHttpException extends HttpException
{
    protected string $title = 'Service Unavailable';
    protected int $status = 503;
}
