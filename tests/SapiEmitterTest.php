<?php

namespace Georgeff\HttpKernel\Test;

use Georgeff\HttpKernel\EmitterInterface;
use Georgeff\HttpKernel\SapiEmitter;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\TextResponse;
use PHPUnit\Framework\TestCase;

final class SapiEmitterTest extends TestCase
{
    public function test_implements_emitter_interface(): void
    {
        $emitter = new SapiEmitter();

        $this->assertInstanceOf(EmitterInterface::class, $emitter);
    }

    public function test_emits_response_body(): void
    {
        $emitter = new SapiEmitter();
        $response = new TextResponse('Hello, World!');

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('Hello, World!', $output);
    }

    public function test_does_not_emit_body_for_204_response(): void
    {
        $emitter = new SapiEmitter();
        $response = new EmptyResponse(204);

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_does_not_emit_body_for_205_response(): void
    {
        $emitter = new SapiEmitter();
        $response = (new Response())->withStatus(205);

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_does_not_emit_body_for_304_response(): void
    {
        $emitter = new SapiEmitter();
        $response = new EmptyResponse(304);

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_emits_body_in_chunks(): void
    {
        $body = str_repeat('x', 100);
        $emitter = new SapiEmitter(25);
        $response = new TextResponse($body);

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame($body, $output);
    }

    public function test_respects_content_length_header(): void
    {
        $emitter = new SapiEmitter();
        $response = (new TextResponse('Hello, World!'))
            ->withHeader('Content-Length', '5');

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('Hello', $output);
    }

    public function test_does_not_emit_body_for_empty_stream(): void
    {
        $emitter = new SapiEmitter();
        $response = new TextResponse('');

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
