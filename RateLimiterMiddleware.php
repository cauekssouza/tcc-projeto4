<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

use Psr\Http\Message\RequestInterface;
use Throwable;
use GuzzleHttp\Promise\RejectedPromise;

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
                        // Execução do handler protegida contra exceções de infraestrutura
                        return $handler($request, $options);
                    } catch (Throwable $handlerException) {
                        // Aqui você pode integrar com o logger da aplicação, se existir
                        // logger()->error('Handler failure in RateLimiterMiddleware', ['exception' => $handlerException]);

                        // Propaga como falha controlada (promise rejeitada) para não derrubar o processo
                        return new RejectedPromise($handlerException);
                    }
                });
            } catch (Throwable $rateLimiterException) {
                // Falhas internas do rateLimiter (ex.: store/deferrer) são tratadas aqui
                // Evita crash da aplicação e loops de retenção não controlados
                // logger()->error('RateLimiter failure in RateLimiterMiddleware', ['exception' => $rateLimiterException]);

                return new RejectedPromise($rateLimiterException);
            }
        };
    }
}
