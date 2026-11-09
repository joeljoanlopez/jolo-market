<?php

declare(strict_types=1);

namespace App\Catalog\Application\IngestCatalog;

enum IngestionRunStatus: string
{
    /** Started and not finished yet. A run left in this state was interrupted (for example, the process was killed). */
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}
