<?php

namespace Invertus\Http\Client;

use Closure;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Invertus\Http\Extractable\RetryHandler;
use JsonSerializable;
use Invertus\Http\Extractable\Str;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Rize\UriTemplate\UriTemplate;
use RuntimeException;

class PendingRequest
{
    /**
     * The factory instance.
     *
     * @var \Invertus\Http\Client\Factory|null
     */
    protected $factory;

    /**
     * The Guzzle client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * The Guzzle HTTP handler.
     *
     * @var callable
     */
    protected $handler;

    /**
     * The base URL for the request.
     *
     * @var string
     */
    protected $baseUrl = '';

    /**
     * The parameters that can be substituted into the URL.
     *
     * @var array
     */
    protected $urlParameters = [];

    /**
     * The request body format.
     *
     * @var string
     */
    protected $bodyFormat;

    /**
     * The raw body for the request.
     *
     * @var string
     */
    protected $pendingBody;


    /**
     * The request cookies.
     *
     * @var array
     */
    protected $cookies;

    /**
     * The transfer stats for the request.
     *
     * @var \GuzzleHttp\TransferStats
     */
    protected $transferStats;

    /**
     * The request options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * A callback to run when throwing if a server or client error occurs.
     *
     * @var \Closure
     */
    protected $throwCallback;

    /**
     * A callback to check if an exception should be thrown when a server or client error occurs.
     *
     * @var \Closure
     */
    protected $throwIfCallback;

    /**
     * The number of times to try the request.
     *
     * @var int
     */
    protected $tries = 1;

    /**
     * The number of milliseconds to wait between retries.
     *
     * @var Closure|int
     */
    protected $retryDelay = 100;

    /**
     * Whether to throw an exception when all retries fail.
     *
     * @var bool
     */
    protected $retryThrow = true;

    /**
     * The callback that will determine if the request should be retried.
     *
     * @var callable|null
     */
    protected $retryWhenCallback = null;

    /**
     * The callbacks that should execute before the request is sent.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $beforeSendingCallbacks;

    /**
     * The stub callables that will handle requests.
     *
     * @var \Illuminate\Support\Collection|null
     */
    protected $stubCallbacks;

    /**
     * Indicates that an exception should be thrown if any request is not faked.
     *
     * @var bool
     */
    protected $preventStrayRequests = false;

    /**
     * Whether the requests should be asynchronous.
     *
     * @var bool
     */
    protected $async = false;

    /**
     * The pending request promise.
     *
     * @var \GuzzleHttp\Promise\PromiseInterface
     */
    protected $promise;

    /**
     * The sent request object, if a request has been made.
     *
     * @var \Invertus\Http\Client\Request|null
     */
    protected $request;

    /**
     * The Guzzle request options that are mergable via array_merge_recursive.
     *
     * @var array
     */
    protected $mergableOptions = [
        'cookies',
        'form_params',
        'headers',
        'json',
        'multipart',
        'query',
    ];

    /**
     * Create a new HTTP Client instance.
     *
     * @param  \Invertus\Http\Client\Factory|null  $factory
     * @return void
     */
    public function __construct(Factory $factory = null)
    {
        $this->factory = $factory;

        $this->asJson();

        $this->options = [
            'connect_timeout' => 10,
            'http_errors' => false,
            'timeout' => 30,
        ];

        $this->beforeSendingCallbacks = collect([function (Request $request, array $options, PendingRequest $pendingRequest) {
            $pendingRequest->request = $request;
            $pendingRequest->cookies = $options['cookies'];
        }]);
    }

    /**
     * Set the base URL for the pending request.
     *
     * @param  string  $url
     * @return $this
     */
    public function baseUrl(string $url)
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Attach a raw body to the request.
     *
     * @param  string  $content
     * @param  string  $contentType
     * @return $this
     */
    public function withBody($content, $contentType = 'application/json')
    {
        $this->bodyFormat('body');

        $this->pendingBody = $content;

        $this->contentType($contentType);

        return $this;
    }

    /**
     * Indicate the request contains JSON.
     *
     * @return $this
     */
    public function asJson()
    {
        return $this->bodyFormat('json')->contentType('application/json');
    }

    /**
     * Indicate the request contains form parameters.
     *
     * @return $this
     */
    public function asForm()
    {
        return $this->bodyFormat('form_params')->contentType('application/x-www-form-urlencoded');
    }

    /**
     * Indicate the request is a multi-part form request.
     *
     * @return $this
     */
    public function asMultipart()
    {
        return $this->bodyFormat('multipart');
    }

