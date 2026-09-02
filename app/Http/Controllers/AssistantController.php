<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\AI\AssistantService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssistantController extends Controller
{
    public function __construct(
        private AssistantService $assistant,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = $user->aiConversations()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'message_count' => $c->messages_count,
                'updated_at' => $c->updated_at?->diffForHumans(),
            ])->values();

        return Inertia::render('Assistant/Index', [
            'conversations' => $conversations,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $request->user()->aiConversations()->create([
            'title' => $data['title'] ?? __('New conversation'),
        ]);

        return redirect()->route('assistant.show', $conversation);
    }

    public function show(Request $request, AiConversation $conversation)
    {
        $this->authorize('view', $conversation);

        return Inertia::render('Assistant/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
            'messages' => $conversation->messages()->get(['id', 'role', 'content', 'created_at'])->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toDateTimeString(),
            ])->values(),
            'aiEnabled' => ! blank(config('services.ai.api_key')),
        ]);
    }

    public function send(Request $request, ?AiConversation $conversation = null)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        if ($conversation) {
            $this->authorize('update', AiConversation::find($conversation->id));
        } elseif (data_get($data, 'conversation_id')) {
            $conversation = AiConversation::query()->findOrFail($data['conversation_id']);
            $this->authorize('update', $conversation);
        }

        $user = $request->user();

        if (! $conversation) {
            $conversation = $user->aiConversations()->create([
                'title' => mb_substr($data['message'], 0, 60),
            ]);
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        $reply = $this->assistant->respond($user->id, $user->locale, $history, $data['message']);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        return redirect()->route('assistant.show', $conversation)->with('success', __('Response generated.'));
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()->route('assistant.index')->with('success', __('Conversation deleted.'));
    }
}
