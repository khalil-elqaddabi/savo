<?php

namespace App\Services\AI;

/**
 * Transport-level result from an AI provider.
 *
 * A response is either free text or a request to execute one or more
 * structured tools. The orchestration layer (AssistantService) is responsible
 * for executing tool calls and, when appropriate, requesting a final answer.
 */
final class AIResponse
{
    /**
     * @param  string  $content  Assistant text content (empty when only tools are requested)
     * @param  array<int, array{id: string, name: string, arguments: array}>  $toolCalls
     */
    public function __construct(
        public readonly string $content = '',
        public readonly array $toolCalls = [],
    ) {
    }

    public function isToolCall(): bool
    {
        return $this->toolCalls !== [];
    }

    public static function text(string $content): self
    {
        return new self($content);
    }

    /**
     * @param  array<int, array{id: string, name: string, arguments: array}>  $toolCalls
     */
    public static function withToolCalls(array $toolCalls, string $content = ''): self
    {
        return new self($content, $toolCalls);
    }
}
