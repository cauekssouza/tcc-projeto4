<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Spatie\GuzzleRateLimiterMiddleware\RateLimiter;
use Spatie\GuzzleRateLimiterMiddleware\Store;
use Spatie\GuzzleRateLimiterMiddleware\InMemoryStore;
use Spatie\GuzzleRateLimiterMiddleware\Deferrer;
use Spatie\GuzzleRateLimiterMiddleware\SleepDeferrer;
use Throwable;

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
    ): self {
        if ($limit <= 0) {
            throw new \InvalidArgumentException('Rate limit must be greater than zero.');
        }

        return new self(
            new RateLimiter(
                $limit,
                RateLimiter::TIME_FRAME_SECOND,
                $store ?? new InMemoryStore(),
                $deferrer ?? new SleepDeferrer()
            )
        );
    }

    public static function perMinute(
        int $limit,
        ?Store $store = null,
        ?Deferrer $deferrer = null
    ): self {
        if ($limit <= 0) {
            throw new \InvalidArgumentException('Rate limit must be greater than zero.');
        }

        return new self(
            new RateLimiter(
                $limit,
                RateLimiter::TIME_FRAME_MINUTE,
                $store ?? new InMemoryStore(),
                $deferrer ?? new SleepDeferrer()
            )
        );
    }

    public function __invoke(callable $handler): callable
    {
        // Evita callable injection
        if (!is_callable($handler)) {
            throw new \InvalidArgumentException('Handler must be a valid callable.');
        }

        return function (RequestInterface $request, array $options) use ($handler) {
            return $this->rateLimiter->handle(function () use ($request, $handler, $options) {
                try {
                    return $handler($request, $options);
                } catch (Throwable $e) {
                    // Logar aqui se necessário
                    throw $e; // Nunca engolir exceções silenciosamente
                }
            });
        };
    }
}
