<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Dev;

use App\Identity\Domain\Port\SecretEncrypter;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\InvalidUsername;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\Clock;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * PD-56: 2FA stays mandatory in dev; this prints the code an authenticator app would show.
 * The settings match OtphpTotpService (SHA-1, 6 digits, 30-second steps).
 */
#[AsCommand(
    name: 'app:dev:totp',
    description: 'Development only: print the current TOTP code of an active user and how long it is valid.',
)]
final readonly class PrintTotpCodeConsoleCommand
{
    private const int PERIOD_SECONDS = 30;

    public function __construct(
        private UserRepository $users,
        private SecretEncrypter $secretEncrypter,
        private Clock $clock,
    ) {
    }

    /**
     * Exit code: 0 with the code, 1 when the user does not exist or is not active.
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Username, for example "admin" or "usuario".')]
        string $username,
    ): int {
        try {
            $user = $this->users->findByUsername(Username::fromString($username));
        } catch (InvalidUsername) {
            $user = null;
        }

        $secret = null === $user || !$user->isActive() ? null : $user->totpSecret();
        if (null === $secret) {
            $io->error(\sprintf('"%s" is not an active user.', $username));

            return Command::FAILURE;
        }

        $plainSecret = $this->secretEncrypter->decrypt($secret);
        if ('' === $plainSecret) {
            throw new \RuntimeException('A TOTP secret cannot be empty.');
        }

        $timestamp = $this->clock->now()->getTimestamp();
        if ($timestamp < 0) {
            throw new \RuntimeException('The clock is before the Unix epoch.');
        }
        $code = TOTP::createFromSecret($plainSecret, new NativeClock())->at($timestamp);
        $io->writeln(\sprintf('%s (valid for %d s more)', $code, self::PERIOD_SECONDS - $timestamp % self::PERIOD_SECONDS));

        return Command::SUCCESS;
    }
}
