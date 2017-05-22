# streaming-json

A streaming JSON parser for Amp.

## Installation

```
composer require kelunik/streaming-json
```

## Usage

```php
$parser = new Parser($inputStream);

while (yield $parser->advance()) {
    $parsedItem = $parser->getCurrent();
}
```

Options can be passed to the constructor just like for `json_decode`. The parser will consume the passed input stream and is itself an `Amp\Iterator` that allows consumption of all parsed items. Any malformed message will fail the parser. If the input stream ends, the parser will try to parse the last item and will complete the iterator successfully or fail it, depending on whether the last item was malformed or not.