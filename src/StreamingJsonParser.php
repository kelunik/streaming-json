<?php

namespace Kelunik\StreamingJson;

use Amp\ByteStream\InputStream;
use Amp\Emitter;
use Amp\Iterator;
use Amp\Promise;
use Amp\Success;
use ExceptionalJSON\DecodeErrorException;
use function Amp\call;
use function ExceptionalJSON\decode;

class StreamingJsonParser implements Iterator {
    private $assoc;
    private $depth;
    private $options;

    private $source;
    private $emitter;
    private $iterator;
    private $backpressure;

    public function __construct(InputStream $inputStream, bool $assoc = false, $depth = 512, int $options = 0) {
        $this->assoc = $assoc;
        $this->depth = $depth;
        $this->options = $options;

        $this->source = $inputStream;
        $this->emitter = new Emitter;
        $this->iterator = $this->emitter->iterate();
        $this->backpressure = new Success;

        call(function () {
            return $this->pipe();
        })->onResolve(function ($error) {
            $this->handleStreamEnd($error);
        });
    }

    private function pipe(): \Generator {
        $buffer = "";

        while (null !== $chunk = yield $this->source->read()) {
            $buffer .= $chunk;

            while (($pos = \strpos($buffer, "\r\n")) !== false) {
                $this->handleLine(\substr($buffer, 0, $pos));
                $buffer = \substr($buffer, $pos + 1);
            }

            yield $this->backpressure;
        }

        if ($buffer !== "") {
            $this->handleLine($buffer);
        }
    }

    private function handleStreamEnd(\Throwable $error = null) {
        if ($this->emitter === null) { // Previously failed already
            return;
        }

        $emitter = $this->emitter;
        $this->emitter = null;

        if ($error) {
            $emitter->fail($error);
        } else {
            $emitter->complete();
        }
    }

    private function handleLine(string $line) {
        try {
            $decodedLine = decode($line, $this->assoc, $this->depth, $this->options);
            $this->backpressure = $this->emitter->emit($decodedLine);
        } catch (DecodeErrorException $e) {
            $emitter = $this->emitter;
            $this->emitter = null;
            $emitter->fail($e);
        }
    }

    /** @inheritdoc */
    public function advance(): Promise {
        return $this->iterator->advance();
    }

    /** @inheritdoc */
    public function getCurrent() {
        return $this->iterator->getCurrent();
    }
}
