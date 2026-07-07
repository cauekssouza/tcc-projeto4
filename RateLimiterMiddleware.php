<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

use Psr\Http\Message\RequestInterface;
use Throwable;

class RateLimiterMiddlewareException extends \RuntimeException {}
class HandlerExecutionException extends \RuntimeException {}
class RateLimitTimeoutException extends \RuntimeException {}

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
            try {
                $timeoutSeconds = $options['rate_limiter_timeout'] ?? null;
                $startTime = $timeoutSeconds !== null ? microtime(true) : null;

                $callback = function () use ($request, $handler, $options, $timeoutSeconds, $startTime) {
                    if ($timeoutSeconds !== null && $startTime !== null) {
                        $elapsed = microtime(true) - $startTime;
                        if ($elapsed >= $timeoutSeconds) {
                            throw new RateLimitTimeoutException(
                                sprintf('Rate limiter timeout of %s seconds exceeded.', $timeoutSeconds)
                            );
                        }
                    }

                    try {
                        return $handler($request, $options);
                    } catch (Throwable $e) {
                        // Wrap any handler-level infrastructure or runtime exception
                        throw new HandlerExecutionException('Handler execution failed.', 0, $e);
                    }
                };

                try {
                    return $this->rateLimiter->handle($callback);
                } catch (Throwable $e) {
                    // Ensure rate limiter failures are surfaced in a controlled way
                    throw new RateLimiterMiddlewareException('Rate limiter middleware failure.', 0, $e);
                }
            } catch (Throwable $e) {
                // Final safety net to avoid crashes and propagate a controlled exception
                throw $e;
            }
        };
    }
}
