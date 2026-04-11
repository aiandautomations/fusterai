<?php

namespace App\Services;

use App\Domains\Mailbox\Models\Mailbox;

class MailboxService
{
    public function create(array $validated, int $workspaceId): Mailbox
    {
        return Mailbox::create([
            'workspace_id'  => $workspaceId,
            'webhook_token' => bin2hex(random_bytes(16)),
            ...$validated,
        ]);
    }

    public function update(Mailbox $mailbox, array $validated): void
    {
        $mailbox->update($validated);
    }

    public function delete(Mailbox $mailbox): void
    {
        $mailbox->delete();
    }
}
