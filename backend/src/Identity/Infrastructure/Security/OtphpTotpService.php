<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Identity\Domain\ValueObject\Username;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;

/**
 * RFC 6238 with the settings every authenticator app supports: SHA-1, 6 digits, 30-second steps.
 */
final readonly class OtphpTotpService implements TotpService
{
    private const string ISSUER = 'jolo-market';

    private const int PERIOD_SECONDS = 30;

    /** Tolerated clock drift between the phone and the server. */
    private const int LEEWAY_SECONDS = 15;

    public function generateSecret(): string
    {
        // 20 bytes = 160 bits, the size RFC 4226 recommends.
        return TOTP::generate(new NativeClock(), 20)->getSecret();
    }

    public function provisioningUri(Username $username, #[\SensitiveParameter] string $secret): string
    {
        if ('' === $secret) {
            throw new \InvalidArgumentException('A TOTP secret cannot be empty.');
        }

        $totp = TOTP::createFromSecret($secret, new NativeClock());
        $totp->setLabel($username->value());
        $totp->setIssuer(self::ISSUER);

        return $totp->getProvisioningUri();
    }

    public function matchingTimeStep(#[\SensitiveParameter] string $secret, TotpCode $code, \DateTimeImmutable $now): ?int
    {
        if ('' === $secret) {
            return null;
        }

        $totp = TOTP::createFromSecret($secret, new NativeClock());
        $timestamp = $now->getTimestamp();
        $steps = array_unique(array_map(
            static fn (int $at): int => intdiv($at, self::PERIOD_SECONDS),
            [$timestamp - self::LEEWAY_SECONDS, $timestamp, $timestamp + self::LEEWAY_SECONDS],
        ));
        foreach ($steps as $step) {
            if ($step >= 0 && hash_equals($totp->at($step * self::PERIOD_SECONDS), $code->value())) {
                return $step;
            }
        }

        return null;
    }
}