    /**
     * Specify the body format of the request.
     *
     * @param  string  $format
     * @return $this
     */
    public function bodyFormat(string $format)
    {
        return tap($this, function () use ($format) {
            $this->bodyFormat = $format;
        });
    }

    /**
     * Set the given query parameters in the request URI.
     *
     * @param  array  $parameters
     * @return $this
     */
    public function withQueryParameters(array $parameters)
    {
        return tap($this, function () use ($parameters) {
            $this->options = array_merge_recursive($this->options, [
                'query' => $parameters,
            ]);
        });
    }

    /**
     * Specify the request's content type.
     *
     * @param  string  $contentType
     * @return $this
     */
    public function contentType(string $contentType)
    {
        return tap($this, function () use ($contentType) {
            $this->options['headers']['Content-Type'] = $contentType;
        });
    }

    /**
     * Indicate that JSON should be returned by the server.
     *
     * @return $this
     */
    public function acceptJson()
    {
        return $this->accept('application/json');
    }

    /**
     * Indicate the type of content that should be returned by the server.
     *
     * @param  string  $contentType
     * @return $this
     */
    public function accept($contentType)
    {
        return $this->withHeaders(['Accept' => $contentType]);
    }

    /**
     * Add the given headers to the request.
     *
     * @param  array  $headers
     * @return $this
     */
    public function withHeaders(array $headers)
    {
        return tap($this, function () use ($headers) {
            $this->options = array_merge_recursive($this->options, [
                'headers' => $headers,
            ]);
        });
    }

    /**
     * Add the given header to the request.
     *
     * @param  string  $name
     * @param  mixed  $value
     * @return $this
     */
    public function withHeader($name, $value)
    {
        return $this->withHeaders([$name => $value]);
    }

    /**
     * Replace the given headers on the request.
     *
     * @param  array  $headers
     * @return $this
     */
    public function replaceHeaders(array $headers)
    {
        return tap($this, function () use ($headers) {
            $this->options['headers'] = array_merge($this->options['headers'] ?? [], $headers);
        });
    }

    /**
     * Specify the basic authentication username and password for the request.
     *
     * @param  string  $username
     * @param  string  $password
     * @return $this
     */
    public function withBasicAuth(string $username, string $password)
    {
        return tap($this, function () use ($username, $password) {
            $this->options['auth'] = [$username, $password];
        });
    }

    /**
     * Specify the digest authentication username and password for the request.
     *
     * @param  string  $username
     * @param  string  $password
     * @return $this
     */
    public function withDigestAuth($username, $password)
    {
        return tap($this, function () use ($username, $password) {
            $this->options['auth'] = [$username, $password, 'digest'];
        });
    }

    /**
     * Specify an authorization token for the request.
     *
     * @param  string  $token
     * @param  string  $type
     * @return $this
     */
    public function withToken($token, $type = 'Bearer')
    {
        return tap($this, function () use ($token, $type) {
            $this->options['headers']['Authorization'] = trim($type.' '.$token);
        });
    }

    /**
     * Specify the user agent for the request.
     *
     * @param  string|bool  $userAgent
     * @return $this
     */
    public function withUserAgent($userAgent)
    {
        return tap($this, function () use ($userAgent) {
            $this->options['headers']['User-Agent'] = trim($userAgent);
        });
    }

    /**
     * Specify the URL parameters that can be substituted into the request URL.
     *
     * @param  array  $parameters
     * @return $this
     */
    public function withUrlParameters(array $parameters = [])
    {
        return tap($this, function () use ($parameters) {
            $this->urlParameters = $parameters;
        });
    }

    /**
     * Specify the cookies that should be included with the request.
     *
     * @param  array  $cookies
     * @param  string  $domain
     * @return $this
     */
    public function withCookies(array $cookies, string $domain)
    {
        return tap($this, function () use ($cookies, $domain) {
            $this->options = array_merge_recursive($this->options, [
                'cookies' => CookieJar::fromArray($cookies, $domain),
            ]);
        });
    }

    /**
     * Specify the maximum number of redirects to allow.
     *
     * @param  int  $max
     * @return $this
     */
    public function maxRedirects(int $max)
    {
        return tap($this, function () use ($max) {
            $this->options['allow_redirects']['max'] = $max;
        });
    }

    /**
     * Indicate that redirects should not be followed.
     *
     * @return $this
     */
    public function withoutRedirecting()
    {
        return tap($this, function () {
            $this->options['allow_redirects'] = false;
        });
    }

    /**
     * Indicate that TLS certificates should not be verified.
     *
     * @return $this
     */
    public function withoutVerifying()
    {
        return tap($this, function () {
            $this->options['verify'] = false;
        });
    }

