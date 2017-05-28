<?php

namespace Kelunik\StreamingJson\Test;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\PendingReadException;
use function Amp\Iterator\fromIterable;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use Kelunik\StreamingJson\StreamingJsonParser;

class StreamingJsonParserTest extends TestCase {
    public function test() {
        Loop::run(function () {
            $payload = implode("\r\n", [
                \json_encode(["foo" => "bar"]),
                \json_encode(["foo" => "baz"]),
                \json_encode(["foo" => "random-longer-value"]),
            ]);

            $stream = new IteratorStream(fromIterable(\str_split($payload, 8), 10));
            $parser = new StreamingJsonParser($stream);

            $i = 0;

            while (yield $parser->advance()) {
                $current = $parser->getCurrent();

                $this->assertInternalType("object", $current);
                $this->assertObjectHasAttribute("foo", $current);

                $i++;
            }

            $this->assertSame(3, $i);
        });
    }

    public function testBackpressure() {
        // Reads exactly the first item and stops then when no item is consumed
        $this->expectOutputString(\str_repeat("r", 15));

        Loop::run(function () {
            $payload = implode("\r\n", [
                \json_encode(["foo" => "bar"]),
                \json_encode(["foo" => "baz"]),
                \json_encode(["foo" => "random-longer-value"]),
            ]);

            $stream = new class(new IteratorStream(fromIterable(\str_split($payload, 1), 1))) implements InputStream {
                private $inputStream;

                public function __construct(InputStream $inputStream) {
                    $this->inputStream = $inputStream;
                }

                public function read(): Promise {
                    echo "r";
                    return $this->inputStream->read();
                }
            };

            $parser = new StreamingJsonParser($stream);

            Loop::delay(1000, function () use ($payload, $parser) {
                Loop::stop();
            });
        });
    }
}