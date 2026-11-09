<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Domain\ValueObject\ActivationToken;
use App\Identity\Domain\ValueObject\EncryptedTotpSecret;
use App\Identity\Domain\ValueObject\PasswordHash;
use App\Identity\Domain\ValueObject\Username;
use App\Shared\Domain\ValueObject\UserId;

/**
 * A person who can log in (PD-12, PD-13).
 *
 * An admin creates the user pending activation, with a one-time activation link. Through the
 * link, the user sets a password, enrolls a TOTP device and confirms a code; only then is the
 * user ACTIVE, and the link is used up. Every activation step checks the link again, so an
 * expired link cannot finish an activation that was already started.
 *
 * Checking the TOTP code itself needs the decrypted secret, which the application layer does
 * through its ports before calling confirmTotp().
 */
final class User
{
    private function __construct(
        private readonly UserId $id,
        private readonly Username $username,
        private readonly Role $role,
        private UserStatus $status,
        private ?PasswordHash $passwordHash,
        private ?EncryptedTotpSecret $totpSecret,
        private ?\DateTimeImmutable $totpConfirmedAt,
        private ?EncryptedTotpSecret $pendingTotpSecret,
        private ?ActivationToken $activation,
        private readonly \DateTimeImmutable $createdAt,
        private readonly ?UserId $createdBy,
    ) {
    }

    /**
     * @param ?UserId $createdBy the admin who created the user; null for the first admin (PD-14)
     */
    public static function createPending(
        UserId $id,
        Username $username,
        Role $role,
        ActivationToken $activation,
        \DateTimeImmutable $createdAt,
        ?UserId $createdBy,
    ): self {
        return new self($id, $username, $role, UserStatus::PENDING_ACTIVATION, null, null, null, null, $activation, $createdAt, $createdBy);
    }

    /**
     * Rebuilds a stored user. Only persistence adapters call it.
     */
    public static function reconstitute(
        UserId $id,
        Username $username,
        Role $role,
        UserStatus $status,
        ?PasswordHash $passwordHash,
        ?EncryptedTotpSecret $totpSecret,
        ?\DateTimeImmutable $totpConfirmedAt,
        ?EncryptedTotpSecret $pendingTotpSecret,
        ?ActivationToken $activation,
        \DateTimeImmutable $createdAt,
        ?UserId $createdBy,
    ): self {
        return new self($id, $username, $role, $status, $passwordHash, $totpSecret, $totpConfirmedAt, $pendingTotpSecret, $activation, $createdAt, $createdBy);
    }

    public function setInitialPassword(#[\SensitiveParameter] string $plainToken, PasswordHash $passwordHash, \DateTimeImmutable $now): void
    {
        $this->assertActivationLinkIsValid($plainToken, $now);

        $this->passwordHash = $passwordHash;
    }

    /**
     * Starting again replaces the unconfirmed secret, for example when the user reloads the page.
     */
    public function startTotpEnrollment(#[\SensitiveParameter] string $plainToken, EncryptedTotpSecret $secret, \DateTimeImmutable $now): void
    {
        $this->assertActivationLinkIsValid($plainToken, $now);
        if (null === $this->passwordHash) {
            throw ActivationStepOutOfOrder::passwordMissing();
        }

        $this->pendingTotpSecret = $secret;
    }

    /**
     * The caller has already checked a code against the pending secret.
     */
    public function confirmTotp(#[\SensitiveParameter] string $plainToken, \DateTimeImmutable $now): void
    {
        $this->assertActivationLinkIsValid($plainToken, $now);
        if (null === $this->passwordHash) {
            throw ActivationStepOutOfOrder::passwordMissing();
        }
        if (null === $this->pendingTotpSecret) {
            throw ActivationStepOutOfOrder::totpEnrollmentMissing();
        }

        $this->totpSecret = $this->pendingTotpSecret;
        $this->totpConfirmedAt = $now;
        $this->pendingTotpSecret = null;
        $this->activation = null;
        $this->status = UserStatus::ACTIVE;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return UserStatus::ACTIVE === $this->status;
    }

    public function isAdmin(): bool
    {
        return Role::ADMIN === $this->role;
    }

    public function passwordHash(): ?PasswordHash
    {
        return $this->passwordHash;
    }

    public function totpSecret(): ?EncryptedTotpSecret
    {
        return $this->totpSecret;
    }

    public function totpConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->totpConfirmedAt;
    }

    public function pendingTotpSecret(): ?EncryptedTotpSecret
    {
        return $this->pendingTotpSecret;
    }

    public function activation(): ?ActivationToken
    {
        return $this->activation;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function createdBy(): ?UserId
    {
        return $this->createdBy;
    }

    /**
     * @throws ActivationLinkNotValid when the link is not this user's current one, has expired or was used
     */
    public function assertActivationLinkIsValid(#[\SensitiveParameter] string $plainToken, \DateTimeImmutable $now): void
    {
        if (UserStatus::PENDING_ACTIVATION !== $this->status
            || null === $this->activation
            || !$this->activation->matches($plainToken)
            || $this->activation->isExpiredAt($now)
        ) {
            throw ActivationLinkNotValid::create();
        }
    }
}
