<?php

namespace App\Http\Controllers;

use App\Events\AgentStatusChanged;
use App\Http\Requests\Profile\UpdateAgentStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class AgentStatusController extends Controller
{
    public function update(UpdateAgentStatusRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->status = $request->status;
        $user->save();

        Cache::forget("workspace.agent_statuses.{$user->workspace_id}");
        broadcast(new AgentStatusChanged($user->workspace_id, $user->id, $user->status))->toOthers();

        return back();
    }
}
