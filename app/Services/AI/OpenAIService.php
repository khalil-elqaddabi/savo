<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI-Completions transport.
 *
 * This is a transport only: it serialises messages/tools to the remote API and
 * maps the reply (text or tool_calls) to an {@see AIResponse}. All business and
 * tool-execution logic lives in {@see AssistantService}.
 *
 * Failures are normalised into RuntimeException messages with friendly,
 * non-technical wording; the orchestration layer decides how to surface them.
 *
 * Error classification deliberately distinguishes the common provider failures
 * so the UI can show an accurate, helpful message instead of a generic one:
 *
 *  - 401  -> invalid API key
 *  - 403  -> access / project permission problem
 *  - 429  -> rate limit, or "insufficient quota" when OpenAI reports the
 *            account has no credits left (different remediation)
 *  - 5xx  -> provider temporarily unavailable
 *  - timeout / connection -> could not reach the provider
 *  - empty / malformed body -> invalid provider response
 *
 * Only the safe error ``code`` / ``type`` from the provider (e.g.
 * ``credit_balance_exhausted``) is ever used to classify a response. Raw
 * provider bodies, API keys, headers and any user financial data are never
 * logged or exposed.
 */
class OpenAIService implements AIServiceInterface
{
    public function isConfigured(): bool
    {
        return ! blank(config('services.ai.api_key'));
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function complete(array $messages, array $tools = []): AIResponse
    {
        $key = config('services.ai.api_key');

        if (blank($key)) {
            throw new RuntimeException('AI provider is not configured (missing API key).');
        }

        $model = config('services.ai.model', 'gpt-4o-mini');
        $base = config('services.ai.base_url', 'https://api.openai.com/v1');

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 900,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::timeout(45)
                ->baseUrl($base)
                ->withToken($key)
                ->acceptJson()
                ->post('/chat/completions', $payload);

            // OpenRouter (and other OpenAI-compatible proxies) occasionally send
            // a provider failure inside an HTTP-200 body carrying an OpenAI-style
            // ``error`` envelope (e.g. ``{"error":{"code":429,...}}``) with no
            // ``choices``. Treat that as a failed response too so it is classified
            // instead of falling through to the generic "unexpected response".
            if ($response->failed() || is_array(data_get($response->json(), 'error'))) {
                $this->throwForResponse($response);
            }

            $json = $response->json();
            $message = data_get($json, 'choices.0.message');

            if (! is_array($message)) {
                throw new RuntimeException('The AI provider returned an unexpected response.');
            }

            $content = is_string($message['content'] ?? null) ? $message['content'] : '';
            $toolCalls = data_get($message, 'tool_calls', []);

            if (! is_array($toolCalls) || $toolCalls === []) {
                if (trim($content) === '') {
                    throw new RuntimeException('The AI provider returned an empty response.');
                }

                return AIResponse::text($content);
            }

            $normalised = [];
            foreach ($toolCalls as $tc) {
                $arguments = json_decode((string) data_get($tc, 'function.arguments', '{}'), true);
                $normalised[] = [
                    'id' => (string) data_get($tc, 'id'),
                    'name' => (string) data_get($tc, 'function.name'),
                    'arguments' => is_array($arguments) ? $arguments : [],
                ];
            }

            return AIResponse::withToolCalls($normalised, $content);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the AI provider. Please check your connection.');
        }
    }

    /**
     * Ask the provider to extract a structured transaction from a receipt image.
     *
     * Sends a multimodal (text + image) user message and expects a strict JSON
     * object back. Reuses the same error classification as {@see complete}.
     *
     * @param  string  $imageDataUrl  base64 data URL of the receipt image.
     * @return array<string, mixed>  decoded JSON from the model.
     */
    public function scanReceipt(string $imageDataUrl): array
    {
        $key = config('services.ai.api_key');

        if (blank($key)) {
            throw new RuntimeException('AI provider is not configured (missing API key).');
        }

        $model = config('services.ai.model', 'gpt-4o-mini');
        $base = config('services.ai.base_url', 'https://api.openai.com/v1');

        $instructions = 'You are a receipt parser. Extract a single transaction from the '
            .'receipt image and ONLY reply with a strict JSON object with string/number values '
            .'in this exact shape: '
            .'{"amount": "12.50", "currency": "MAD", "merchant": "Cafe", '
            .'"category": "Food", "date": "2026-01-05", "type": "expense"}. '
            .'If you cannot read the receipt, reply {"error": "unreadable"}.';

        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => 300,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $instructions],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(45)
                ->baseUrl($base)
                ->withToken($key)
                ->acceptJson()
                ->post('/chat/completions', $payload);

