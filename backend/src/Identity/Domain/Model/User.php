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
 *
 * A session remembers the session version it started with and stops working once the version
 * moves on: a reset, disabling the user, a new password or a new TOTP device ends every session
 * (PD-35, PD-36, PD-38).
 */
final class User
{
    private function __construct(
        private readonly UserId $id,
        private readonly Username $username,
        private Role $role,
        private UserStatus $status,
        private ?PasswordHash $passwordHash,
        private ?EncryptedTotpSecret $totpSecret,
        private ?\DateTimeImmutable $totpConfirmedAt,
        private ?EncryptedTotpSecret $pendingTotpSecret,
        private ?ActivationToken $activation,
        private readonly \DateTimeImmutable $createdAt,
        private readonly ?UserId $createdBy,
        private int $sessionVersion,
        private ?int $lastTotpStep,
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
        return new self($id, $username, $role, UserStatus::PENDING_ACTIVATION, null, null, null, null, $activation, $createdAt, $createdBy, 1, null);
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
        int $sessionVersion,
        ?int $lastTotpStep,
    ): self {
        return new self($id, $username, $role, $status, $passwordHash, $totpSecret, $totpConfirmedAt, $pendingTotpSecret, $activation, $createdAt, $createdBy, $sessionVersion, $lastTotpStep);
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

    /**
     * PD-34: the new link gets a fresh lifetime and the previous one stops working. A half-finished
     * activation is dropped, so the new link starts from the password step.
     */
    public function regenerateActivation(ActivationToken $activation): void
    {
        if (UserStatus::PENDING_ACTIVATION !== $this->status) {
            throw UserStateConflict::notPendingActivation();
        }

        $this->activation = $activation;
        $this->passwordHash = null;
        $this->pendingTotpSecret = null;
    }

    /**
     * PD-35: lost password or TOTP device. The user activates again through the new link.
     */
    public function reset(ActivationToken $activation): void
    {
        if (UserStatus::DISABLED === $this->status) {
            throw UserStateConflict::disabled();
        }

        $this->status = UserStatus::PENDING_ACTIVATION;
        $this->activation = $activation;
        $this->passwordHash = null;
        $this->totpSecret = null;
        $this->totpConfirmedAt = null;
        $this->pendingTotpSecret = null;
        $this->endSessions();
    }

    public function disable(): void
    {
        if (UserStatus::ACTIVE !== $this->status) {
            throw UserStateConflict::notActive();
        }

        $this->status = UserStatus::DISABLED;
        $this->endSessions();
    }

    public function enable(): void
    {
        if (UserStatus::DISABLED !== $this->status) {
            throw UserStateConflict::notDisabled();
        }

        $this->status = UserStatus::ACTIVE;
    }

    public function changeRole(Role $role): void
    {
        $this->role = $role;
    }

    /**
     * The caller has already checked the current password and a TOTP code.
     */
    public function changePassword(PasswordHash $passwordHash): void
    {
        $this->assertActive();

        $this->passwordHash = $passwordHash;
        $this->endSessions();
    }

    /**
     * The current device keeps working until the new one is confirmed (PD-38).
     */
    public function startTotpReEnrollment(EncryptedTotpSecret $secret): void
    {
        $this->assertActive();

        $this->pendingTotpSecret = $secret;
    }

    /**
     * The caller has already checked a code from the new device.
     */
    public function confirmTotpReEnrollment(\DateTimeImmutable $now): void
    {
        $this->assertActive();
        if (null === $this->pendingTotpSecret) {
            throw UserStateConflict::noTotpReEnrollment();
        }

        $this->totpSecret = $this->pendingTotpSecret;
        $this->totpConfirmedAt = $now;
        $this->pendingTotpSecret = null;
        $this->endSessions();
    }

    /**
     * A TOTP code is accepted once: its time step must come after the last one this user used.
     *
     * @return bool false when the step was already used (or is older), so the code is a replay
     */
    public function claimTotpStep(int $step): bool
    {
        if (null !== $this->lastTotpStep && $step <= $this->lastTotpStep) {
            return false;
        }

        $this->lastTotpStep = $step;

        return true;
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

    public function sessionVersion(): int
    {
        return $this->sessionVersion;
    }

    public function lastTotpStep(): ?int
    {
        return $this->lastTotpStep;
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

    private function assertActive(): void
    {
        if (UserStatus::ACTIVE !== $this->status) {
            throw UserStateConflict::notActive();
        }
    }

    private function endSessions(): void
    {
        ++$this->sessionVersion;
    }
}
