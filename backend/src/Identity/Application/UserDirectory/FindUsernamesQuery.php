<?php

declare(strict_types=1);

namespace App\Identity\Application\UserDirectory;

final readonly class FindUsernamesQuery
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(public array $userIds)
    {
    }
}
