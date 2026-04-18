<?php

namespace App\Http\Controllers\Automation;

use App\Domains\Automation\Models\AutomationRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\StoreAutomationRequest;
use App\Http\Requests\Automation\UpdateAutomationRequest;
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
            'rules' => $rules,
            'triggers' => self::triggers(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', AutomationRule::class);

        return Inertia::render('Automation/Create', [
            'triggers' => self::triggers(),
            'conditionFields' => self::conditionFields(),
            'actionTypes' => self::actionTypes(),
        ]);
    }

    public function store(StoreAutomationRequest $request): RedirectResponse
    {
        $this->authorize('create', AutomationRule::class);

        $this->service->create($request->validated(), $request->user()->workspace_id);

        return redirect()->route('automation.index')->with('success', 'Automation rule created.');
    }

    public function edit(Request $request, AutomationRule $automation): Response
    {
        $this->authorize('update', $automation);

        return Inertia::render('Automation/Edit', [
            'rule' => $automation,
            'triggers' => self::triggers(),
            'conditionFields' => self::conditionFields(),
            'actionTypes' => self::actionTypes(),
        ]);
    }

    public function update(UpdateAutomationRequest $request, AutomationRule $automation): RedirectResponse
    {
        $this->authorize('update', $automation);

        $this->service->update($automation, $request->validated());

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
