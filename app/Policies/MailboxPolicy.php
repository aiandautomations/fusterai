<?php

namespace App\Policies;

use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;

class MailboxPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /** Any authenticated user can view the mailbox list. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Any workspace member can view a specific mailbox. */
    public function view(User $user, Mailbox $mailbox): bool
    {
        return $user->workspace_id === $mailbox->workspace_id;
    }

    /** Only admin+ can create mailboxes. */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Only admin+ can edit mailbox settings. */
    public function update(User $user, Mailbox $mailbox): bool
    {
        return $user->isAdmin() && $user->workspace_id === $mailbox->workspace_id;
    }

    /** Only admin+ can delete mailboxes. */
    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $user->isAdmin() && $user->workspace_id === $mailbox->workspace_id;
    }
}