    /**
     * Specify the path where the body of the response should be stored.
     *
     * @param  string|resource  $to
     * @return $this
     */
    public function sink($to)
    {
        return tap($this, function () use ($to) {
            $this->options['sink'] = $to;
        });
    }

    /**
     * Specify the timeout (in seconds) for the request.
     *
     * @param  int  $seconds
     * @return $this
     */
    public function timeout(int $seconds)
    {
        return tap($this, function () use ($seconds) {
            $this->options['timeout'] = $seconds;
        });
    }

    /**
     * Specify the connect timeout (in seconds) for the request.
     *
     * @param  int  $seconds
     * @return $this
     */
    public function connectTimeout(int $seconds)
    {
        return tap($this, function () use ($seconds) {
            $this->options['connect_timeout'] = $seconds;
        });
    }

    /**
     * Specify the number of times the request should be attempted.
     *
     * @param  int  $times
     * @param  Closure|int  $sleepMilliseconds
     * @param  callable|null  $when
     * @param  bool  $throw
     * @return $this
     */
    public function retry(int $times, $sleepMilliseconds = 0, ?callable $when = null, bool $throw = true)
    {
        $this->tries = $times;
        $this->retryDelay = $sleepMilliseconds;
        $this->retryThrow = $throw;
        $this->retryWhenCallback = $when;

        return $this;
    }

    /**
     * Replace the specified options on the request.
     *
     * @param  array  $options
     * @return $this
     */
    public function withOptions(array $options)
    {
        return tap($this, function () use ($options) {
            $this->options = array_replace_recursive(
                array_merge_recursive($this->options, Arr::only($options, $this->mergableOptions)),
                $options
            );
        });
    }


    /**
     * Add a new "before sending" callback to the request.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function beforeSending($callback)
    {
        return tap($this, function () use ($callback) {
            $this->beforeSendingCallbacks[] = $callback;
        });
    }

    /**
     * Throw an exception if a server or client error occurs.
     *
     * @param  callable|null  $callback
     * @return $this
     */
    public function throw(callable $callback = null)
    {
        $this->throwCallback = $callback ?: function () {
            return null;
        };

        return $this;
    }

    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to true.
     *
     * @param  callable|bool  $condition
     * @param  callable|null  $throwCallback
     * @return $this
     */
    public function throwIf($condition)
    {
        if (is_callable($condition)) {
            $this->throwIfCallback = $condition;
        }

        return $condition ? $this->throw(func_get_args()[1] ?? null) : $this;
    }

    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to false.
     *
     * @param  bool  $condition
     * @return $this
     */
    public function throwUnless($condition)
    {
        return $this->throwIf(! $condition);
    }

