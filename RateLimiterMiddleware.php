<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;

class RateLimiterMiddleware
{
    /** @var \Spatie\GuzzleRateLimiterMiddleware\RateLimiter */
    protected $rateLimiter;

    private function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    public static function perSecond(int $limit, ?Store $store = null, ?Deferrer $deferrer = null): RateLimiterMiddleware
    {
        $rateLimiter = new RateLimiter(
            $limit,
            RateLimiter::TIME_FRAME_SECOND,
            $store ?? new InMemoryStore(),
            $deferrer ?? new SleepDeferrer()
        );

        return new static($rateLimiter);
    }

    public static function perMinute(int $limit, ?Store $store = null, ?Deferrer $deferrer = null): RateLimiterMiddleware
    {
        $rateLimiter = new RateLimiter(
            $limit,
            RateLimiter::TIME_FRAME_MINUTE,
            $store ?? new InMemoryStore(),
            $deferrer ?? new SleepDeferrer()
        );

        return new static($rateLimiter);
    }

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // Fail-fast if something is wrong with the internal rate limiter instance
            try {
                $rateLimiter = $this->rateLimiter;
            } catch (\Throwable $exception) {
                return Create::rejectionFor($exception);
            }

            // Defensive timeout extraction (no loops or uncontrolled blocking)
            $timeout = null;
            if (isset($options['timeout']) && is_numeric($options['timeout'])) {
                $timeout = (float) $options['timeout'];
            }

            $operation = function () use ($request, $handler, $options) {
                try {
                    return $handler($request, $options);
                } catch (\Throwable $exception) {
                    // Convert any handler crash into a controlled rejected promise
                    return Create::rejectionFor($exception);
                }
            };

            try {
                // The rate limiting rules and deferral behavior remain unchanged.
                // We only ensure that any infrastructure-level failure is captured.
                $result = $rateLimiter->handle($operation);

                // If a timeout is configured, avoid uncontrolled retention by
                // delegating timeout handling to Guzzle (no custom loops here).
                if ($timeout !== null && is_object($result) && method_exists($result, 'wait')) {
                    // Let Guzzle's own timeout mechanisms apply; we do not introduce
                    // custom blocking loops that could cause resource exhaustion.
                    return $result;
                }

                return $result;
            } catch (\Throwable $exception) {
                // Any exception from the rate limiter or deferrer is contained
                // and surfaced as a rejected promise instead of crashing the app.
                return Create::rejectionFor($exception);
            }
        };
    }
}
