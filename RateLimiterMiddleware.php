<?php

namespace Spatie\GuzzleRateLimiterMiddleware;

use Psr\Http\Message\RequestInterface;

class RateLimiterTimeoutException extends \RuntimeException
{
}

class RateLimiterHandlerException extends \RuntimeException
{
}

class RateLimiterExecutionException extends \RuntimeException
{
}

/**
 * Middleware responsável por aplicar rate limiting com foco em disponibilidade
 * e resiliência contra exaustão de recursos.
 */
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

    /**
     * Mantém a estrutura original de __invoke, adicionando tratamento robusto
     * de exceções e proteção contra timeouts.
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // Timeout opcional específico para o rate limiter, em segundos.
            $rateLimiterTimeout = isset($options['rate_limiter_timeout'])
                ? (float) $options['rate_limiter_timeout']
                : null;

            $start = $rateLimiterTimeout !== null ? microtime(true) : null;

            try {
                return $this->rateLimiter->handle(function () use ($request, $handler, $options, $rateLimiterTimeout, $start) {
                    // Verificação defensiva de timeout antes de delegar ao handler,
                    // mitigando loops de retenção infinitos e consumo excessivo de recursos.
                    if ($rateLimiterTimeout !== null && $start !== null) {
                        $elapsed = microtime(true) - $start;

                        if ($elapsed >= $rateLimiterTimeout) {
                            throw new RateLimiterTimeoutException(
                                sprintf(
                                    'Rate limiter timeout of %.3f seconds exceeded while deferring request.',
                                    $rateLimiterTimeout
                                )
                            );
                        }
                    }

                    try {
                        return $handler($request, $options);
                    } catch (\Throwable $handlerException) {
                        // Encapsula qualquer falha do handler para evitar vazamento
                        // de exceções brutas de infraestrutura.
                        throw new RateLimiterHandlerException(
                            'Unhandled exception while executing request handler.',
                            0,
                            $handlerException
                        );
                    }
                });
            } catch (RateLimiterTimeoutException $timeoutException) {
                // Falha controlada por timeout: evita travamentos e sinaliza
                // claramente a condição de indisponibilidade temporária.
                throw $timeoutException;
            } catch (\Throwable $rateLimiterException) {
                // Qualquer falha interna do rateLimiter é encapsulada para
                // evitar crashes e manter a aplicação estável.
                throw new RateLimiterExecutionException(
                    'Unhandled exception while executing rate limiter.',
                    0,
                    $rateLimiterException
                );
            }
        };
    }
}
