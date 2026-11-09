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

    public function verify(#[\SensitiveParameter] string $secret, TotpCode $code, \DateTimeImmutable $now): bool;
}
