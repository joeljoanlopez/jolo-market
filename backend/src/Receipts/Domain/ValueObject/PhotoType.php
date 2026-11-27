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

    public function mediaType(): string
    {
        return 'image/'.$this->value;
    }
}
