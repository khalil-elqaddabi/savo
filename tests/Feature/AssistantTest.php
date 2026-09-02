<?php

use App\Models\Account;
use App\Models\User;
use App\Services\AI\AIResponse;
use App\Services\AI\AIServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

beforeEach(function () {
    // FakeAIProvider keeps shared static state; reset it so tests stay
    // order-independent and the tool-call counter restarts each time.
    FakeAIProvider::$mode = 'text';
    FakeAIProvider::$throwMessage = 'AI rate limit exceeded. Please try again in a moment.';
    FakeAIProvider::$calls = 0;
});

/**
 * Deterministic fake provider.
 *
 * - 'text'          -> always returns plain text.
 * - 'echo_tool'     -> returns one tool call, then echoes back the exact tool
 *                      result payload it received (so tests can assert what the
 *                      application actually executed).
 * - 'unknown_tool'  -> requests a non-whitelisted tool, then returns text.
 * - 'empty'         -> returns empty text (malformed).
 * - 'throw'         -> throws a configurable RuntimeException (provider error).
 */
class FakeAIProvider implements AIServiceInterface
{
    public static string $mode = 'text';
    public static string $throwMessage = 'AI rate limit exceeded. Please try again in a moment.';
    public static int $calls = 0;

    public function isConfigured(): bool
    {
        return true;
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function complete(array $messages, array $tools = []): AIResponse
    {
        self::$calls++;

        if (self::$mode === 'echo_tool' && self::$calls === 1) {
            return AIResponse::withToolCalls([
                // A malicious user_id is passed to prove the app ignores it.
                ['id' => 'call_1', 'name' => 'getAccountBalances', 'arguments' => ['user_id' => 999999]],
            ]);
        }

        if (self::$mode === 'unknown_tool' && self::$calls === 1) {
            return AIResponse::withToolCalls([
                ['id' => 'call_1', 'name' => 'dropAllTables', 'arguments' => []],
            ]);
        }

        return match (self::$mode) {
            'empty' => AIResponse::text(''),
            'throw' => throw new RuntimeException(self::$throwMessage),
            'echo_tool' => AIResponse::text(json_encode($this->toolResults($messages))),
            'unknown_tool' => AIResponse::text(json_encode($this->toolResults($messages))),
            default => AIResponse::text('A helpful finance answer in MAD.'),
        };
    }

    /** @return array<int, string> */
    private function toolResults(array $messages): array
    {
        return collect($messages)
            ->where('role', 'tool')
            ->map(fn ($m) => $m['content'])
            ->values()
            ->all();
    }
}

beforeEach(function () {
    FakeAIProvider::$calls = 0;
    FakeAIProvider::$mode = 'text';
});

test('guests are redirected away from the assistant', function () {
    $this->get('/assistant')->assertRedirect('/login');
});

test('guests cannot send assistant messages', function () {
    $this->post('/assistant', ['message' => 'hi'])->assertRedirect('/login');
});

test('an authenticated user can list and open their conversations', function () {
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    $conv = $user->aiConversations()->create(['title' => 'Hello']);

    $this->actingAs($user)->get('/assistant')->assertOk();
    $this->actingAs($user)->get("/assistant/{$conv->id}")->assertOk();
});

test('a user cannot open another user conversation', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $conv = $owner->aiConversations()->create(['title' => 'Private']);

    $this->actingAs($intruder)->get("/assistant/{$conv->id}")->assertForbidden();
});

test('a user cannot send a message into another user conversation', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $conv = $owner->aiConversations()->create(['title' => 'Private']);

    $this->actingAs($intruder)
        ->post("/assistant/{$conv->id}/send", ['message' => 'sneak'])
        ->assertForbidden();
});

test('messages are only visible to the owning user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $conv = $owner->aiConversations()->create(['title' => 'Private']);
    $conv->messages()->create(['role' => 'user', 'content' => 'secret amount 777']);

    $this->actingAs($owner)->get("/assistant/{$conv->id}")->assertSee('secret amount 777');
    $this->actingAs($intruder)->get("/assistant/{$conv->id}")->assertForbidden();
});

