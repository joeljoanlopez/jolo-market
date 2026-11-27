<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * Who read the photo (PD-114): the AI, the OCR fallback, or none when both failed.
 */
enum ReadBy: string
{
    case AI = 'ai';
    case OCR = 'ocr';
    case NONE = 'none';
}
