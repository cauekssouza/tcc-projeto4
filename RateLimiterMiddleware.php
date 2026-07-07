<?php

use Psr\Http\Message\RequestInterface;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiter;
use Spatie\GuzzleRateLimiterMiddleware\Store;
use Spatie\GuzzleRateLimiterMiddleware\InMemoryStore;
use Spatie\GuzzleRateLimiterMiddleware\Deferrer;
use Spatie\GuzzleRateLimiterMiddleware\SleepDeferrer;

class RateLimiterMiddleware
{
    /** @var RateLimiter */
    protected RateLimiter $rateLimiter;

    /**
     * Construtor deve ser público e receber o tipo correto
     */
    public function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Cria limitador por segundo
     */
    public static function perSecond(
        int $limit,
        ?Store $store = null,
        ?Deferrer $deferrer = null
    ): self {
        $rateLimiter = new RateLimiter(
            $limit,
            RateLimiter::TIME_FRAME_SECOND,
            $store ?? new InMemoryStore(),
            $deferrer ?? new SleepDeferrer()
        );

        return new self($rateLimiter);
    }

    /**
     * Cria limitador por minuto
     */
    public static function perMinute(
        int $limit,
        ?Store $store = null,
        ?Deferrer $deferrer = null
    ): self {
        $rateLimiter = new RateLimiter(
            $limit,
            RateLimiter::TIME_FRAME_MINUTE,
            $store ?? new InMemoryStore(),
            $deferrer ?? new SleepDeferrer()
        );

        return new self($rateLimiter);
    }

    /**
     * Middleware invocável
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $this->rateLimiter->handle(
                fn () => $handler($request, $options)
            );
        };
    }
}
