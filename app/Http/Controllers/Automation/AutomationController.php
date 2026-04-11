<?php

namespace App\Http\Controllers\Automation;

use App\Domains\Automation\Models\AutomationRule;
use App\Http\Controllers\Controller;
use App\Services\AutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function __construct(private AutomationService $service) {}
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AutomationRule::class);

        $rules = AutomationRule::where('workspace_id', $request->user()->workspace_id)
            ->orderBy('order')
            ->get();

        return Inertia::render('Automation/Index', [
            'rules'    => $rules,
            'triggers' => self::triggers(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AutomationRule::class);

        return Inertia::render('Automation/Create', [
            'triggers'           => self::triggers(),
            'conditionFields'    => self::conditionFields(),
            'actionTypes'        => self::actionTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AutomationRule::class);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'trigger'     => ['required', 'string', 'in:' . implode(',', array_column(self::triggers(), 'value'))],
            'conditions'  => ['array'],
            'actions'     => ['required', 'array', 'min:1'],
            'active'      => ['boolean'],
        ]);

        $this->service->create($validated, $request->user()->workspace_id);

        return redirect()->route('automation.index')->with('success', 'Automation rule created.');
    }

    public function edit(Request $request, AutomationRule $automation): Response
    {
        $this->authorize('update', $automation);

        return Inertia::render('Automation/Edit', [
            'rule'            => $automation,
            'triggers'        => self::triggers(),
            'conditionFields' => self::conditionFields(),
            'actionTypes'     => self::actionTypes(),
        ]);
    }

    public function update(Request $request, AutomationRule $automation): RedirectResponse
    {
        $this->authorize('update', $automation);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'trigger'     => ['required', 'string'],
            'conditions'  => ['array'],
            'actions'     => ['required', 'array', 'min:1'],
            'active'      => ['boolean'],
        ]);

        $this->service->update($automation, $validated);

        return redirect()->route('automation.index')->with('success', 'Rule updated.');
    }

    public function destroy(Request $request, AutomationRule $automation): RedirectResponse
    {
        $this->authorize('delete', $automation);
        $this->service->delete($automation);

        return redirect()->route('automation.index')->with('success', 'Rule deleted.');
    }

    public function toggle(Request $request, AutomationRule $automation): RedirectResponse
    {
        $this->authorize('update', $automation);
        $this->service->toggle($automation);

        return back();
    }

    // ── Metadata ─────────────────────────────────────────────────────────────

    private static function triggers(): array
    {
        return [
            ['value' => 'conversation.created',  'label' => 'Conversation Created'],
            ['value' => 'conversation.replied',   'label' => 'Customer Replied'],
            ['value' => 'conversation.closed',    'label' => 'Conversation Closed'],
            ['value' => 'conversation.assigned',  'label' => 'Conversation Assigned'],
            ['value' => 'time.idle',              'label' => 'Idle for N hours'],
        ];
    }

    private static function conditionFields(): array
    {
        return [
            ['value' => 'status',   'label' => 'Status',   'operators' => ['equals', 'not_equals']],
            ['value' => 'priority', 'label' => 'Priority', 'operators' => ['equals', 'not_equals']],
            ['value' => 'channel',  'label' => 'Channel',  'operators' => ['equals', 'not_equals']],
            ['value' => 'subject',  'label' => 'Subject',  'operators' => ['contains', 'not_contains']],
            ['value' => 'assigned', 'label' => 'Assigned', 'operators' => ['equals', 'not_equals']],
        ];
    }

    private static function actionTypes(): array
    {
        return [
            ['value' => 'set_status',   'label' => 'Set Status'],
            ['value' => 'set_priority', 'label' => 'Set Priority'],
            ['value' => 'assign_to',    'label' => 'Assign To Agent'],
            ['value' => 'unassign',     'label' => 'Unassign'],
            ['value' => 'add_tag',      'label' => 'Add Tag'],
            ['value' => 'add_to_folder', 'label' => 'Add to Folder'],
            ['value' => 'move_mailbox',  'label' => 'Move to Mailbox'],
        ];
    }
}
