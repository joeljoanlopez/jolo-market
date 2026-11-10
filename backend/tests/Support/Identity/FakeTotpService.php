<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Identity\Domain\ValueObject\Username;

/**
 * Secrets are SECRET1, SECRET2, ... The valid code of a secret, and the time step it belongs
 * to, are set with acceptCode().
 */
final class FakeTotpService implements TotpService
{
    public const int DEFAULT_STEP = 59000000;

    private int $next = 1;

    /** @var array<string, array{string, int}> secret => [valid code, its time step] */
    private array $validCodes = [];

    public function generateSecret(): string
    {
        return 'SECRET'.$this->next++;
    }

    public function provisioningUri(Username $username, string $secret): string
    {
        return \sprintf('otpauth://totp/jolo-market:%s?secret=%s&issuer=jolo-market', $username->value(), $secret);
    }

    public function matchingTimeStep(string $secret, TotpCode $code, \DateTimeImmutable $now): ?int
    {
        [$validCode, $step] = $this->validCodes[$secret] ?? [null, null];

        return $validCode === $code->value() ? $step : null;
    }

    public function acceptCode(string $secret, string $code, int $step = self::DEFAULT_STEP): void
    {
        $this->validCodes[$secret] = [$code, $step];
    }
}