    /**
     * Issue a GET request to the given URL.
     *
     * @param  string  $url
     * @param  array|string|null  $query
     * @return \Invertus\Http\Client\Response
     */
    public function get(string $url, $query = null)
    {
        return $this->send('GET', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    /**
     * Issue a HEAD request to the given URL.
     *
     * @param  string  $url
     * @param  array|string|null  $query
     * @return \Invertus\Http\Client\Response
     */
    public function head(string $url, $query = null)
    {
        return $this->send('HEAD', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    /**
     * Issue a POST request to the given URL.
     *
     * @param  string  $url
     * @param  array  $data
     * @return \Invertus\Http\Client\Response
     */
    public function post(string $url, $data = [])
    {
        return $this->send('POST', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a PATCH request to the given URL.
     *
     * @param  string  $url
     * @param  array  $data
     * @return \Invertus\Http\Client\Response
     */
    public function patch(string $url, $data = [])
    {
        return $this->send('PATCH', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a PUT request to the given URL.
     *
     * @param  string  $url
     * @param  array  $data
     * @return \Invertus\Http\Client\Response
     */
    public function put(string $url, $data = [])
    {
        return $this->send('PUT', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a DELETE request to the given URL.
     *
     * @param  string  $url
     * @param  array  $data
     * @return \Invertus\Http\Client\Response
     */
    public function delete(string $url, $data = [])
    {
        return $this->send('DELETE', $url, empty($data) ? [] : [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Send the request to the given URL.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  array  $options
     * @return \Invertus\Http\Client\Response
     *
     * @throws \Exception
     */
    public function send(string $method, string $url, array $options = [])
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = ltrim(rtrim($this->baseUrl, '/').'/'.ltrim($url, '/'), '/');
        }

        $url = $this->expandUrlParameters($url);

        $options = $this->parseHttpOptions($options);

        $this->pendingBody = null;

        if ($this->async) {
            return $this->makePromise($method, $url, $options);
        }

        $shouldRetry = null;

        return RetryHandler::retry($this->tries ?? 1, function ($attempt) use ($method, $url, $options, &$shouldRetry) {
            try {
                return tap(new Response($this->sendRequest($method, $url, $options)), function ($response) use ($attempt, &$shouldRetry) {
                    $this->populateResponse($response);

                    if (! $response->successful()) {
                        try {
                            $shouldRetry = $this->retryWhenCallback ? call_user_func($this->retryWhenCallback, $response->toException(), $this) : true;
                        } catch (Exception $exception) {
                            $shouldRetry = false;

                            throw $exception;
                        }

                        if ($this->throwCallback &&
                            ($this->throwIfCallback === null ||
                             call_user_func($this->throwIfCallback, $response))) {
                            $response->throw($this->throwCallback);
                        }

                        if ($attempt < $this->tries && $shouldRetry) {
                            $response->throw();
                        }

                        if ($this->tries > 1 && $this->retryThrow) {
                            $response->throw();
                        }
                    }
                });
            } catch (ConnectException $e) {
                throw new ConnectionException($e->getMessage(), 0, $e);
            }
        }, $this->retryDelay ?? 100, function ($exception) use (&$shouldRetry) {
            $result = $shouldRetry ?? ($this->retryWhenCallback ? call_user_func($this->retryWhenCallback, $exception, $this) : true);

            $shouldRetry = null;

            return $result;
        });
    }

    /**
     * Substitute the URL parameters in the given URL.
     *
     * @param  string  $url
     * @return string
     */
    protected function expandUrlParameters(string $url)
    {
        $template = new UriTemplate();

        return $template->expand($url, $this->urlParameters);
    }

    /**
     * Parse the given HTTP options and set the appropriate additional options.
     *
     * @param  array  $options
     * @return array
     */
    protected function parseHttpOptions(array $options)
    {
        if (isset($options[$this->bodyFormat])) {
            if ($this->bodyFormat === 'multipart') {
                $options[$this->bodyFormat] = $this->parseMultipartBodyFormat($options[$this->bodyFormat]);
            } elseif ($this->bodyFormat === 'body') {
                $options[$this->bodyFormat] = $this->pendingBody;
            }
        } else {
            $options[$this->bodyFormat] = $this->pendingBody;
        }

        return collect($options)->map(function ($value, $key) {
            if ($key === 'json' && $value instanceof JsonSerializable) {
                return $value;
            }

            return $value instanceof Arrayable ? $value->toArray() : $value;
        })->all();
    }

    /**
     * Parse multi-part form data.
     *
     * @param  array  $data
     * @return array|array[]
     */
    protected function parseMultipartBodyFormat(array $data)
    {
        return collect($data)->map(function ($value, $key) {
            return is_array($value) ? $value : ['name' => $key, 'contents' => $value];
        })->values()->all();
    }

    /**
     * Send an asynchronous request to the given URL.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  array  $options
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    protected function makePromise(string $method, string $url, array $options = [])
    {
        return $this->promise = $this->sendRequest($method, $url, $options)
            ->then(function (MessageInterface $message) {
                return tap(new Response($message), function ($response) {
                    $this->populateResponse($response);
                });
            })
            ->otherwise(function (TransferException $e) {
                return $e instanceof RequestException && $e->hasResponse() ? $this->populateResponse(new Response($e->getResponse())) : $e;
            });
    }

    /**
     * Send a request either synchronously or asynchronously.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  array  $options
     * @return \Psr\Http\Message\MessageInterface|\GuzzleHttp\Promise\PromiseInterface
     *
     * @throws \Exception
     */
    protected function sendRequest(string $method, string $url, array $options = [])
    {
        $clientMethod = $this->async ? 'requestAsync' : 'request';

        $laravelData = $this->parseRequestData($method, $url, $options);

        $onStats = function ($transferStats) {
            if (($callback = ($this->options['on_stats'] ?? false)) instanceof Closure) {
                $transferStats = $callback($transferStats) ?: $transferStats;
            }

            $this->transferStats = $transferStats;
        };

        return $this->buildClient()->$clientMethod($method, $url, $this->mergeOptions([
            'laravel_data' => $laravelData,
            'on_stats' => $onStats,
        ], $options));
    }

    /**
     * Get the request data as an array so that we can attach it to the request for convenient assertions.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  array  $options
     * @return array
     */
    protected function parseRequestData($method, $url, array $options)
    {
        if ($this->bodyFormat === 'body') {
            return [];
        }

        $data = $options[$this->bodyFormat] ?? $options['query'] ?? [];

        if (empty($data) && $method === 'GET' && Str::contains($url, '?')) {
            $data = (string) Str::after($url, '?');
        }

        if (is_string($data)) {
            parse_str($data, $parsedData);

            $data = is_array($parsedData) ? $parsedData : [];
        }

        if ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Populate the given response with additional data.
     *
     * @param  \Invertus\Http\Client\Response  $response
     * @return \Invertus\Http\Client\Response
     */
    protected function populateResponse(Response $response)
    {
        $response->cookies = $this->cookies;

        $response->transferStats = $this->transferStats;

        return $response;
    }

    /**
     * Build the Guzzle client.
     *
     * @return \GuzzleHttp\Client
     */
    public function buildClient()
    {
        return $this->client ?? $this->createClient($this->buildHandlerStack());
    }

    /**
     * Create new Guzzle client.
     *
     * @param  \GuzzleHttp\HandlerStack  $handlerStack
     * @return \GuzzleHttp\Client
     */
    public function createClient($handlerStack)
    {
        return new Client([
            'handler' => $handlerStack,
            'cookies' => true,
        ]);
    }

    /**
     * Build the Guzzle client handler stack.
     *
     * @return \GuzzleHttp\HandlerStack
     */
    public function buildHandlerStack()
    {
        return $this->pushHandlers(HandlerStack::create($this->handler));
    }

    /**
     * Add the necessary handlers to the given handler stack.
     *
     * @param  \GuzzleHttp\HandlerStack  $handlerStack
     * @return \GuzzleHttp\HandlerStack
     */
    public function pushHandlers($handlerStack)
    {
        return tap($handlerStack, function ($stack) {
            $stack->push($this->buildBeforeSendingHandler());
            $stack->push($this->buildRecorderHandler());
            $stack->push($this->buildStubHandler());
        });
    }

    /**
     * Build the before sending handler.
     *
     * @return \Closure
     */
    public function buildBeforeSendingHandler()
    {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                return $handler($this->runBeforeSendingCallbacks($request, $options), $options);
            };
        };
    }

    /**
     * Build the recorder handler.
     *
     * @return \Closure
     */
    public function buildRecorderHandler()
    {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                $promise = $handler($request, $options);

                return $promise->then(function ($response) use ($request, $options) {
                    if ($this->factory) {
                        $this->factory->recordRequestResponsePair(
                            (new Request($request))->withData($options['laravel_data']),
                            new Response($response)
                        );
                    }

                    return $response;
                });
            };
        };
    }

    /**
     * Build the stub handler.
     *
     * @return \Closure
     */
    public function buildStubHandler()
    {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                $response = ($this->stubCallbacks ?? collect())
                     ->map
                     ->__invoke((new Request($request))->withData($options['laravel_data']), $options)
                     ->filter()
                     ->first();

                if (is_null($response)) {
                    if ($this->preventStrayRequests) {
                        throw new RuntimeException('Attempted request to ['.(string) $request->getUri().'] without a matching fake.');
                    }

                    return $handler($request, $options);
                }

                return is_array($response) ? Factory::response($response) : $response;
            };
        };
    }

    /**
     * Execute the "before sending" callbacks.
     *
     * @param  \GuzzleHttp\Psr7\RequestInterface  $request
     * @param  array  $options
     * @return \GuzzleHttp\Psr7\RequestInterface
     */
    public function runBeforeSendingCallbacks($request, array $options)
    {
        return tap($request, function (&$request) use ($options) {
            $this->beforeSendingCallbacks->each(function ($callback) use (&$request, $options) {
                $callbackResult = call_user_func(
                    $callback, (new Request($request))->withData($options['laravel_data']), $options, $this
                );

                if ($callbackResult instanceof RequestInterface) {
                    $request = $callbackResult;
                } elseif ($callbackResult instanceof Request) {
                    $request = $callbackResult->toPsrRequest();
                }
            });
        });
    }

    /**
     * Replace the given options with the current request options.
     *
     * @param  array  ...$options
     * @return array
     */
    public function mergeOptions(...$options)
    {
        return array_replace_recursive(
            array_merge_recursive($this->options, Arr::only($options, $this->mergableOptions)),
            ...$options
        );
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function stub($callback)
    {
        $this->stubCallbacks = collect($callback);

        return $this;
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
     * Toggle asynchronicity in requests.
     *
     * @param  bool  $async
     * @return $this
     */
    public function async(bool $async = true)
    {
        $this->async = $async;

        return $this;
    }

    /**
     * Get the pending request options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}
