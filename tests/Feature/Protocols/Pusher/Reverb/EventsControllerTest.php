<?php

use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can receive and event trigger', function () {
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode(['some' => 'data']),
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('can receive and event trigger for multiple channels', function () {
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('can return user counts when requested', function () {
    subscribe('presence-test-channel-one');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'user_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{"user_count":1},"test-channel-two":{}}}');
});

it('can return subscription counts when requested', function () {
    subscribe('test-channel-two');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'subscription_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{},"test-channel-two":{"subscription_count":1}}}');
});

it('can ignore a subscriber', function () {
    $connection = connect();
    subscribe('test-channel-two', connection: $connection);
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
    ]));

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'socket_id' => $connection->socketId(),
    ]));

    $connection->assertReceived('{"event":"NewEvent","data":"{\"some\":\"data\"}","channel":"test-channel-two"}', 1);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('validates invalid data', function ($payload) {
    await($this->signedPostRequest('events', $payload));
})
    ->throws(ResponseException::class, exceptionCode: 422)
    ->with([
        [
            [
                'name' => 'NewEvent',
                'channel' => 'test-channel',
            ],
        ],
        [
            [
                'name' => 'NewEvent',
                'channels' => ['test-channel-one', 'test-channel-two'],
            ],
        ],
        [
            [
                'name' => 'NewEvent',
                'channel' => 'test-channel',
                'data' => json_encode(['some' => 'data']),
                'socket_id' => 1234,
            ],
        ],
        [
            [
                'name' => 'NewEvent',
                'channel' => 'test-channel',
                'data' => json_encode(['some' => 'data']),
                'info' => 1234,
            ],
        ],
    ]);

it('can gather user counts when requested', function () {
    $this->usingRedis();

    subscribe('presence-test-channel-one');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'user_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{"user_count":1},"test-channel-two":{}}}');
});

it('can gather subscription counts when requested', function () {
    $this->usingRedis();

    subscribe('test-channel-two');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'subscription_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{},"test-channel-two":{"subscription_count":1}}}');
});