            if ($response->failed() || is_array(data_get($response->json(), 'error'))) {
                $this->throwForResponse($response);
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (! is_string($content) || trim($content) === '') {
                throw new RuntimeException('The AI provider returned an empty response.');
            }

            $decoded = json_decode(trim($content), true);

            if (! is_array($decoded)) {
                throw new RuntimeException('The AI provider returned an unexpected response.');
            }

            return $decoded;
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the AI provider. Please check your connection.');
        }
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function throwForResponse($response): void
    {
        $status = $response->status();
        $error = data_get($response->json(), 'error');

        // Prefer an OpenAI-style ``error`` envelope in the body. OpenRouter can
        // deliver provider failures through an HTTP-200 body, so the classification
        // below uses the safe error ``code``/``type`` rather than the HTTP status.
        if (is_array($error)) {
            $this->throwForErrorEnvelope($status, $error);
        }

        if ($status === 401) {
            throw new RuntimeException('The AI provider rejected the API key. Please check the configuration.');
        }

        if ($status === 403) {
            throw new RuntimeException('The AI provider denied access to this project. Please check the account permissions.');
        }

        if ($status === 429) {
            if ($this->isInsufficientQuota($response)) {
                throw new RuntimeException('AI quota exhausted. The provider account has no credits remaining.');
            }

            throw new RuntimeException('AI rate limit exceeded. Please try again in a moment.');
        }

        if ($status >= 500) {
            throw new RuntimeException('The AI provider is temporarily unavailable.');
        }

        throw new RuntimeException('The AI provider returned an unexpected response.');
    }

    /**
     * Classify a provider failure expressed as an OpenAI-style ``error`` envelope
     * inside the response body. Throws the matching RuntimeException, otherwise
     * falls through so the caller can continue with HTTP-status based handling.
     *
     * @param  array<string, mixed>  $error
     */
    private function throwForErrorEnvelope(int $status, array $error): void
    {
        $code = strtolower((string) ($error['code'] ?? ''));
        $type = strtolower((string) ($error['type'] ?? ''));

        if ($this->isInsufficientQuotaEnvelope($code, $type)) {
            throw new RuntimeException('AI quota exhausted. The provider account has no credits remaining.');
        }

        $numericCode = is_numeric($error['code'] ?? null) ? (int) $error['code'] : null;

        if ($type === 'rate_limited'
            || $type === 'rate_limit_error'
            || $numericCode === 429) {
            throw new RuntimeException('AI rate limit exceeded. Please try again in a moment.');
        }

        if ($numericCode === 401
            || str_contains($code, 'auth')
            || str_contains($type, 'auth')
            || $status === 401) {
            throw new RuntimeException('The AI provider rejected the API key. Please check the configuration.');
        }

        if ($numericCode === 403 || $status === 403) {
            throw new RuntimeException('The AI provider denied access to this project. Please check the account permissions.');
        }

        if ($numericCode !== null && $numericCode >= 500) {
            throw new RuntimeException('The AI provider is temporarily unavailable.');
        }
    }

    /**
     * @param  string  $code
     * @param  string  $type
     */
    private function isInsufficientQuotaEnvelope(string $code, string $type): bool
    {
        return $code === 'credit_balance_exhausted'
            || $code === 'insufficient_quota'
            || $type === 'insufficient_quota';
    }

    /**
     * Detect an OpenAI "insufficient quota" 429 from the safe provider error
     * code/type only. Rate-limit 429s (e.g. a genuine throttling response)
     * carry a different error shape and are NOT treated as a quota problem.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function isInsufficientQuota($response): bool
    {
        $error = data_get($response->json(), 'error');

        if (! is_array($error)) {
            return false;
        }

        $code = strtolower((string) ($error['code'] ?? ''));
        $type = strtolower((string) ($error['type'] ?? ''));

        return $code === 'credit_balance_exhausted'
            || $code === 'insufficient_quota'
            || $type === 'insufficient_quota';
    }
}
