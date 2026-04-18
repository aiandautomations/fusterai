import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import ColorPicker from '@/Components/ColorPicker';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { FolderIcon, SearchIcon, MoreHorizontalIcon, PencilIcon } from 'lucide-react';
import type { Folder } from '@/types';

interface Props {
    folders: (Folder & { conversations_count: number })[];
}

function EditFolderForm({ folder, onCancel }: { folder: Folder & { conversations_count: number }; onCancel: () => void }) {
    const { data, setData, patch, processing } = useForm({ name: folder.name, color: folder.color, icon: folder.icon });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/settings/folders/${folder.id}`, { onSuccess: onCancel });
    }

    return (
        <form onSubmit={submit} className="px-4 pb-4 pt-3 border-t border-border bg-muted/30 space-y-3">
            <div className="flex items-end gap-3">
                <div className="flex-1 space-y-1.5">
                    <Label className="text-xs">Name</Label>
                    <div className="flex items-center gap-2">
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="h-8 text-sm"
                            required
                            autoFocus
                        />
                        {data.name && <span className="h-3 w-3 rounded-full shrink-0" style={{ backgroundColor: data.color }} />}
                    </div>
                </div>
            </div>
            <div className="space-y-1.5">
                <Label className="text-xs">Color</Label>
                <ColorPicker value={data.color} onChange={(c) => setData('color', c)} />
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={processing} className="h-7 text-xs">
                    Save
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={onCancel} className="h-7 text-xs">
                    Cancel
                </Button>
            </div>
        </form>
    );
}

function FolderRow({
    folder,
    editingId,
    setEditingId,
}: {
    folder: Folder & { conversations_count: number };
    editingId: number | null;
    setEditingId: (id: number | null) => void;
}) {
    const isEditing = editingId === folder.id;

    function destroy() {
        if (!confirm(`Delete "${folder.name}"? Conversations will not be deleted.`)) return;
        router.delete(`/settings/folders/${folder.id}`);
    }

    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-background overflow-hidden transition-shadow',
                isEditing && 'shadow-sm ring-1 ring-primary/20',
            )}
        >
            <div className="flex items-center gap-4 px-4 py-3">
                <span className="h-3 w-3 rounded-full shrink-0" style={{ backgroundColor: folder.color }} />
                <span className="text-[13.5px] font-medium text-foreground flex-1">{folder.name}</span>
                <span className="text-xs text-muted-foreground tabular-nums bg-muted/60 px-2 py-0.5 rounded-md">
                    {folder.conversations_count} {folder.conversations_count === 1 ? 'conversation' : 'conversations'}
                </span>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button className="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted/60 hover:text-foreground transition-colors">
                            <MoreHorizontalIcon className="h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-40">
                        <DropdownMenuItem onClick={() => setEditingId(isEditing ? null : folder.id)}>
                            <PencilIcon className="h-3.5 w-3.5 mr-2" />
                            {isEditing ? 'Cancel edit' : 'Edit folder'}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="text-destructive focus:text-destructive" onClick={destroy}>
                            Delete folder
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            {isEditing && <EditFolderForm folder={folder} onCancel={() => setEditingId(null)} />}
        </div>
    );
}

export default function FoldersSettings({ folders }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', color: '#6366f1', icon: 'folder' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/folders', { onSuccess: () => reset() });
    }

    const filtered = folders.filter((f) => f.name.toLowerCase().includes(search.toLowerCase()));

    return (
        <AppLayout>
            <Head title="Folders" />
            <div className="w-full px-6 py-8 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Custom Folders</h1>
                    <p className="text-sm text-muted-foreground mt-1">Organize conversations into custom buckets.</p>
                </div>

                {/* Create card */}
                <div className="rounded-xl border border-border bg-card p-5 space-y-4">
                    <p className="text-[13px] font-semibold">New folder</p>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="flex items-end gap-3">
                            <div className="flex-1 space-y-1.5">
                                <Label className="text-xs text-muted-foreground">Folder name</Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g. VIP customers"
                                        required
                                        className="h-9"
                                    />
                                    {data.name && (
                                        <span className="h-3 w-3 rounded-full shrink-0" style={{ backgroundColor: data.color }} />
                                    )}
                                </div>
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs text-muted-foreground">Color</Label>
                            <ColorPicker value={data.color} onChange={(c) => setData('color', c)} />
                        </div>
                        <Button type="submit" disabled={processing || !data.name.trim()} size="sm">
                            {processing ? 'Creating…' : 'Create folder'}
                        </Button>
                    </form>
                </div>

                {/* Folder list */}
                {folders.length > 0 && (
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="relative flex-1">
                                <SearchIcon className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground pointer-events-none" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search folders…"
                                    className="pl-9 h-9"
                                />
                            </div>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground bg-muted/60 px-3 py-2 rounded-lg border border-border shrink-0">
                                <FolderIcon className="h-3.5 w-3.5" />
                                <span>
                                    {folders.length} folder{folders.length !== 1 ? 's' : ''}
                                </span>
                            </div>
                        </div>

                        {filtered.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border py-12 text-center">
                                <p className="text-sm text-muted-foreground">No folders match "{search}"</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {filtered.map((folder) => (
                                    <FolderRow key={folder.id} folder={folder} editingId={editingId} setEditingId={setEditingId} />
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {folders.length === 0 && (
                    <div className="rounded-xl border border-dashed border-border py-16 text-center space-y-2">
                        <FolderIcon className="h-8 w-8 mx-auto text-muted-foreground/40" />
                        <p className="text-sm font-medium text-muted-foreground">No folders yet</p>
                        <p className="text-xs text-muted-foreground/70">
                            Create your first folder above to start organising conversations.
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
