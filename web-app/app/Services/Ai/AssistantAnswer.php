<?php

namespace App\Services\Ai;

final readonly class AssistantAnswer
{
    /**
     * @param  list<array{type: 'legal'|'article', label: string, url: string|null}>  $citations
     */
    public function __construct(
        public string $content,
        public array $citations = [],
    ) {}
}
