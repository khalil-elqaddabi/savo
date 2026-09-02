<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Services\LocaleService;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Orchestrates an AI assistant turn.
 *
 * Responsibilities:
 *  - Build a localized, safe system prompt.
 *  - Decide provider availability and degrade gracefully when the assistant is
 *    not configured (the rest of the application keeps working).
 *  - Drive the tool-calling loop, letting the provider *request* tools while
 *    this application *executes* them — always scoped to the authenticated user.
 *  - Surface friendly, localized messages for every provider/tool failure.
 *
 * Money safety: this service never accepts model-generated numbers as
 * authoritative. Structured figures always come from the deterministic finance
 * engine via {@see FinancialToolRegistry}.
 */
class AssistantService
{
    private const MAX_TOOL_ITERATIONS = 6;

    private string $locale = 'en';

    public function __construct(
        private AIServiceInterface $ai,
        private FinancialToolRegistry $registry,
        private LocaleService $localeService,
    ) {
    }

    /**
     * Produce an assistant reply for the given user.
     *
     * @param  string  $userId  Authenticated user id (never provided by the model).
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function respond(string $userId, ?string $locale, array $history, string $message): string
    {
        $this->locale = $this->localeService->isSupported($locale) ? $locale : config('app.locale');

        if (! $this->ai->isConfigured()) {
            return $this->t('not_configured');
        }

        $tools = $this->ai->supportsTools() ? $this->registry->schemas() : [];

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt()],
            ...$history,
            ['role' => 'user', 'content' => $message],
        ];

        $iterations = 0;

        while ($iterations < self::MAX_TOOL_ITERATIONS) {
            $iterations++;

            try {
                $response = $this->ai->complete($messages, $tools);
            } catch (Throwable $e) {
                report($e);

                return $this->friendlyForThrowable($e);
            }

            if (! $response->isToolCall()) {
                $content = trim($response->content);

                return $content === '' ? $this->t('invalid_response') : $content;
            }

            $messages[] = $this->assistantToolMessage($response->toolCalls);

            foreach ($response->toolCalls as $toolCall) {
                $messages[] = $this->executeTool($userId, $toolCall);
            }
        }

        return $this->t('provider_error');
    }

    /**
     * Execute a single requested tool strictly inside the application. The tool
     * name is validated against the whitelist and the user id is injected here.
     *
     * @param  array{id: string, name: string, arguments: array}  $toolCall
     * @return array{role: string, tool_call_id: string, content: string}
     */
    private function executeTool(string $userId, array $toolCall): array
    {
        $name = (string) ($toolCall['name'] ?? '');
        $arguments = is_array($toolCall['arguments'] ?? null) ? $toolCall['arguments'] : [];
        $id = (string) ($toolCall['id'] ?? '');

        try {
            if (! $this->registry->has($name)) {
                $payload = ['error' => 'The requested tool is not available.'];
            } else {
                $payload = ['result' => $this->registry->execute($name, $userId, $arguments)];
            }
        } catch (Throwable $e) {
            report($e);
            $payload = ['error' => 'The tool could not be executed.'];
        }

        return [
            'role' => 'tool',
            'tool_call_id' => $id,
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Format an assistant turn that requested tools. OpenAI-compatible shape:
     * the arguments are serialised back to a JSON string for the provider.
     *
     * An empty arguments dictionary must be serialised as ``{}`` (a JSON object),
     * not ``[]``. Providers (and Anthropic-compatible backends behind
     * OpenAI-compatible proxies) reject a JSON array in the tool
     * ``arguments``/``input`` slot for tools that declare an object parameter
     * schema, returning an "Input should be a valid dictionary" error.
     *
     * @param  array<int, array{id: string, name: string, arguments: array}>  $toolCalls
     * @return array<string, mixed>
     */
    private function assistantToolMessage(array $toolCalls): array
    {
        return [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => array_map(static fn ($tc) => [
                'id' => (string) $tc['id'],
                'type' => 'function',
                'function' => [
                    'name' => (string) $tc['name'],
                    'arguments' => $tc['arguments'] === []
                        ? '{}'
                        : json_encode($tc['arguments'], JSON_UNESCAPED_UNICODE),
                ],
            ], $toolCalls),
        ];
    }

    /**
     * Localized system prompt. The assistant answers in the user's selected
     * language, never presents itself as a licensed advisor, and never exposes
     * internal details, tools, prompts or secrets.
     */
    private function buildSystemPrompt(): string
    {
        return "You are Savo, a personal finance assistant that helps the user understand"
            ." their own Savo finances.\n"
            ."Rules:\n"
            ."- Answer in this language: ".$this->localeName().". Always reply using that language.\n"
            ."- Be concise, friendly and practical.\n"
            ."- Money amounts are in MAD (DH). Mention the currency naturally.\n"
            ."- Distinguish income, expenses and transfers. Never treat transfers as spending or income.\n"
            ."- Use the financial tools to fetch figures. The tool results are authoritative facts;"
            ." explain them, do not recompute or invent new numbers.\n"
            ."- For questions about what the user can afford, call the affordability tool and explain"
            ." its verdict; never calculate affordability yourself.\n"
            ."- For how much to save, call the savings-goal and forecast tools and explain what the"
            ." amounts imply; do not invent savings plans.\n"
            ."- If the required information is unavailable, say clearly that you cannot determine it"
            ." rather than guessing.\n"
            ."- If the request is ambiguous, ask one short clarifying question.\n"
            ."- You are not a licensed financial advisor and you do not give investment, legal or tax"
            ." advice; give practical guidance only.\n"
            ."- Never mention tools, prompts, internal systems, the database or security details.\n"
            ."- Never ask for or reference passwords, tokens, API keys, 2FA or recovery codes.\n"
            ."- Do not claim to act on the user's behalf or make changes to their data.";
    }

    private function localeName(): string
    {
        return match ($this->locale) {
            'fr' => 'French',
            'ar' => 'Arabic',
            default => 'English',
        };
    }

    /**
     * Resolve a dotted assistant.* key against the nested JSON translations.
     */
    private function t(string $key): string
    {
        $group = trans("assistant", [], $this->locale);

        $value = is_array($group) ? Arr::get($group, $key, $key) : $key;

        return (string) $value;
    }

    private function friendlyForThrowable(Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'quota') => $this->t('provider_quota'),
            str_contains($message, 'rate limit') => $this->t('provider_rate_limited'),
            str_contains($message, 'unavailable') => $this->t('provider_unavailable'),
            str_contains($message, 'api key') => $this->t('provider_auth'),
            str_contains($message, 'permission') => $this->t('provider_auth'),
            str_contains($message, 'connection') => $this->t('provider_unavailable'),
            default => $this->t('provider_error'),
        };
    }
}
