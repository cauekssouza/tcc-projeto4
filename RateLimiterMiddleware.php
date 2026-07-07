<?php

use Psr\Http\Message\RequestInterface;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiter;
use Spatie\GuzzleRateLimiterMiddleware\Store\Store;
use Spatie\GuzzleRateLimiterMiddleware\Store\InMemoryStore;
use Spatie\GuzzleRateLimiterMiddleware\Deferrer\Deferrer;
use Spatie\GuzzleRateLimiterMiddleware\Deferrer\SleepDeferrer;

class RateLimiterMiddleware
{
    /** @var RateLimiter */
    protected RateLimiter $rateLimiter;

    public function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    public static function perSecond(
        int $limit,
        ?Store $store = null,
        ?Deferrer $deferrer = null
    ): RateLimiterMiddleware {
        $rateLimiter = new RateLimiter(
            $limit,
            RateLimiter::TIME_FRAME_SECOND,
            $store ?? new InMemoryStore(),
            $deferrer ?? new SleepDeferrer()
        );

        return new self($rateLimiter);
    }

    public static function perMinute(
        int $limit,
        ?Store $store = null,
        ?Deferrer $deferrer = null
    ): RateLimiterMiddleware {
        $rateLimiter = new RateLimiter(
            $limit,
            RateLimiter::TIME_FRAME_MINUTE,
            $store ?? new InMemoryStore(),
            $deferrer ?? new SleepDeferrer()
        );

        return new self($rateLimiter);
    }

    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $this->rateLimiter->handle(
                fn () => $handler($request, $options)
            );
        };
    }
}
