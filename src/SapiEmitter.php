<?php

namespace Georgeff\HttpKernel;

use Psr\Http\Message\ResponseInterface;

final class SapiEmitter implements EmitterInterface
{
    public function __construct(private int $responseChunkSize = 4096) {}

    public function emit(ResponseInterface $response): void
    {
        $isEmpty = $this->isResponseEmpty($response);

        if (headers_sent() === false) {
            $this->emitHeaders($response);

            $this->emitStatusLine($response);
        }

        if (!$isEmpty) {
            $this->emitBody($response);
        }
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $first = strtolower($name) !== 'set-cookie';

            foreach ($values as $value) {
                $header = sprintf('%s: %s', $name, $value);

                header($header, $first);

                $first = false;
            }
        }
    }

    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusLine = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        header($statusLine, true, $response->getStatusCode());
    }

    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $amountToRead = (int) $response->getHeaderLine('Content-Length');

        if (!$amountToRead) {
            $amountToRead = $body->getSize();
        }

        if ($amountToRead) {
            while ($amountToRead > 0 && !$body->eof()) {
                $length = min($this->responseChunkSize, $amountToRead);

                $data = $body->read($length);

                echo $data;

                $amountToRead -= strlen($data);

                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        } else {
            while (!$body->eof()) {
                echo $body->read($this->responseChunkSize);

                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        }
    }

    private function isResponseEmpty(ResponseInterface $response): bool
    {
        if (in_array($response->getStatusCode(), [204, 205, 304], true)) {
            return true;
        }

        $body = $response->getBody();
        $seekable = $body->isSeekable();

        if ($seekable) {
            $body->rewind();
        }

        return $seekable ? $body->read(1) === '' : $body->eof();
    }
}