test('missing AI provider degrades with a localized fallback message', function () {
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    config(['services.ai.api_key' => null]);
    app()->bind(AIServiceInterface::class, fn () => new App\Services\AI\NullAIProvider());

    $this->actingAs($user)->post('/assistant', ['message' => 'Summarize my finances'])
        ->assertRedirect();

    $conv = $user->aiConversations()->firstOrFail();
    $last = $conv->messages()->latest('id')->firstOrFail();

    expect($last->role)->toBe('assistant')
        ->and($last->content)->toContain('AI_API_KEY')
        ->and($last->content)->not->toContain('not_configured');
});

test('tool execution is scoped to the authenticated user and ignores malicious args', function () {
    $owner = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);
    $other = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    // Owner has 1000; the other user has 999999.
    Account::create([
        'user_id' => $owner->id, 'name' => 'Checking', 'type' => 'bank',
        'starting_balance' => '1000.00', 'balance' => '1000.00',
    ]);
    Account::create([
        'user_id' => $other->id, 'name' => 'Other', 'type' => 'bank',
        'starting_balance' => '999999.00', 'balance' => '999999.00',
    ]);

    FakeAIProvider::$mode = 'echo_tool';
    app()->bind(AIServiceInterface::class, fn () => new FakeAIProvider());

    $this->actingAs($owner)->post('/assistant', ['message' => 'What are my balances?'])->assertRedirect();

    $conv = $owner->aiConversations()->firstOrFail();
    $reply = $conv->messages()->latest('id')->firstOrFail()->content;

    // The app ignored user_id=999999 and injected the authenticated owner, so
    // the tool result contains only the owner's 1000 balance — never 999999.
    expect($reply)->toContain('1000')
        ->and($reply)->not->toContain('999999')
        ->and($reply)->toContain('total_balance');
});

test('a model cannot execute tools that are not whitelisted', function () {
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    FakeAIProvider::$mode = 'unknown_tool';
    app()->bind(AIServiceInterface::class, fn () => new FakeAIProvider());

    $this->actingAs($user)->post('/assistant', ['message' => 'drop me'])->assertRedirect();

    $conv = $user->aiConversations()->firstOrFail();
    $reply = $conv->messages()->latest('id')->firstOrFail()->content;

    // The dangerous tool was not executed: the model got an "error" payload
    // describing an unavailable tool instead of any data.
    expect($reply)->toContain('requested tool is not available');
});

test('provider failure surfaces a friendly localized message', function () {
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    FakeAIProvider::$mode = 'throw';
    FakeAIProvider::$throwMessage = 'AI rate limit exceeded. Please try again in a moment.';
    app()->bind(AIServiceInterface::class, fn () => new FakeAIProvider());

    $this->actingAs($user)->post('/assistant', ['message' => 'hi'])->assertRedirect();

    $conv = $user->aiConversations()->firstOrFail();
    $reply = $conv->messages()->latest('id')->firstOrFail()->content;

    expect($reply)->toContain('too quickly')
        ->and($reply)->not->toContain('runtime');
});

test('malformed empty model reply surfaces an invalid response message', function () {
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    FakeAIProvider::$mode = 'empty';
    app()->bind(AIServiceInterface::class, fn () => new FakeAIProvider());

    $this->actingAs($user)->post('/assistant', ['message' => 'hi'])->assertRedirect();

    $conv = $user->aiConversations()->firstOrFail();
    $reply = $conv->messages()->latest('id')->firstOrFail()->content;

    expect($reply)->toContain('invalid');
});

