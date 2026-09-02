<?php

use App\Services\AI\AIResponse;
use App\Services\AI\OpenAIService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/*
|--------------------------------------------------------------------------
| OpenAIService error classification and request construction.
|--------------------------------------------------------------------------
|
| These tests drive the real OpenAIService transport against a mocked HTTP
| layer (never a live OpenAI call). They assert the transport builds exactly
| one request per `complete()` call and classifies provider failures into
| distinct, safe RuntimeException messages that the UI can localise.
*/

function openaiService(): OpenAIService
{
    config(['services.ai.api_key' => 'sk-test-1234']);
    config(['services.ai.base_url' => 'https://api.openai.com/v1']);
    config(['services.ai.model' => 'gpt-4o-mini']);

    return new OpenAIService();
}

test('a successful provider text response returns an AIResponse without a tool call', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello!']]],
        ], 200),
    ]);

    $result = openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);

    expect($result)->toBeInstanceOf(AIResponse::class)
        ->and($result->isToolCall())->toBeFalse()
        ->and($result->content)->toBe('Hello!');
});

test('the transport sends exactly one POST to chat/completions with the configured model', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]],
        ], 200),
    ]);

    openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);

    Http::assertSentCount(1);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && ($body['model'] ?? null) === 'gpt-4o-mini'
            && $request->hasHeader('Authorization', 'Bearer sk-test-1234');
    });
});

test('HTTP 401 surfaces as an invalid API key error', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'API key');

test('HTTP 403 surfaces as a permission error', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Access denied']], 403)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'permission');

test('HTTP 429 without a quota signal is treated as a rate limit', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'error' => ['message' => 'Rate limit reached for requests.', 'type' => 'requests'],
    ], 429)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'rate limit');

test('Http-200 body carrying a provider error envelope is treated as a rate limit', function () {
    // Regression: OpenRouter free models intermittently respond with HTTP 200 and
    // an OpenAI-style error envelope (e.g. {"error":{"code":429,"message":"Provider
    // returned error"}}) and no "choices". This must be classified as a provider
    // failure and surfaced as a rate limit - NOT the generic "unexpected response".
    Http::fake(['api.openai.com/*' => Http::response([
        'error' => ['message' => 'Provider returned error', 'code' => 429],
    ], 200)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Say OK']]);
})->throws(RuntimeException::class, 'rate limit');

test('Http-200 body carrying a quota error envelope is treated as a quota problem', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'Provider returned error',
            'code' => 'insufficient_quota',
            'type' => 'insufficient_quota',
        ],
    ], 200)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Say OK']]);
})->throws(RuntimeException::class, 'quota');

test('HTTP 429 with insufficient quota is classified as a quota problem, not a rate limit', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'You have no credits remaining. Add credits to continue using the API.',
            'type' => 'insufficient_quota',
            'code' => 'credit_balance_exhausted',
        ],
    ], 429)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'quota');

test('HTTP 5xx surfaces as a provider unavailable error', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Internal error']], 503)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'unavailable');

test('an empty provider body surfaces as an invalid response error', function () {
    Http::fake(['api.openai.com/*' => Http::response([], 200)]);

    openaiService()->complete([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class);

test('one user message triggers exactly one OpenAI request and surfaces the localized quota message', function () {
    config(['services.ai.api_key' => 'sk-test-1234']);
    config(['services.ai.base_url' => 'https://api.openai.com/v1']);
    config(['services.ai.model' => 'gpt-4o-mini']);

    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'You have no credits remaining. Add credits to continue using the API.',
            'type' => 'insufficient_quota',
            'code' => 'credit_balance_exhausted',
        ],
    ], 429)]);

    // Bind the real transport so the full assistant path is exercised (no live call).
    app()->bind(App\Services\AI\AIServiceInterface::class, fn () => new OpenAIService());

    $assistant = app(App\Services\AI\AssistantService::class);
    $reply = $assistant->respond(1, 'en', [], 'Hello');

    // Exactly one provider call for a single message, no retry/loop inflation.
    Http::assertSentCount(1);

    // The quota problem is surfaced as a clear, localized message — never a
    // generic error, never labelled merely as a "rate limit".
    expect($reply)->toContain('no remaining credits');
});

test('HTTP 400 tool-input error on a tool-call follow-up surfaces as a RuntimeException, never a silent success', function () {
    // Regression (transport level): the follow-up completion after a tool call
    // can be rejected by Anthropic-compatible backends with HTTP 400 and
    // "messages.N.content.0.tool_use.input: Input should be a valid dictionary"
    // when the assistant tool-call arguments are (wrongly) serialised as a JSON
    // array ("[]") instead of a dictionary ("{}"). The transport must surface
    // this as a RuntimeException so the assistant loop stops with a friendly
    // error — it must never treat the failure body as a valid assistant reply.
    //
    // (The root-cause fix lives in AssistantService::assistantToolMessage, which
    // round-trips empty-object arguments as "{}"; this test locks in the
    // transport's handling of the resulting 400 should it ever re-occur.)
    Http::fake(['api.openai.com/*' => Http::response([
        'error' => [
            'message' => 'Provider returned error',
            'code' => 400,
            'type' => 'invalid_request_error',
            'metadata' => ['raw' => 'messages.2.content.0.tool_use.input: Input should be a valid dictionary (2013)'],
        ],
    ], 400)]);

    $messages = [
        ['role' => 'system', 'content' => 'system'],
        ['role' => 'user', 'content' => 'How much money do I have?'],
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'getAccountBalances', 'arguments' => '{}']],
        ]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"result":{"total_balance":0}}'],
    ];

    openaiService()->complete($messages, [
        ['type' => 'function', 'function' => ['name' => 'getAccountBalances', 'description' => 'Balances', 'parameters' => ['type' => 'object']]],
    ]);
})->throws(RuntimeException::class);
