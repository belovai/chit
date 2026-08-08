<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

/**
 * What the caller wants. Never how — `max_tokens`, `effort`, and anything else
 * vendor-shaped lives on the connection, because it is the user's setting.
 */
final readonly class AiRequest
{
    /**
     * @param  list<ContentPart>  $content
     * @param  array<string, mixed>|null  $jsonSchema
     */
    public function __construct(
        public string $system,
        public array $content,
        public ?array $jsonSchema = null,
        public bool $cacheSystem = false,
    ) {}

    public function hasImages(): bool
    {
        foreach ($this->content as $part) {
            if ($part instanceof ImagePart) {
                return true;
            }
        }

        return false;
    }
}
