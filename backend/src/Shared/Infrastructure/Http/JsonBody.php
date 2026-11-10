<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the fields of a JSON request body. A missing or mistyped field is a 422.
 */
final readonly class JsonBody
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

    public function string(string $name): string
    {
        $value = $this->fields[$name] ?? null;

        return \is_string($value) ? $value : throw InvalidRequestBody::missingString($name);
    }
}
