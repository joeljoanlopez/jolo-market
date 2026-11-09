<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Rate limits of the credential checks (E3.19). Every attempt counts; a successful login
 * clears the per-user counter, not the per-IP one. Limits live in config/packages/rate_limiter.yaml.
 */
final readonly class LoginThrottle
{
    public function __construct(
        private RateLimiterFactoryInterface $identityLoginUsernameLimiter,
        private RateLimiterFactoryInterface $identityLoginIpLimiter,
        private RateLimiterFactoryInterface $identityTwoFactorUserLimiter,
        private RateLimiterFactoryInterface $identityTwoFactorIpLimiter,
        private RateLimiterFactoryInterface $identityActivationIpLimiter,
    ) {
    }

    public function passwordAttempt(Request $request, string $username): LimiterInterface
    {
        $byUsername = $this->identityLoginUsernameLimiter->create(mb_strtolower($username));
        self::consume($this->identityLoginIpLimiter->create(self::ip($request)), $byUsername);

        return $byUsername;
    }

    public function codeAttempt(Request $request, string $userId): LimiterInterface
    {
        $byUser = $this->identityTwoFactorUserLimiter->create($userId);
        self::consume($this->identityTwoFactorIpLimiter->create(self::ip($request)), $byUser);

        return $byUser;
    }

    /**
     * Profile changes re-check the password and a code, so they share the per-user 2FA limit.
     */
    public function profileAttempt(Request $request, string $userId): LimiterInterface
    {
        $byUser = $this->identityTwoFactorUserLimiter->create('profile:'.$userId);
        self::consume($this->identityTwoFactorIpLimiter->create(self::ip($request)), $byUser);

        return $byUser;
    }

    public function activationCodeAttempt(Request $request): void
    {
        self::consume($this->identityActivationIpLimiter->create(self::ip($request)));
    }

    private static function consume(LimiterInterface ...$limiters): void
    {
        foreach ($limiters as $limiter) {
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

                throw new TooManyRequestsHttpException($retryAfter, 'Too many attempts.');
            }
        }
    }

    private static function ip(Request $request): string
    {
        return $request->getClientIp() ?? 'unknown';
    }
}
