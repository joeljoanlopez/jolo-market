<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * The image types a receipt photo may have (PD-68).
 */
enum PhotoType: string
{
    case JPEG = 'jpeg';
    case PNG = 'png';
    case WEBP = 'webp';

    public static function fromMediaType(string $mediaType): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->mediaType() === $mediaType) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The type its first bytes show, whatever the upload claims; null for any other file.
     */
    public static function detect(string $contents): ?self
    {
        return match (true) {
            str_starts_with($contents, "\xFF\xD8\xFF") => self::JPEG,
            str_starts_with($contents, "\x89PNG\r\n\x1A\n") => self::PNG,
            str_starts_with($contents, 'RIFF') && 'WEBP' === substr($contents, 8, 4) => self::WEBP,
            default => null,
        };
    }

    public function mediaType(): string
    {
        return 'image/'.$this->value;
    }
}
