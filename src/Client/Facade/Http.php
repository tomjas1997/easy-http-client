<?php

namespace klarnapayment\src\Http\Facade;

use Invertus\Http\Client\Factory;
use Invertus\Http\Client\PendingRequest;
use klarnapayment\src\Http\Client\resource;


/**
 * @method static \Invertus\Http\Client\Factory globalMiddleware(callable $middleware)
 * @method static \Invertus\Http\Client\Factory globalRequestMiddleware(callable $middleware)
 * @method static \Invertus\Http\Client\Factory globalResponseMiddleware(callable $middleware)
 * @method static \GuzzleHttp\Promise\PromiseInterface response(array|string|null $body = null, int $status = 200, array $headers = [])
 * @method static \Invertus\Http\Client\ResponseSequence sequence(array $responses = [])
 * @method static \Invertus\Http\Client\Factory allowStrayRequests()
 * @method static void recordRequestResponsePair(\Invertus\Http\Client\Request $request, \Invertus\Http\Client\Response $response)
 * @method static void assertSent(callable $callback)
 * @method static void assertSentInOrder(array $callbacks)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static void assertSequencesAreEmpty()
 * @method static \Illuminate\Support\Collection recorded(callable $callback = null)
 * @method static \Illuminate\Contracts\Events\Dispatcher|null getDispatcher()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static mixed macroCall(string $method, array $parameters)
 * @method static \Invertus\Http\Client\PendingRequest baseUrl(string $url)
 * @method static \Invertus\Http\Client\PendingRequest withBody(string $content, string $contentType = 'application/json')
 * @method static \Invertus\Http\Client\PendingRequest asJson()
 * @method static \Invertus\Http\Client\PendingRequest asForm()
 * @method static \Invertus\Http\Client\PendingRequest attach(string|array $name, string|resource $contents = '', string|null $filename = null, array $headers = [])
 * @method static \Invertus\Http\Client\PendingRequest asMultipart()
 * @method static \Invertus\Http\Client\PendingRequest bodyFormat(string $format)
 * @method static \Invertus\Http\Client\PendingRequest withQueryParameters(array $parameters)
 * @method static \Invertus\Http\Client\PendingRequest contentType(string $contentType)
 * @method static \Invertus\Http\Client\PendingRequest acceptJson()
 * @method static \Invertus\Http\Client\PendingRequest accept(string $contentType)
 * @method static \Invertus\Http\Client\PendingRequest withHeaders(array $headers)
 * @method static \Invertus\Http\Client\PendingRequest withHeader(string $name, mixed $value)
 * @method static \Invertus\Http\Client\PendingRequest replaceHeaders(array $headers)
 * @method static \Invertus\Http\Client\PendingRequest withBasicAuth(string $username, string $password)
 * @method static \Invertus\Http\Client\PendingRequest withDigestAuth(string $username, string $password)
 * @method static \Invertus\Http\Client\PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method static \Invertus\Http\Client\PendingRequest withUserAgent(string|bool $userAgent)
 * @method static \Invertus\Http\Client\PendingRequest withUrlParameters(array $parameters = [])
 * @method static \Invertus\Http\Client\PendingRequest withCookies(array $cookies, string $domain)
 * @method static \Invertus\Http\Client\PendingRequest maxRedirects(int $max)
 * @method static \Invertus\Http\Client\PendingRequest withoutRedirecting()
 * @method static \Invertus\Http\Client\PendingRequest withoutVerifying()
 * @method static \Invertus\Http\Client\PendingRequest sink(string|resource $to)
 * @method static \Invertus\Http\Client\PendingRequest timeout(int $seconds)
 * @method static \Invertus\Http\Client\PendingRequest connectTimeout(int $seconds)
 * @method static \Invertus\Http\Client\PendingRequest retry(int $times, \Closure|int $sleepMilliseconds = 0, callable|null $when = null, bool $throw = true)
 * @method static \Invertus\Http\Client\PendingRequest withOptions(array $options)
 * @method static \Invertus\Http\Client\PendingRequest withMiddleware(callable $middleware)
 * @method static \Invertus\Http\Client\PendingRequest withRequestMiddleware(callable $middleware)
 * @method static \Invertus\Http\Client\PendingRequest withResponseMiddleware(callable $middleware)
 * @method static \Invertus\Http\Client\PendingRequest beforeSending(callable $callback)
 * @method static \Invertus\Http\Client\PendingRequest throw(callable|null $callback = null)
 * @method static \Invertus\Http\Client\PendingRequest throwIf(callable|bool $condition, callable|null $throwCallback = null)
 * @method static \Invertus\Http\Client\PendingRequest throwUnless(bool $condition)
 * @method static \Invertus\Http\Client\PendingRequest dump()
 * @method static \Invertus\Http\Client\PendingRequest dd()
 * @method static \Invertus\Http\Client\Response get(string $url, array|string|null $query = null)
 * @method static \Invertus\Http\Client\Response head(string $url, array|string|null $query = null)
 * @method static \Invertus\Http\Client\Response post(string $url, array $data = [])
 * @method static \Invertus\Http\Client\Response patch(string $url, array $data = [])
 * @method static \Invertus\Http\Client\Response put(string $url, array $data = [])
 * @method static \Invertus\Http\Client\Response delete(string $url, array $data = [])
 * @method static array pool(callable $callback)
 * @method static \Invertus\Http\Client\Response send(string $method, string $url, array $options = [])
 * @method static \GuzzleHttp\Client buildClient()
 * @method static \GuzzleHttp\Client createClient(\GuzzleHttp\HandlerStack $handlerStack)
 * @method static \GuzzleHttp\HandlerStack buildHandlerStack()
 * @method static \GuzzleHttp\HandlerStack pushHandlers(\GuzzleHttp\HandlerStack $handlerStack)
 * @method static \Closure buildBeforeSendingHandler()
 * @method static \Closure buildRecorderHandler()
 * @method static \Closure buildStubHandler()
 * @method static \GuzzleHttp\Psr7\RequestInterface runBeforeSendingCallbacks(\GuzzleHttp\Psr7\RequestInterface $request, array $options)
 * @method static array mergeOptions(array ...$options)
 * @method static \Invertus\Http\Client\PendingRequest stub(callable $callback)
 * @method static \Invertus\Http\Client\PendingRequest async(bool $async = true)
 * @method static \GuzzleHttp\Promise\PromiseInterface|null getPromise()
 * @method static \Invertus\Http\Client\PendingRequest setClient(\GuzzleHttp\Client $client)
 * @method static \Invertus\Http\Client\PendingRequest setHandler(callable $handler)
 * @method static array getOptions()
 * @method static \Invertus\Http\Client\PendingRequest|mixed when(\Closure|mixed|null $value = null, callable|null $callback = null, callable|null $default = null)
 * @method static \Invertus\Http\Client\PendingRequest|mixed unless(\Closure|mixed|null $value = null, callable|null $callback = null, callable|null $default = null)
 *
 * @see \Invertus\Http\Client\Factory
 */
class Http
{
    private static $instance;

    public static function getInstance(): Factory
    {
        if (is_null(self::$instance)) {
            self::$instance = new Factory();
        }

        return self::$instance;
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        return self::getInstance()->$method(...$args);
    }
}