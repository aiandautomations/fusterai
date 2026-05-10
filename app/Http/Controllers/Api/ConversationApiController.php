<?php

namespace App\Http\Controllers\Api;

use App\Domains\Conversation\Models\Conversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConversationIndexRequest;
use App\Http\Requests\Api\ReplyConversationRequest;
use App\Http\Requests\Api\StoreConversationRequest;
use App\Http\Requests\Api\UpdateConversationRequest;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Conversations
 */
class ConversationApiController extends Controller
{
    public function __construct(private ConversationService $service) {}

    /**
     * List conversations
     *
     * Returns a paginated list of conversations in the authenticated user's workspace.
     * Filter by status, mailbox, priority, or assigned agent.
     *
     * @queryParam status string Filter by status. Allowed: open, pending, closed, spam. Example: open
     * @queryParam mailbox_id integer Filter by mailbox ID. Example: 1
     * @queryParam priority string Filter by priority. Allowed: low, normal, high, urgent. Example: high
     * @queryParam assigned_user_id integer Filter by assigned agent ID. Example: 3
     * @queryParam per_page integer Number of results per page (default 30). Example: 15
     *
     * @response 200 scenario="Success" {"data": [], "current_page": 1, "per_page": 30, "total": 0}
     * @response 401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function index(ConversationIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $query = Conversation::query()
            ->where('workspace_id', $user->workspace_id)
            ->with(['customer', 'mailbox', 'assignedUser', 'tags'])
            ->orderByDesc('last_reply_at');

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['mailbox_id'] ?? null) {
            $query->where('mailbox_id', $filters['mailbox_id']);
        }
        if ($filters['priority'] ?? null) {
            $query->where('priority', $filters['priority']);
        }
        if ($filters['assigned_user_id'] ?? null) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }

        $conversations = $query->paginate($filters['per_page'] ?? 30);

        return response()->json($conversations);
    }

    /**
     * Get a conversation
     *
     * Returns a single conversation with all threads, customer, mailbox, tags, and attachments.
     *
     * @response 200 scenario="Success" {"id": 1, "subject": "Help needed", "status": "open", "threads": []}
     * @response 404 scenario="Not found" {"message": "Resource not found."}
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->workspace_id !== $request->user()->workspace_id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $conversation->load([
            'customer',
            'mailbox',
            'assignedUser',
            'tags',
            'threads.user',
            'threads.customer',
            'threads.attachments',
        ]);

        return response()->json($conversation);
    }

    /**
     * Create a conversation
     *
     * Creates a new conversation and an initial customer message thread.
     * If the customer email doesn't exist in the workspace, a new customer record is created.
     *
     * @bodyParam subject string required Conversation subject. Example: "Billing question"
     * @bodyParam customer_email string required Customer email address. Example: "alice@example.com"
     * @bodyParam customer_name string Customer display name. Example: "Alice Smith"
     * @bodyParam body string required Initial message body. Example: "I have a question about my invoice."
     * @bodyParam mailbox_id integer Mailbox to assign. Example: 1
     * @bodyParam priority string Priority level. Allowed: low, normal, high, urgent. Example: "normal"
     * @bodyParam status string Initial status. Allowed: open, pending, closed. Example: "open"
     *
     * @response 201 scenario="Created" {"id": 42, "subject": "Billing question", "status": "open"}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {}}
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $user = $request->user();

        ['conversation' => $conversation] = $this->service->createViaApi($request->validated(), $user->workspace_id, $user);

        return response()->json($conversation->load(['customer', 'threads']), 201);
    }

    /**
     * Update a conversation
     *
     * Update the status, priority, or assigned agent of a conversation.
     *
     * @bodyParam status string Conversation status. Allowed: open, pending, closed, spam. Example: "closed"
     * @bodyParam priority string Priority level. Allowed: low, normal, high, urgent. Example: "urgent"
     * @bodyParam assigned_user_id integer|null Agent user ID to assign. Send null to unassign. Example: 5
     *
     * @response 200 scenario="Success" {"id": 1, "status": "closed", "priority": "urgent"}
     * @response 404 scenario="Not found" {"message": "Resource not found."}
     */
    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->workspace_id !== $request->user()->workspace_id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Use has() to detect explicitly-sent keys; array_filter would strip intentional nulls (e.g. assigned_user_id: null to unassign)
        $fields = array_filter(
            $request->validated(),
            fn ($key) => $request->has($key),
            ARRAY_FILTER_USE_KEY,
        );

        $fresh = $this->service->updateViaApi($conversation, $fields, $request->user());

        return response()->json($fresh);
    }

    /**
     * Reply to a conversation
     *
     * Adds a new message or internal note to the conversation thread.
     * Broadcasts a real-time update to all agents viewing the conversation.
     *
     * @bodyParam body string required Reply body (HTML or plain text). Example: "Thanks for reaching out! I'll look into this."
     * @bodyParam type string Thread type. Allowed: message, note. Default: message. Example: "message"
     *
     * @response 201 scenario="Created" {"id": 99, "type": "message", "body": "Thanks for reaching out!"}
     * @response 404 scenario="Not found" {"message": "Resource not found."}
     * @response 422 scenario="Validation error" {"message": "The given data was invalid.", "errors": {}}
     */
    public function reply(ReplyConversationRequest $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->workspace_id !== $request->user()->workspace_id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validated();

        $thread = $this->service->replyViaApi(
            $conversation,
            $validated['body'],
            $validated['type'] ?? 'message',
            $request->user(),
        );

        return response()->json($thread->load('user'), 201);
    }
}
