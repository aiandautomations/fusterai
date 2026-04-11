<?php

namespace App\Http\Controllers;

use App\Events\AgentStatusChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgentStatusController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:online,away,busy,offline'],
        ]);

        $user = $request->user();
        $user->status = $request->status;
        $user->save();

        broadcast(new AgentStatusChanged($user->workspace_id, $user->id, $user->status))->toOthers();

        return back();
    }
}
