<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\AiConversation;
use App\Services\AI\AssistantService;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(private AssistantService $assistant)
    {
    }

    public function index(Request $request)
    {
        $conversations = $request->user()->aiConversations()
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'conversations' => ConversationResource::collection($conversations),
                'ai_enabled' => ! blank(config('services.ai.api_key')),
            ],
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

        return response()->json([
            'data' => ['conversation' => new ConversationResource($conversation->loadCount('messages'))],
            'message' => __('Conversation created.'),
        ], 201);
    }

    public function show(Request $request, AiConversation $conversation)
    {
        $this->authorize('view', $conversation);

        return response()->json([
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
                'messages' => $conversation->messages()->get(['id', 'role', 'content', 'created_at'])
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'role' => $m->role,
                        'content' => $m->content,
                        'created_at' => $m->created_at?->toDateTimeString(),
                    ])->values(),
                'ai_enabled' => ! blank(config('services.ai.api_key')),
            ],
        ]);
    }

    public function send(Request $request, AiConversation $conversation)
    {
        $this->authorize('update', $conversation);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

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

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'reply' => [
                    'role' => 'assistant',
                    'content' => $reply,
                ],
            ],
            'message' => __('Response generated.'),
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return response()->json(['message' => __('Conversation deleted.')]);
    }
}