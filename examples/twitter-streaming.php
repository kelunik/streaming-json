<?php

use Amp\Artax\Client;
use Amp\Artax\DefaultClient;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Loop;
use Amp\Uri\Uri;
use Kelunik\StreamingJson\StreamingJsonParser;

require __DIR__ . '/../vendor/autoload.php';

Loop::set(new Loop\NativeDriver);

Loop::run(function () use ($argv) {
    // Create an app on https://apps.twitter.com/ and get your app credentials and a token for your own account

    $consumerKey = "";
    $consumerSecret = "";

    $token = "";
    $tokenSecret = "";

    // ^------------------------ FILL IN ----------------------------------------------------------------------

    $nonce = bin2hex(random_bytes(16));
    $timestamp = time();

    $client = new DefaultClient;
    $client->setOption(Client::OP_TRANSFER_TIMEOUT, 0);
    $client->setOption(Client::OP_MAX_BODY_BYTES, 0);

    $params = [
        "oauth_consumer_key" => $consumerKey,
        "oauth_nonce" => $nonce,
        "oauth_signature_method" => "HMAC-SHA1",
        "oauth_timestamp" => $timestamp,
        "oauth_token" => $token,
        "oauth_version" => "1.0",
    ];

    $authorization = "OAuth ";

    foreach ($params as $key => $param) {
        $authorization .= rawurlencode($key) . '="' . rawurlencode($param) . '", ';
    }

    $uri = "https://stream.twitter.com/1.1/statuses/filter.json?track=" . \rawurlencode($argv[1] ?? "php");

    $queryParams = (new Uri($uri))->getAllQueryParameters();
    $encodedParams = [];

    foreach (array_merge($params, $queryParams) as $key => $value) {
        $encodedParams[\rawurlencode($key)] = \rawurlencode(\is_array($value) ? \current($value) : $value);
    }

    ksort($encodedParams);

    $signingData = "POST&" . \rawurlencode(\strtok($uri, "?")) . "&" . \rawurlencode(\http_build_query($encodedParams));
    $signature = base64_encode(hash_hmac("sha1", $signingData, \rawurlencode($consumerSecret) . "&" . \rawurlencode($tokenSecret), true));

    /** @var Response $response */
    $response = yield $client->request(
        (new Request($uri, "POST"))
            ->withHeader("authorization", $authorization . 'oauth_signature="' . \rawurlencode($signature) . '"')
    );

    print "Status: " . $response->getStatus() . PHP_EOL;

    if ($response->getStatus() !== 200) {
        exit(1);
    }

    $parser = new StreamingJsonParser($response->getBody(), true);

    while (yield $parser->advance()) {
        var_dump($parser->getCurrent());
    }

    exit(0);
});
