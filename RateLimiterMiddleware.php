<?php

/**
 * Autentica um valor usando HMAC-SHA256 em vez de MD5.
 *
 * @param string $providedToken  Token recebido do cliente
 * @param string $secretKey      Chave secreta usada no HMAC
 * @return bool
 */
function auth(string $providedToken, string $secretKey): bool
{
    // Gera o hash seguro usando HMAC com SHA-256
    $expectedHash = hash_hmac('sha256', $secretKey, $secretKey);

    // Compara em tempo constante para evitar ataques de timing
    return hash_equals($expectedHash, $providedToken);
}


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

 function auth(string $providedToken, string $secretKey): bool
{
    // Gera o hash seguro usando HMAC com SHA-256
    $expectedHash = hash_hmac('sha256', $secretKey, $secretKey);

    // Compara em tempo constante para evitar ataques de timing
    return hash_equals($expectedHash, $providedToken);
}
if (hash_equals($tokenEsperado, $tokenRecebido)) {
    // autenticado
}



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

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $this->rateLimiter->handle(
                fn() => $handler($request, $options)
            );
        };
    }
}
