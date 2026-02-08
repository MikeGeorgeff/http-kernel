<?php

namespace Georgeff\HttpKernel\Exception;

final class GoneHttpException extends HttpException
{
    protected string $title = 'Gone';
    protected int $status = 410;
}
