<?php

namespace Georgeff\HttpKernel\Exception;

use Psr\Http\Message\ServerRequestInterface;

final class MethodNotAllowedHttpException extends HttpException
{
    protected string $title = 'Method Not Allowed';
    protected int $status = 405;

    /**
     * @var string[]
     */
    private array $allowedMethods = [];

    /**
     * @param string[] $allowedMethods
     */
    public function __construct(ServerRequestInterface $request, string $message = '', array $allowedMethods = [])
    {
        parent::__construct($request, $message);

        $this->allowedMethods = $allowedMethods;
    }

    /**
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
