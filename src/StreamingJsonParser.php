<?php

namespace Kelunik\StreamingJson;

use Amp\ByteStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\Parser;
use Amp\CallableMaker;
use Amp\Emitter;
use Amp\Iterator;
use Amp\Promise;
use ExceptionalJSON\DecodeErrorException;
use function ExceptionalJSON\decode;

class StreamingJsonParser implements Iterator {
    use CallableMaker;

    private $parser;
    private $emitter;
    private $iterator;

    private $assoc;
    private $depth;
    private $options;

    public function __construct(InputStream $inputStream, bool $assoc = false, $depth = 512, int $options = 0) {
        $this->assoc = $assoc;
        $this->depth = $depth;
        $this->options = $options;

        $this->emitter = new Emitter;
        $this->iterator = $this->emitter->iterate();
        $this->parser = new Parser($this->parse());

        ByteStream\pipe($inputStream, $this->parser)->onResolve($this->callableFromInstanceMethod("handleStreamEnd"));
    }

    private function parse(): \Generator {
        while (true) {
            $line = yield "\r\n";

            if (trim($line) === "") {
                continue;
            }

            $this->handleLine($line);
        }
    }

    private function handleStreamEnd(\Throwable $error = null) {
        if ($this->emitter === null) { // Previously failed already
            return;
        }

        if ($error) {
            $this->emitter->fail($error);
        } else {
            $remainingBuffer = $this->parser->getBuffer();

            if (\trim($remainingBuffer) !== "") {
                $this->handleLine($remainingBuffer);
            }

            // The remaining buffer might not be a complete JSON message and then fail the emitter
            if ($this->emitter) {
                $this->emitter->complete();
            }
        }

        $this->emitter = null;
    }

    private function handleLine(string $line) {
        try {
            $decodedLine = decode($line, $this->assoc, $this->depth, $this->options);
            $this->emitter->emit($decodedLine);
        } catch (DecodeErrorException $e) {
            $this->emitter->fail($e);
            $this->emitter = null;
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
