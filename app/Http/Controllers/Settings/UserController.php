<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Domains\Mailbox\Models\Mailbox;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                'id'        => $u->id,
                'name'      => $u->name,
                'email'     => $u->email,
                'role'      => $u->role,
                'avatar'    => $u->avatar,
                'mailboxes' => $u->mailboxes->map(fn ($mb) => ['id' => $mb->id, 'name' => $mb->name]),
            ]);

        $mailboxes = Mailbox::where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Settings/Users', [
            'users'     => $users,
            'mailboxes' => $mailboxes,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role'  => ['required', 'in:super_admin,admin,manager,agent'],
        ]);

        $user = $this->service->invite($validated, $request->user()->workspace_id);

        return redirect()->back()->with('success', 'Invitation sent to ' . $user->email . '.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $this->service->update($user, $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:super_admin,admin,manager,agent'],
        ]));

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);
        $this->service->delete($user);

        return redirect()->back()->with('success', 'User removed successfully.');
    }

    public function updateMailboxes(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $request->validate([
            'mailbox_ids'   => ['nullable', 'array'],
            'mailbox_ids.*' => ['integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $request->user()->workspace_id)],
        ]);

        $this->service->syncMailboxes($user, $request->mailbox_ids ?? []);

        return redirect()->back()->with('success', 'Mailbox access updated.');
    }
}
