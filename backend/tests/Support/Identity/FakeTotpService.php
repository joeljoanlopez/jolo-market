<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Port\TotpService;
use App\Identity\Domain\ValueObject\TotpCode;
use App\Identity\Domain\ValueObject\Username;

/**
 * Secrets are SECRET1, SECRET2, ... The valid code of a secret is set with acceptCode().
 */
final class FakeTotpService implements TotpService
{
    private int $next = 1;

    /** @var array<string, string> secret => valid code */
    private array $validCodes = [];

    public function generateSecret(): string
    {
        return 'SECRET'.$this->next++;
    }

    public function provisioningUri(Username $username, string $secret): string
    {
        return \sprintf('otpauth://totp/jolo-market:%s?secret=%s&issuer=jolo-market', $username->value(), $secret);
    }

    public function verify(string $secret, TotpCode $code, \DateTimeImmutable $now): bool
    {
        return ($this->validCodes[$secret] ?? null) === $code->value();
    }

    public function acceptCode(string $secret, string $code): void
    {
        $this->validCodes[$secret] = $code;
    }
}
