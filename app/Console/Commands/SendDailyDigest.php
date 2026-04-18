<?php

namespace App\Console\Commands;

use App\Domains\Conversation\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DailyDigestNotification;
use Illuminate\Console\Command;

class SendDailyDigest extends Command
{
    protected $signature = 'notifications:digest';

    protected $description = 'Send daily digest email to all active workspace agents';

    public function handle(): void
    {
        Workspace::all()->each(function (Workspace $workspace) {
            $open = Conversation::where('workspace_id', $workspace->id)->where('status', 'open')->count();
            $pending = Conversation::where('workspace_id', $workspace->id)->where('status', 'pending')->count();

            User::where('workspace_id', $workspace->id)->each(function (User $user) use ($workspace, $open, $pending) {
                $assignedToMe = Conversation::where('workspace_id', $workspace->id)
                    ->where('assigned_user_id', $user->id)
                    ->where('status', 'open')
                    ->count();

                $user->notify(new DailyDigestNotification([
                    'workspace_name' => $workspace->name,
                    'open' => $open,
                    'pending' => $pending,
                    'assigned_to_me' => $assignedToMe,
                ]));
            });
        });

        $this->info('Daily digest sent.');
    }
}
