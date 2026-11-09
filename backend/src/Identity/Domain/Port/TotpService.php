<?php

declare(strict_types=1);

namespace App\Identity\Domain\Port;

use App\Identity\Domain\ValueObject\TotpCode;
use App\Identity\Domain\ValueObject\Username;

/**
 * Time-based one-time passwords (RFC 6238). Secrets cross this port in clear,
 * so callers encrypt them (SecretEncrypter) before storing.
 */
interface TotpService
{
    public function generateSecret(): string;

    /**
     * The otpauth:// URI that authenticator apps read from the QR code.
     */
    public function provisioningUri(Username $username, #[\SensitiveParameter] string $secret): string;

    /**
     * The 30-second time step (Unix time / 30) whose code is $code, within the tolerated clock
     * drift, or null when the code is wrong. Callers reject a step already used (no replay).
     */
    public function matchingTimeStep(#[\SensitiveParameter] string $secret, TotpCode $code, \DateTimeImmutable $now): ?int;
}
