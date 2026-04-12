<?php

namespace App\Http\Controllers;

use App\Events\AgentStatusChanged;
use App\Http\Requests\Profile\UpdateAgentStatusRequest;
use Illuminate\Http\RedirectResponse;

class AgentStatusController extends Controller
{
    public function update(UpdateAgentStatusRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->status = $request->status;
        $user->save();

        broadcast(new AgentStatusChanged($user->workspace_id, $user->id, $user->status))->toOthers();

        return back();
    }
}
