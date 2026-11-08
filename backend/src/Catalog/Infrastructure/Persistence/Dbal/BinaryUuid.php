<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Dbal;

/**
 * UUIDs are stored as BINARY(16).
 */
final class BinaryUuid
{
    public static function toDatabase(string $uuid): string
    {
        return (string) hex2bin(str_replace('-', '', $uuid));
    }

    public static function fromDatabase(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return \sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
