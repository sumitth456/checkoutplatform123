<?php

namespace App\Modules\Product\Import\Enums;

enum ProductImportStatus: string
{
    case Pending = 'pending';
    case PreviewReady = 'preview_ready';
    case Validated = 'validated';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
