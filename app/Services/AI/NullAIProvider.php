<?php

namespace App\Services\AI;

/**
 * No-op provider used when no AI provider / API key is configured.
 *
 * It reports that it is not configured and never makes network calls. The
 * orchestration layer checks {@see isConfigured()} and degrades gracefully, so
 * the rest of Savo keeps working without any AI key.
 */
class NullAIProvider implements AIServiceInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function supportsTools(): bool
    {
        return false;
    }

    public function complete(array $messages, array $tools = []): AIResponse
    {
        return AIResponse::text('');
    }
}
