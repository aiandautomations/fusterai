<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Domains\Mailbox\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
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

        $user = User::create([
            'workspace_id' => $request->user()->workspace_id,
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'role'         => $validated['role'],
            // Placeholder password — user will set their own via invite link
            'password'     => Hash::make(Str::random(32)),
        ]);

        // Send invite email so the user can set their own password
        Password::sendResetLink(['email' => $user->email]);

        return redirect()->back()->with('success', 'Invitation sent to ' . $user->email . '.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:super_admin,admin,manager,agent'],
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->back()->with('success', 'User removed successfully.');
    }

    public function updateMailboxes(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $workspaceId = $request->user()->workspace_id;
        $request->validate([
            'mailbox_ids'   => ['nullable', 'array'],
            'mailbox_ids.*' => ['integer', Rule::exists('mailboxes', 'id')->where('workspace_id', $workspaceId)],
        ]);

        $user->mailboxes()->sync($request->mailbox_ids ?? []);

        return redirect()->back()->with('success', 'Mailbox access updated.');
    }
}
