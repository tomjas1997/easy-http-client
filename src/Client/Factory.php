<?php

namespace Invertus\Http\Client;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\TransferStats;
use Invertus\Http\Extractable\Str;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * @method \Invertus\Http\Client\PendingRequest baseUrl(string $url)
 * @method \Invertus\Http\Client\PendingRequest withBody(string $content, string $contentType = 'application/json')
 * @method \Invertus\Http\Client\PendingRequest asJson()
 * @method \Invertus\Http\Client\PendingRequest asForm()
 * @method \Invertus\Http\Client\PendingRequest asMultipart()
 * @method \Invertus\Http\Client\PendingRequest bodyFormat(string $format)
 * @method \Invertus\Http\Client\PendingRequest withQueryParameters(array $parameters)
 * @method \Invertus\Http\Client\PendingRequest contentType(string $contentType)
 * @method \Invertus\Http\Client\PendingRequest acceptJson()
 * @method \Invertus\Http\Client\PendingRequest accept(string $contentType)
 * @method \Invertus\Http\Client\PendingRequest withHeaders(array $headers)
 * @method \Invertus\Http\Client\PendingRequest withHeader(string $name, $value)
 * @method \Invertus\Http\Client\PendingRequest replaceHeaders(array $headers)
 * @method \Invertus\Http\Client\PendingRequest withBasicAuth(string $username, string $password)
 * @method \Invertus\Http\Client\PendingRequest withDigestAuth(string $username, string $password)
 * @method \Invertus\Http\Client\PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method \Invertus\Http\Client\PendingRequest withUserAgent(string|bool $userAgent)
 * @method \Invertus\Http\Client\PendingRequest withUrlParameters(array $parameters = [])
 * @method \Invertus\Http\Client\PendingRequest withCookies(array $cookies, string $domain)
 * @method \Invertus\Http\Client\PendingRequest maxRedirects(int $max)
 * @method \Invertus\Http\Client\PendingRequest withoutRedirecting()
 * @method \Invertus\Http\Client\PendingRequest withoutVerifying()
 * @method \Invertus\Http\Client\PendingRequest sink(string|resource $to)
 * @method \Invertus\Http\Client\PendingRequest timeout(int $seconds)
 * @method \Invertus\Http\Client\PendingRequest connectTimeout(int $seconds)
 * @method \Invertus\Http\Client\PendingRequest retry(int $times, Closure|int $sleepMilliseconds = 0, ?callable $when = null, bool $throw = true)
 * @method \Invertus\Http\Client\PendingRequest withOptions(array $options)
 * @method \Invertus\Http\Client\PendingRequest beforeSending(callable $callback)
 * @method \Invertus\Http\Client\PendingRequest throw(callable|null $callback = null)
 * @method \Invertus\Http\Client\PendingRequest throwIf($condition)
 * @method \Invertus\Http\Client\PendingRequest throwUnless(bool $condition)
 * @method \Invertus\Http\Client\Response get(string $url, $query = null)
 * @method \Invertus\Http\Client\Response head(string $url, $query = null)
 * @method \Invertus\Http\Client\Response post(string $url, $data = [])
 * @method \Invertus\Http\Client\Response patch(string $url, $data = [])
 * @method \Invertus\Http\Client\Response put(string $url, $data = [])
 * @method \Invertus\Http\Client\Response delete(string $url, $data = [])
 * @method \Invertus\Http\Client\Response send(string $method, string $url, array $options = [])
 * @method \Invertus\Http\Client\PendingRequest stub(callable $callback)
 * @method \Invertus\Http\Client\PendingRequest async(bool $async = true)
 * @method array getOptions()
 * @method \GuzzleHttp\Promise\PromiseInterface|null getPromise()
 */
class Factory
{
    /**
     * The stub callables that will handle requests.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $stubCallbacks;

    /**
     * Indicates if the factory is recording requests and responses.
     *
     * @var bool
     */
    protected $recording = false;

    /**
     * The recorded response array.
     *
     * @var array
     */
    protected $recorded = [];

    /**
     * All created response sequences.
     *
     * @var array
     */
    protected $responseSequences = [];

    /**
     * Indicates that an exception should be thrown if any request is not faked.
     *
     * @var bool
     */
    protected $preventStrayRequests = false;

