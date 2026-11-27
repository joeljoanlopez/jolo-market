<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Api;

use App\Shared\Infrastructure\Http\InvalidRequestBody;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the optional, typed fields of a receipt request body. A field that is absent or null is
 * null; one with the wrong JSON type is a 422 (no "2" for 2, no 1.5 for an integer).
 */
final readonly class ReceiptRequestBody
{
    /**
     * @param array<mixed> $fields
     */
    private function __construct(private array $fields)
    {
    }

    public static function of(Request $request): self
    {
        $content = $request->getContent();
        if ('' === $content) {
            return new self([]);
        }

        try {
            $fields = json_decode($content, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidRequestBody::notJson();
        }

        return \is_array($fields) ? new self($fields) : throw InvalidRequestBody::notJson();
    }

    public function string(string $name): ?string
    {
        $value = $this->fields[$name] ?? null;

        return null === $value || \is_string($value) ? $value : throw InvalidReceiptRequest::wrongType($name, 'a string');
    }

    public function requiredString(string $name): string
    {
        return $this->string($name) ?? throw InvalidReceiptRequest::missing($name);
    }

    public function int(string $name): ?int
    {
        $value = $this->fields[$name] ?? null;

        return null === $value || \is_int($value) ? $value : throw InvalidReceiptRequest::wrongType($name, 'an integer');
    }

    public function requiredInt(string $name): int
    {
        return $this->int($name) ?? throw InvalidReceiptRequest::missing($name);
    }
}
