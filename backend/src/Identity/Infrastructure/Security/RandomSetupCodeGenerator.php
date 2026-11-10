<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\SetupCodeGenerator;

/**
 * 16 characters of Crockford base32 (80 random bits) in groups of four, like ABCD-EFGH-JKMN-PQRS.
 * Short enough to type, too long to guess under the setup rate limit.
 */
final readonly class RandomSetupCodeGenerator implements SetupCodeGenerator
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function generate(): string
    {
        $characters = '';
        for ($i = 0; $i < 16; ++$i) {
            $characters .= self::ALPHABET[random_int(0, 31)];
        }

        return implode('-', str_split($characters, 4));
    }
}
