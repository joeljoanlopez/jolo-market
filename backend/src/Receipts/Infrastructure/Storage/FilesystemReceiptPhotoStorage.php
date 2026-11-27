<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Storage;

use App\Receipts\Domain\Port\ReceiptPhotoStorage;
use App\Receipts\Domain\ValueObject\ReceiptId;

/**
 * One file per receipt in RECEIPT_STORAGE_DIR, named by the receipt id and never by the uploaded
 * name (PD-68), so an upload cannot choose where it is written.
 */
final readonly class FilesystemReceiptPhotoStorage implements ReceiptPhotoStorage
{
    public function __construct(private string $directory)
    {
    }

    public function store(ReceiptId $id, string $contents): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(\sprintf('The receipt photo directory "%s" cannot be created.', $this->directory));
        }

        // Written aside and renamed, so a reader never sees half a photo.
        $temporary = $this->pathOf($id).'.tmp';
        if (false === file_put_contents($temporary, $contents) || !rename($temporary, $this->pathOf($id))) {
            throw new \RuntimeException(\sprintf('The photo of receipt "%s" cannot be stored.', $id->value()));
        }
    }

    public function read(ReceiptId $id): ?string
    {
        $path = $this->pathOf($id);
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return false === $contents ? null : $contents;
    }

    public function delete(ReceiptId $id): void
    {
        $path = $this->pathOf($id);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function pathOf(ReceiptId $id): string
    {
        return rtrim($this->directory, '/').'/'.$id->value();
    }
}
