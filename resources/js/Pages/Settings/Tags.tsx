import React, { useState } from 'react'
import { useForm, router } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import ColorPicker from '@/Components/ColorPicker'
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { TagIcon, SearchIcon, MoreHorizontalIcon, CheckIcon, PencilIcon } from 'lucide-react'

interface Tag {
    id: number
    name: string
    color: string
    conversations_count: number
}

interface Props { tags: Tag[] }


// ── Tag chip preview ──────────────────────────────────────────────────────────

function TagChip({ name, color }: { name: string; color: string }) {
    return (
        <span
            className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
            style={{ backgroundColor: color + '22', color }}
        >
            {name || 'preview'}
        </span>
    )
}

// ── Inline edit form ──────────────────────────────────────────────────────────

function EditTagForm({ tag, onCancel }: { tag: Tag; onCancel: () => void }) {
    const { data, setData, patch, processing } = useForm({ name: tag.name, color: tag.color })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        patch(`/tags/${tag.id}`, { onSuccess: onCancel })
    }

    return (
        <form onSubmit={submit} className="px-4 pb-4 pt-3 border-t border-border bg-muted/30 space-y-3">
            <div className="flex items-end gap-3">
                <div className="flex-1 space-y-1.5">
                    <Label className="text-xs">Name</Label>
                    <div className="flex items-center gap-2">
                        <Input
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            className="h-8 text-sm"
                            required
                            autoFocus
                        />
                        <TagChip name={data.name} color={data.color} />
                    </div>
                </div>
            </div>
            <div className="space-y-1.5">
                <Label className="text-xs">Color</Label>
                <ColorPicker value={data.color} onChange={c => setData('color', c)} />
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={processing} className="h-7 text-xs">Save</Button>
                <Button type="button" size="sm" variant="ghost" onClick={onCancel} className="h-7 text-xs">Cancel</Button>
            </div>
        </form>
    )
}

// ── Tag row ───────────────────────────────────────────────────────────────────

function TagRow({ tag, editingId, setEditingId }: {
    tag: Tag
    editingId: number | null
    setEditingId: (id: number | null) => void
}) {
    const isEditing = editingId === tag.id

    function destroy() {
        if (!confirm(`Delete tag "${tag.name}"? This will remove it from all conversations.`)) return
        router.delete(`/tags/${tag.id}`)
    }

    return (
        <div className={cn(
            'rounded-xl border border-border bg-background overflow-hidden transition-shadow',
            isEditing && 'shadow-sm ring-1 ring-primary/20',
        )}>
            <div className="flex items-center gap-4 px-4 py-3">
                {/* Color dot */}
                <span
                    className="h-3 w-3 rounded-full shrink-0"
                    style={{ backgroundColor: tag.color }}
                />

                {/* Chip preview */}
                <TagChip name={tag.name} color={tag.color} />

                {/* Name */}
                <span className="text-[13.5px] font-medium text-foreground flex-1">{tag.name}</span>

                {/* Usage count */}
                <span className="text-xs text-muted-foreground tabular-nums bg-muted/60 px-2 py-0.5 rounded-md">
                    {tag.conversations_count} {tag.conversations_count === 1 ? 'conversation' : 'conversations'}
                </span>

                {/* Actions */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button className="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted/60 hover:text-foreground transition-colors">
                            <MoreHorizontalIcon className="h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-40">
                        <DropdownMenuItem onClick={() => setEditingId(isEditing ? null : tag.id)}>
                            <PencilIcon className="h-3.5 w-3.5 mr-2" />
                            {isEditing ? 'Cancel edit' : 'Edit tag'}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="text-destructive focus:text-destructive" onClick={destroy}>
                            Delete tag
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {isEditing && <EditTagForm tag={tag} onCancel={() => setEditingId(null)} />}
        </div>
    )
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function SettingsTags({ tags }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null)
    const [search, setSearch] = useState('')
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', color: '#6366f1' })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post('/tags', { onSuccess: () => reset() })
    }

    const filtered = tags.filter(t => t.name.toLowerCase().includes(search.toLowerCase()))

    return (
        <AppLayout title="Tags">
            <div className="w-full px-6 py-8 space-y-6">

                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Tags</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Organise conversations with labels. Tags appear in the sidebar and on conversation rows.
                    </p>
                </div>

                {/* Create card */}
                <div className="rounded-xl border border-border bg-card p-5 space-y-4">
                    <p className="text-[13px] font-semibold">Create a tag</p>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="flex items-end gap-3">
                            <div className="flex-1 space-y-1.5">
                                <Label className="text-xs text-muted-foreground">Tag name</Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        placeholder="e.g. billing"
                                        required
                                        className="h-9"
                                    />
                                    {data.name && <TagChip name={data.name} color={data.color} />}
                                </div>
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label className="text-xs text-muted-foreground">Color</Label>
                            <ColorPicker value={data.color} onChange={c => setData('color', c)} />
                        </div>

                        <Button type="submit" disabled={processing} size="sm">
                            {processing ? 'Creating…' : 'Create tag'}
                        </Button>
                    </form>
                </div>

                {/* Tag list */}
                {tags.length > 0 && (
                    <div className="space-y-3">
                        {/* Toolbar */}
                        <div className="flex items-center gap-3">
                            <div className="relative flex-1">
                                <SearchIcon className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground pointer-events-none" />
                                <Input
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    placeholder="Search tags…"
                                    className="pl-9 h-9"
                                />
                            </div>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground bg-muted/60 px-3 py-2 rounded-lg border border-border shrink-0">
                                <TagIcon className="h-3.5 w-3.5" />
                                <span>{tags.length} tag{tags.length !== 1 ? 's' : ''}</span>
                            </div>
                        </div>

                        {filtered.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border py-12 text-center">
                                <p className="text-sm text-muted-foreground">No tags match "{search}"</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {filtered.map(tag => (
                                    <TagRow
                                        key={tag.id}
                                        tag={tag}
                                        editingId={editingId}
                                        setEditingId={setEditingId}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {tags.length === 0 && (
                    <div className="rounded-xl border border-dashed border-border py-16 text-center space-y-2">
                        <TagIcon className="h-8 w-8 mx-auto text-muted-foreground/40" />
                        <p className="text-sm font-medium text-muted-foreground">No tags yet</p>
                        <p className="text-xs text-muted-foreground/70">Create your first tag above to start organising conversations.</p>
                    </div>
                )}

            </div>
        </AppLayout>
    )
}
