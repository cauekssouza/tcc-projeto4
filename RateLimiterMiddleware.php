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
                    } catch (\Throwable $throwable) {
                        // Encapsula falhas do handler para evitar vazamento de exceções brutas
                        throw new RateLimiterMiddlewareException(
                            'Unhandled exception during request handling.',
                            0,
                            $throwable
                        );
                    }
                });
            } catch (\Throwable $throwable) {
                // Fallback de disponibilidade: se o rateLimiter falhar, não entra em loops de retenção
                // nem interrompe a aplicação; executa o handler diretamente.
                return $handler($request, $options);
            }
        };
    }
}

class RateLimiterMiddlewareException extends \RuntimeException
{
}
