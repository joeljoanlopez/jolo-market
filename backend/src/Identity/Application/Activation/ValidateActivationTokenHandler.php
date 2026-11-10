<?php

declare(strict_types=1);

namespace App\Identity\Application\Activation;

final readonly class ValidateActivationTokenHandler
{
    public function __construct(private ActivationLinks $links)
    {
    }

    public function __invoke(ValidateActivationTokenQuery $query): ActivationLink
    {
        $user = $this->links->userFor($query->token);
        $activation = $user->activation() ?? throw new \LogicException('A valid activation link has a token.');

        return new ActivationLink($user->username()->value(), $activation->expiresAt());
    }
}
