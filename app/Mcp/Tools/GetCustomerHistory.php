<?php

namespace App\Mcp\Tools;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetCustomerHistory extends Tool
{
    protected string $name = 'get_customer_history';

    protected string $description = "Get a customer's support history: past conversations, open issues, and contact details.";

    public function schema(JsonSchema $schema): array
    {
        return [
            'email' => $schema->string()->description('Customer email address')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $email = $request->string('email');
        $customer = Customer::withCount('conversations')
            ->with(['conversations' => fn ($q) => $q->latest()->limit(10)])
            ->where('email', $email)
            ->first();

        if (! $customer) {
            return Response::error("No customer found with email: {$email}");
        }

        $openCount = $customer->conversations()->where('status', 'open')->count();

        return Response::json([
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'created' => $customer->created_at->toDateTimeString(),
            'total_tickets' => $customer->conversations_count,
            'open_tickets' => $openCount,
            'conversations' => $customer->conversations->map(function ($c) {
                /** @var Conversation $c */
                return [
                    'id' => $c->id,
                    'subject' => $c->subject,
                    'status' => $c->status,
                    'priority' => $c->priority,
                    'created' => $c->created_at->toDateTimeString(),
                ];
            }),
        ]);
    }
}
