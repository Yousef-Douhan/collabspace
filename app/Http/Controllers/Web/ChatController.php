<?php

namespace App\Http\Controllers\Web;

use App\Enums\ConversationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private ChatService $chats) {}

    /** /chats?tab=inbox|projects  and  /chats/{conversation} */
    public function index(Request $request, ?Conversation $conversation = null): View
    {
        $user = $request->user();
        $tab = $request->input('tab', $conversation?->project_id ? 'projects' : 'inbox');

        $conversations = $this->chats->conversationsFor($user)
            ->wherePivot('is_archived', false)
            ->when($tab === 'projects', fn ($q) => $q->whereNotNull('project_id'), fn ($q) => $q->whereNull('project_id'))
            ->orderByDesc('conversation_user.is_pinned')
            ->orderByDesc('conversations.updated_at')
            ->get();

        $messages = collect();
        if ($conversation) {
            Gate::authorize('view', $conversation);
            $conversation->load('participants');

            $this->chats->markRead($user, $conversation);
            $messages = $this->threadMessages($conversation);
        }

        return view('chats.index', [
            'tab' => $tab,
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
            'users' => User::whereKeyNot($user->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * GET /chats/{conversation}/poll — the thread's message list as HTML, polled by the page
     * every few seconds so new messages, edits and read receipts appear without a reload.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $this->chats->markRead($request->user(), $conversation);
        $messages = $this->threadMessages($conversation->load('participants'));

        return response()->json([
            'html' => view('chats._messages', ['messages' => $messages])->render(),
            'last_id' => $messages->last()?->id,
        ]);
    }

    /**
     * Last 60 messages, oldest first, each with `readers` (participants who have seen it).
     *
     * @return Collection<int, Message>
     */
    private function threadMessages(Conversation $conversation): Collection
    {
        $messages = $conversation->messages()->with('sender')->latest('id')->limit(60)->get()->reverse()->values();
        $readers = $conversation->participants;

        foreach ($messages as $m) {
            $m->readers = $readers->filter(fn (User $u) => $u->id !== $m->sender_id
                && $u->pivot->last_read_at !== null
                && $u->pivot->last_read_at >= $m->created_at->toDateTimeString());
        }

        return $messages;
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $conversation = $data['type'] === ConversationType::Direct->value
            ? $this->chats->startDirect($user, User::findOrFail($data['user_id']))
            : $this->chats->createChannel($user, $data['name'], $data['members'] ?? [], $data['project_id'] ?? null);

        return redirect()->route('chats.show', $conversation);
    }

    public function send(SendMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);

        $this->chats->send($request->user(), $conversation, $request->validated('body'), $request->file('attachment'));

        return redirect()->route('chats.show', $conversation);
    }

    public function updateMessage(Request $request, Message $message): RedirectResponse
    {
        Gate::authorize('update', $message);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $this->chats->edit($message, $data['body']);

        return back();
    }

    public function destroyMessage(Message $message): RedirectResponse
    {
        Gate::authorize('delete', $message);

        $this->chats->remove($message);

        return back();
    }

    public function attachment(Message $message): StreamedResponse
    {
        Gate::authorize('view', $message->conversation);
        abort_unless($message->attachment_path && Storage::disk(ChatService::DISK)->exists($message->attachment_path), 404);

        return Storage::disk(ChatService::DISK)->download($message->attachment_path, $message->attachment_name);
    }

    /** Pin / archive for the current user. */
    public function state(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);

        $data = $request->validate(['is_pinned' => ['sometimes', 'boolean'], 'is_archived' => ['sometimes', 'boolean']]);
        $this->chats->updateState($request->user(), $conversation, $data);

        return ($data['is_archived'] ?? false) ? redirect()->route('chats.index') : back();
    }
}
