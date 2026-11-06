<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    $environment = $context['APP_ENV'] ?? 'prod';

    return new Kernel(is_string($environment) ? $environment : 'prod', (bool) ($context['APP_DEBUG'] ?? false));
};
