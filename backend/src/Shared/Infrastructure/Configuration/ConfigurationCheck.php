<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Checks part of the instance configuration before the app starts (E6.4). Each context checks its own variables, and
 * `app:config:check` runs every check.
 */
#[AutoconfigureTag(self::TAG)]
interface ConfigurationCheck
{
    public const string TAG = 'app.configuration_check';

    /**
     * @return list<string> one clear message per missing or invalid variable; empty when everything is valid
     */
    public function problems(): array;
}
