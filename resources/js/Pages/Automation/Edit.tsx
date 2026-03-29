import { Link, useForm } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Checkbox } from '@/Components/ui/checkbox'
import { Card, CardContent } from '@/Components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'

type Trigger = { value: string; label: string }
type ConditionField = { value: string; label: string; operators: string[] }
type ActionType = { value: string; label: string }

type Condition = { field: string; operator: string; value: string }
type Action    = { type: string; value: string }

interface Rule {
  id: number
  name: string
  description: string | null
  trigger: string
  conditions: Condition[]
  actions: Action[]
  active: boolean
}

interface Props {
  rule: Rule
  triggers: Trigger[]
  conditionFields: ConditionField[]
  actionTypes: ActionType[]
}

export default function AutomationEdit({ rule, triggers, conditionFields, actionTypes }: Props) {
  const { data, setData, put, processing, errors } = useForm({
    name:        rule.name,
    description: rule.description ?? '',
    trigger:     rule.trigger,
    conditions:  rule.conditions as Condition[],
    actions:     rule.actions as Action[],
    active:      rule.active,
  })

  function addCondition() {
    setData('conditions', [...data.conditions, { field: conditionFields[0]?.value ?? '', operator: 'equals', value: '' }])
  }

  function removeCondition(i: number) {
    setData('conditions', data.conditions.filter((_, idx) => idx !== i))
  }

  function updateCondition(i: number, key: keyof Condition, value: string) {
    const updated = [...data.conditions]
    updated[i] = { ...updated[i], [key]: value }
    setData('conditions', updated)
  }

  function addAction() {
    setData('actions', [...data.actions, { type: actionTypes[0]?.value ?? '', value: '' }])
  }

  function removeAction(i: number) {
    setData('actions', data.actions.filter((_, idx) => idx !== i))
  }

  function updateAction(i: number, key: keyof Action, value: string) {
    const updated = [...data.actions]
    updated[i] = { ...updated[i], [key]: value }
    setData('actions', updated)
  }

  function submit(e: React.FormEvent) {
    e.preventDefault()
    put(`/automation/${rule.id}`)
  }

  return (
    <AppLayout title="Edit Automation Rule">
      <div className="w-full px-6 py-8 space-y-6">
        <div>
          <h1 className="text-3xl font-semibold tracking-tight">Edit Automation Rule</h1>
          <p className="mt-1 text-sm text-muted-foreground">Adjust trigger logic and actions for this rule.</p>
        </div>

        <Card className="bg-card/75">
          <CardContent className="p-6">
        <form onSubmit={submit} className="space-y-6">
          {/* Name */}
          <div className="space-y-1">
            <Label>Rule name</Label>
            <Input
              value={data.name}
              onChange={e => setData('name', e.target.value)}
              placeholder="e.g. Auto-assign urgent conversations"
              required
            />
            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
          </div>

          {/* Trigger */}
          <div className="space-y-1">
            <Label>When (trigger)</Label>
            <Select value={data.trigger} onValueChange={(value) => setData('trigger', value)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {triggers.map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>

          {/* Conditions */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label>Conditions (all must match)</Label>
              <Button type="button" variant="ghost" size="sm" onClick={addCondition} className="text-xs">
                + Add condition
              </Button>
            </div>
            {data.conditions.length === 0 && (
              <p className="text-xs text-muted-foreground">No conditions — rule runs on every {data.trigger} event.</p>
            )}
            {data.conditions.map((c, i) => (
              <div key={i} className="flex items-center gap-2">
                <Select value={c.field} onValueChange={(value) => updateCondition(i, 'field', value)}>
                  <SelectTrigger className="flex-1">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {conditionFields.map(f => <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Select value={c.operator} onValueChange={(value) => updateCondition(i, 'operator', value)}>
                  <SelectTrigger className="w-44">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {(conditionFields.find(f => f.value === c.field)?.operators ?? ['equals']).map(op => (
                      <SelectItem key={op} value={op}>{op.replace('_', ' ')}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Input
                  className="flex-1"
                  value={c.value}
                  onChange={e => updateCondition(i, 'value', e.target.value)}
                  placeholder="value"
                />
                <Button type="button" variant="ghost" size="icon-xs" onClick={() => removeCondition(i)} className="text-destructive">×</Button>
              </div>
            ))}
          </div>

          {/* Actions */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label>Actions</Label>
              <Button type="button" variant="ghost" size="sm" onClick={addAction} className="text-xs">
                + Add action
              </Button>
            </div>
            {data.actions.map((a, i) => (
              <div key={i} className="flex items-center gap-2">
                <Select value={a.type} onValueChange={(value) => updateAction(i, 'type', value)}>
                  <SelectTrigger className="flex-1">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {actionTypes.map(at => <SelectItem key={at.value} value={at.value}>{at.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Input
                  className="flex-1"
                  value={a.value}
                  onChange={e => updateAction(i, 'value', e.target.value)}
                  placeholder="value (e.g. urgent, 5)"
                />
                {data.actions.length > 1 && (
                  <Button type="button" variant="ghost" size="icon-xs" onClick={() => removeAction(i)} className="text-destructive">×</Button>
                )}
              </div>
            ))}
          </div>

          {/* Active toggle */}
          <div className="flex items-center gap-3">
            <Checkbox
              id="active"
              checked={data.active}
              onCheckedChange={checked => setData('active', !!checked)}
            />
            <Label htmlFor="active">Active</Label>
          </div>

          <div className="flex items-center gap-4 pt-2">
            <Button type="submit" disabled={processing}>
              {processing ? 'Saving…' : 'Save Changes'}
            </Button>
            <Button asChild variant="ghost">
              <Link href="/automation">Cancel</Link>
            </Button>
          </div>
        </form>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}
