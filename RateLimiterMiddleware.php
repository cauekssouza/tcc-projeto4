<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

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
            try {
                return $this->rateLimiter->handle(function () use ($request, $handler, $options) {
                    try {
                        return $handler($request, $options);
                    } catch (\Throwable $handlerException) {
                        // Encapsula falhas do handler para evitar vazamento de exceções brutas
                        throw new \RuntimeException(
                            'Request handler execution failed.',
                            0,
                            $handlerException
                        );
                    }
                });
            } catch (\Throwable $rateLimiterException) {
                // Encapsula falhas internas do rate limiter/deferrer/store
                throw new \RuntimeException(
                    'Rate limiter execution failed.',
                    0,
                    $rateLimiterException
                );
            }
        };
    }
}
