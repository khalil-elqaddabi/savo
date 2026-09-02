<?php

namespace App\Services\AI;

/**
 * Replaceable AI provider abstraction.
 *
 * The provider is treated as a transport layer only: it exchanges messages and
 * tool schemas with the remote model and returns an {@see AIResponse}. The core
 * application must keep working when no provider is configured, and providers
 * never execute business logic directly.
 */
interface AIServiceInterface
{
    /**
     * Whether a provider + API key are configured and usable.
     */
    public function isConfigured(): bool;

    /**
     * Whether this provider supports native tool / function calling.
     */
    public function supportsTools(): bool;

    /**
     * Send messages (and optionally tool schemas) to the provider and return a
     * response, which may be text or a request to execute tools.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>  $tools  JSON-schema tool definitions
     */
    public function complete(array $messages, array $tools = []): AIResponse;
}
