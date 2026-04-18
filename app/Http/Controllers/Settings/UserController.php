<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Mailbox\Models\Mailbox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateMailboxesRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $workspaceId = $request->user()->workspace_id;

        $users = User::where('workspace_id', $workspaceId)
            ->with(['mailboxes:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'avatar' => $u->avatar,
                'mailboxes' => $u->mailboxes
                    ->map(fn ($mb) => ['id' => $mb->id, 'name' => $mb->name])
                    ->values()
                    ->all(),
            ]);

        $mailboxes = Mailbox::where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Settings/Users', [
            'users' => $users,
            'mailboxes' => $mailboxes,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $user = $this->service->invite($request->validated(), $request->user()->workspace_id);

        return redirect()->back()->with('success', 'Invitation sent to '.$user->email.'.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->service->update($user, $request->validated());

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);
        $this->service->delete($user);

        return redirect()->back()->with('success', 'User removed successfully.');
    }

    public function updateMailboxes(UpdateMailboxesRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->service->syncMailboxes($user, $request->mailbox_ids ?? []);

        return redirect()->back()->with('success', 'Mailbox access updated.');
    }
}
