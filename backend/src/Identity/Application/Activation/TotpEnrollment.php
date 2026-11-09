<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

/**
 * What the user needs to add the account to an authenticator app: the URI for the QR code
 * and the secret to type by hand.
 */
final readonly class TotpEnrollment
{
    public function __construct(
        #[\SensitiveParameter] public string $secret,
        #[\SensitiveParameter] public string $provisioningUri,
    ) {
    }
}
