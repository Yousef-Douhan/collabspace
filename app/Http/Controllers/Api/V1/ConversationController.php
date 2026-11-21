<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController extends Controller
{
    public function __construct(private ChatService $chats) {}

    /** GET /conversations?type=direct|channel&archived=1&q= — pinned first, then most recent activity. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->chats->conversationsFor($request->user())
            ->wherePivot('is_archived', $request->boolean('archived'))
            ->when($request->filled('type'), fn ($q) => $q->where('conversations.type', $request->input('type')))
            ->orderByPivot('is_pinned', 'desc')
            ->orderByDesc('conversations.updated_at')
            ->get();

        if ($request->filled('q')) {
            $needle = mb_strtolower($request->input('q'));
            $conversations = $conversations->filter(
                fn (Conversation $c) => str_contains(mb_strtolower($c->displayNameFor($request->user())), $needle)
            )->values();
        }

        return ConversationResource::collection($conversations);
    }

    /** POST /conversations  {type: direct, user_id} | {type: channel, name, members[], project_id?} */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $me = $request->user();

        $conversation = $request->input('type') === ConversationType::Direct->value
            ? $this->chats->startDirect($me, User::findOrFail($request->integer('user_id')))
            : $this->chats->createChannel(
                $me,
                $request->input('name'),
                $request->input('members', []),
                $request->filled('project_id') ? $request->integer('project_id') : null,
            );

        return $this->single($request, $conversation)
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /** PATCH /conversations/{conversation}  {is_pinned?, is_archived?} — affects only you. */
    public function update(Request $request, Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        $state = $request->validate([
            'is_pinned' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
        ]);

        $this->chats->updateState($request->user(), $conversation, $state);

        return $this->single($request, $conversation);
    }

    /** GET /conversations/{conversation}/messages — newest first, cursor-paginated; marks the chat as read. */
    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderByDesc('id')
            ->cursorPaginate(min(max($request->integer('per_page', 30), 1), 100));

        $this->chats->markRead($request->user(), $conversation);

        return MessageResource::collection($messages);
    }

    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->chats->send(
            $request->user(),
            $conversation,
            $request->input('body'),
            $request->file('attachment'),
        );

        return (new MessageResource($message->load('sender')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function read(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $this->chats->markRead($request->user(), $conversation);

        return response()->noContent();
    }

    /** GET /messages/{message}/attachment — participants only. */
    public function attachment(Message $message): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $message->conversation);

        if (! $message->attachment_path || ! Storage::disk(ChatService::DISK)->exists($message->attachment_path)) {
            return response()->json(['message' => 'Attachment not found.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk(ChatService::DISK)->download($message->attachment_path, $message->attachment_name);
    }

    private function single(Request $request, Conversation $conversation): ConversationResource
    {
        $fresh = $this->chats->conversationsFor($request->user())
            ->where('conversations.id', $conversation->id)
            ->firstOrFail();

        return new ConversationResource($fresh);
    }
}