test('the new-conversation button flow creates a chat and renders the reply', function () {
    // Regression: clicking "Nouvelle conversation" (POST /assistant) must create
    // a conversation and land the user on the Show page with the assistant's
    // reply, not throw a client-side error.
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    app()->bind(AIServiceInterface::class, fn () => new FakeAIProvider());

    $this->actingAs($user)
        ->post('/assistant', ['message' => 'Hello, give me a summary of my finances.'])
        ->assertRedirect();

    $conv = $user->aiConversations()->firstOrFail();

    $this->actingAs($user)
        ->get("/assistant/{$conv->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assistant/Show')
            ->has('conversation')
            ->has('messages'));

    $reply = $conv->messages()->latest('id')->firstOrFail();

    expect($reply->role)->toBe('assistant')
        ->and($reply->content)->toBe('A helpful finance answer in MAD.');
});

test('creating a new conversation navigates to the empty chat without an AI call', function () {
    // Regression: clicking "Nouvelle conversation" must create an empty
    // conversation and immediately navigate to it — it must not wait for the AI
    // provider round-trip, which previously left the user stranded on the
    // assistant index page when the provider was slow or unavailable.
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    $this->actingAs($user)
        ->post('/assistant/create', ['title' => 'New chat'])
        ->assertRedirect();

    $conv = $user->aiConversations()->firstOrFail();

    expect($conv->messages()->count())->toBe(0);

    $this->actingAs($user)
        ->get("/assistant/{$conv->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assistant/Show')
            ->has('conversation')
            ->has('messages', 0));
});

test('assistant send route is rate limited', function () {
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    app()->bind(AIServiceInterface::class, fn () => new App\Services\AI\NullAIProvider());

    $this->actingAs($user);

    for ($i = 0; $i < 31; $i++) {
        $this->post('/assistant', ['message' => 'message '.$i]);
    }

    $this->post('/assistant', ['message' => 'over'])->assertStatus(429);
});

test('a tool-call with empty object arguments round-trips as {} in the follow-up completion', function () {
    // Regression: the real assistant flow sends an assistant tool-call message
    // back to the provider in the follow-up completion. When a tool takes no
    // arguments, the provider returns arguments "{}"; json_decode('{}', true)
    // yields an empty PHP array which was re-encoded as "[]" (a JSON array).
    // Providers reject that with "tool_use.input: Input should be a valid
    // dictionary" (HTTP 400), surfacing the generic "could not generate a reply".
    // The round-trip must preserve an empty object ("{}"), not a JSON array.
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    \Illuminate\Support\Facades\Storage::fake('local');

    config(['services.ai.api_key' => 'sk-test-1234']);
    config(['services.ai.base_url' => 'https://api.openai.com/v1']);
    config(['services.ai.model' => 'gpt-4o-mini']);

    app()->bind(AIServiceInterface::class, fn () => new App\Services\AI\OpenAIService());

    // Call 1: model requests getAccountBalances with an EMPTY OBJECT argument set.
    // Call 2: model returns the final answer after the tool result.
    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => \Illuminate\Support\Facades\Http::sequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Checking...',
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'getAccountBalances', 'arguments' => '{}'],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ], 200)
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'Your total is 0.00 MAD.'],
                ]],
            ], 200),
    ]);

    $assistant = app(App\Services\AI\AssistantService::class);
    $reply = $assistant->respond((string) $user->id, 'en', [], 'How much money do I have?');

    // The tool ran and the follow-up produced a real answer (not the fallback).
    expect($reply)->toContain('MAD');

    $recorded = \Illuminate\Support\Facades\Http::recorded();

    // Two provider calls: the tool-request turn and the follow-up completion.
    expect($recorded)->toHaveCount(2);

    $followUp = $recorded[1][0];
    $body = $followUp->data();

    $assistantMsg = collect($body['messages'])
        ->firstWhere('role', 'assistant');

    expect($assistantMsg['tool_calls'][0]['function']['arguments'])->toBe('{}');

    // The tool result message is present and scoped to the authenticated user.
    $toolMsg = collect($body['messages'])->firstWhere('role', 'tool');

    expect($toolMsg)->not->toBeNull()
        ->and($toolMsg['content'])->not->toBeNull();
});