    /**
     * Create a new factory instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->stubCallbacks = collect();
    }

    /**
     * Create a new response instance for use during stubbing.
     *
     * @param  array|string|null  $body
     * @param  int  $status
     * @param  array  $headers
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function response($body = null, $status = 200, $headers = [])
    {
        if (is_array($body)) {
            $body = json_encode($body);

            $headers['Content-Type'] = 'application/json';
        }

        $response = new Psr7Response($status, $headers, $body);

        return class_exists(\GuzzleHttp\Promise\Create::class)
            ? \GuzzleHttp\Promise\Create::promiseFor($response)
            : \GuzzleHttp\Promise\promise_for($response);
    }

    /**
     * Get an invokable object that returns a sequence of responses in order for use during stubbing.
     *
     * @param  array  $responses
     * @return \Invertus\Http\Client\ResponseSequence
     */
    public function sequence(array $responses = [])
    {
        return $this->responseSequences[] = new ResponseSequence($responses);
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param  callable|array|null  $callback
     * @return $this
     */
    public function fake($callback = null)
    {
        $this->record();

        $this->recorded = [];

        if (is_null($callback)) {
            $callback = function () {
                return static::response();
            };
        }

        if (is_array($callback)) {
            foreach ($callback as $url => $callable) {
                $this->stubUrl($url, $callable);
            }

            return $this;
        }

        $this->stubCallbacks = $this->stubCallbacks->merge(collect([
            function ($request, $options) use ($callback) {
                $response = $callback instanceof Closure
                    ? $callback($request, $options)
                    : $callback;

                if ($response instanceof PromiseInterface) {
                    $options['on_stats'](new TransferStats(
                        $request->toPsrRequest(),
                        $response->wait()
                    ));
                }

                return $response;
            },
        ]));

        return $this;
    }

    /**
     * Register a response sequence for the given URL pattern.
     *
     * @param  string  $url
     * @return \Invertus\Http\Client\ResponseSequence
     */
    public function fakeSequence($url = '*')
    {
        return tap($this->sequence(), function ($sequence) use ($url) {
            $this->fake([$url => $sequence]);
        });
    }

    /**
     * Stub the given URL using the given callback.
     *
     * @param  string  $url
     * @param  \Invertus\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface|callable  $callback
     * @return $this
     */
    public function stubUrl($url, $callback)
    {
        return $this->fake(function ($request, $options) use ($url, $callback) {
            if (! Str::is(Str::start($url, '*'), $request->url())) {
                return;
            }

            return $callback instanceof Closure || $callback instanceof ResponseSequence
                ? $callback($request, $options)
                : $callback;
        });
    }

    /**
     * Indicate that an exception should be thrown if any request is not faked.
     *
     * @param  bool  $prevent
     * @return $this
     */
    public function preventStrayRequests($prevent = true)
    {
        $this->preventStrayRequests = $prevent;

        return $this;
    }

    /**
     * Indicate that an exception should not be thrown if any request is not faked.
     *
     * @return $this
     */
    public function allowStrayRequests()
    {
        return $this->preventStrayRequests(false);
    }

    /**
     * Begin recording request / response pairs.
     *
     * @return $this
     */
    protected function record()
    {
        $this->recording = true;

        return $this;
    }

    /**
     * Record a request response pair.
     *
     * @param  \Invertus\Http\Client\Request  $request
     * @param  \Invertus\Http\Client\Response  $response
     * @return void
     */
    public function recordRequestResponsePair($request, $response)
    {
        if ($this->recording) {
            $this->recorded[] = [$request, $response];
        }
    }

    /**
     * Assert that a request / response pair was recorded matching a given truth test.
     *
     * @param  callable  $callback
     * @return void
     */
    public function assertSent($callback)
    {
        PHPUnit::assertTrue(
            $this->recorded($callback)->count() > 0,
            'An expected request was not recorded.'
        );
    }

    /**
     * Assert that the given request was sent in the given order.
     *
     * @param  array  $callbacks
     * @return void
     */
    public function assertSentInOrder($callbacks)
    {
        $this->assertSentCount(count($callbacks));

        foreach ($callbacks as $index => $url) {
            $callback = is_callable($url) ? $url : function ($request) use ($url) {
                return $request->url() == $url;
            };

            PHPUnit::assertTrue($callback(
                $this->recorded[$index][0],
                $this->recorded[$index][1]
            ), 'An expected request (#'.($index + 1).') was not recorded.');
        }
    }

    /**
     * Assert that a request / response pair was not recorded matching a given truth test.
     *
     * @param  callable  $callback
     * @return void
     */
    public function assertNotSent($callback)
    {
        PHPUnit::assertFalse(
            $this->recorded($callback)->count() > 0,
            'Unexpected request was recorded.'
        );
    }

    /**
     * Assert that no request / response pair was recorded.
     *
     * @return void
     */
    public function assertNothingSent()
    {
        PHPUnit::assertEmpty(
            $this->recorded,
            'Requests were recorded.'
        );
    }

    /**
     * Assert how many requests have been recorded.
     *
     * @param  int  $count
     * @return void
     */
    public function assertSentCount($count)
    {
        PHPUnit::assertCount($count, $this->recorded);
    }

    /**
     * Assert that every created response sequence is empty.
     *
     * @return void
     */
    public function assertSequencesAreEmpty()
    {
        foreach ($this->responseSequences as $responseSequence) {
            PHPUnit::assertTrue(
                $responseSequence->isEmpty(),
                'Not all response sequences are empty.'
            );
        }
    }

    /**
     * Get a collection of the request / response pairs matching the given truth test.
     *
     * @param  callable  $callback
     * @return \Illuminate\Support\Collection
     */
    public function recorded($callback = null)
    {
        if (empty($this->recorded)) {
            return collect();
        }

        $callback = $callback ?: function () {
            return true;
        };

        return collect($this->recorded)->filter(function ($pair) use ($callback) {
            return $callback($pair[0], $pair[1]);
        });
    }

    /**
     * Create a new pending request instance for this factory.
     *
     * @return \Invertus\Http\Client\PendingRequest
     */
    protected function newPendingRequest()
    {
        return new PendingRequest($this);
    }


    /**
     * Execute a method against a new pending request instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return tap($this->newPendingRequest(), function ($request) {
            $request->stub($this->stubCallbacks)->preventStrayRequests($this->preventStrayRequests);
        })->{$method}(...$parameters);
    }
}
