import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useEditor, EditorContent, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Mention from '@tiptap/extension-mention';
import { cn } from '@/lib/utils';
import {
    BoldIcon,
    ItalicIcon,
    UnderlineIcon,
    ListIcon,
    ListOrderedIcon,
    LinkIcon,
    AlignLeftIcon,
    AlignCenterIcon,
    QuoteIcon,
    Undo2Icon,
    Redo2Icon,
    ZapIcon,
    SearchIcon,
} from 'lucide-react';

export interface RichTextEditorHandle {
    insertContent: (html: string) => void;
    clearContent: () => void;
}

interface Agent {
    id: number;
    name: string;
}

interface Props {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    className?: string;
    minHeight?: string;
    onEditorReady?: (editor: Editor) => void;
    onKeyDown?: (e: KeyboardEvent) => void;
    mailboxId?: number;
    agents?: Agent[];
    enableMentions?: boolean;
}

interface CannedResponse {
    id: number;
    name: string;
    content: string;
    mailbox_id: number | null;
}

// ── Mention suggestion builder ───────────────────────────────────────────────

function buildMentionSuggestion(workspaceAgents: Agent[], enabledRef: React.MutableRefObject<boolean>) {
    return {
        items: ({ query }: { query: string }) => {
            if (!enabledRef.current) return [];
            return workspaceAgents.filter((a) => a.name.toLowerCase().includes(query.toLowerCase())).slice(0, 8);
        },

        render: () => {
            let popup: HTMLDivElement | null = null;
            let items: Agent[] = [];
            let selectedIndex = 0;
            let selectFn: ((item: Agent) => void) | null = null;

            function render() {
                if (!popup) return;
                popup.innerHTML = '';
                items.forEach((item, i) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = `@${item.name}`;
                    btn.className = [
                        'w-full text-left px-3 py-2 text-sm transition-colors border-b border-border/40 last:border-0',
                        i === selectedIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/60',
                    ].join(' ');
                    btn.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        selectFn?.(item);
                    });
                    btn.addEventListener('mouseenter', () => {
                        selectedIndex = i;
                        render();
                    });
                    popup!.appendChild(btn);
                });

                if (items.length === 0) {
                    const empty = document.createElement('p');
                    empty.textContent = 'No agents found';
                    empty.className = 'px-3 py-2.5 text-xs text-muted-foreground text-center';
                    popup.appendChild(empty);
                }
            }

            return {
                onStart: (props: {
                    items: Agent[];
                    command: (item: { id: string; label: string }) => void;
                    clientRect?: (() => DOMRect | null) | null;
                }) => {
                    items = props.items;
                    selectedIndex = 0;
                    selectFn = (item: Agent) => props.command({ id: String(item.id), label: item.name });

                    popup = document.createElement('div');
                    popup.className = ['absolute z-50 w-52 rounded-lg border border-border bg-popover shadow-lg overflow-hidden'].join(' ');
                    popup.style.position = 'fixed';

                    document.body.appendChild(popup);
                    render();

                    const rect = props.clientRect?.();
                    if (rect) {
                        popup.style.top = `${rect.bottom + 4}px`;
                        popup.style.left = `${rect.left}px`;
                    }
                },

                onUpdate: (props: {
                    items: Agent[];
                    command: (item: { id: string; label: string }) => void;
                    clientRect?: (() => DOMRect | null) | null;
                }) => {
                    items = props.items;
                    selectedIndex = 0;
                    selectFn = (item: Agent) => props.command({ id: String(item.id), label: item.name });
                    render();

                    const rect = props.clientRect?.();
                    if (rect && popup) {
                        popup.style.top = `${rect.bottom + 4}px`;
                        popup.style.left = `${rect.left}px`;
                    }
                },

                onKeyDown: ({ event }: { event: KeyboardEvent }) => {
                    if (event.key === 'ArrowDown') {
                        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                        render();
                        return true;
                    }
                    if (event.key === 'ArrowUp') {
                        selectedIndex = Math.max(selectedIndex - 1, 0);
                        render();
                        return true;
                    }
                    if (event.key === 'Enter') {
                        if (items[selectedIndex]) selectFn?.(items[selectedIndex]);
                        return true;
                    }
                    return false;
                },

                onExit: () => {
                    popup?.remove();
                    popup = null;
                },
            };
        },
    };
}

// ── Toolbar button ────────────────────────────────────────────────────────────

function ToolbarButton({
    onClick,
    active,
    disabled,
    children,
    title,
}: {
    onClick: () => void;
    active?: boolean;
    disabled?: boolean;
    children: React.ReactNode;
    title?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className={cn(
                'p-1.5 rounded text-sm transition-colors',
                active ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                disabled && 'opacity-40 cursor-not-allowed',
            )}
        >
            {children}
        </button>
    );
}

// ── Canned response picker ────────────────────────────────────────────────────

function CannedResponsePicker({ editor, mailboxId }: { editor: Editor; mailboxId?: number }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<CannedResponse[]>([]);
    const [loading, setLoading] = useState(false);
    const [highlighted, setHighlighted] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const wrapperRef = useRef<HTMLDivElement>(null);

    // Close on outside click
    useEffect(() => {
        if (!open) return;
        function handler(e: MouseEvent) {
            if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    // Focus input when opening
    useEffect(() => {
        if (open) {
            setTimeout(() => inputRef.current?.focus(), 10);
            fetchResults('');
        } else {
            setQuery('');
            setResults([]);
            setHighlighted(0);
        }
    }, [open]);

    const fetchResults = useCallback(
        (q: string) => {
            if (timerRef.current) clearTimeout(timerRef.current);
            timerRef.current = setTimeout(async () => {
                setLoading(true);
                try {
                    const params = new URLSearchParams({ q });
                    if (mailboxId) params.set('mailbox_id', String(mailboxId));
                    const res = await fetch(`/canned-responses/search?${params}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    setResults(data);
                    setHighlighted(0);
                } finally {
                    setLoading(false);
                }
            }, 150);
        },
        [mailboxId],
    );

    function onQueryChange(e: React.ChangeEvent<HTMLInputElement>) {
        setQuery(e.target.value);
        fetchResults(e.target.value);
    }

    function insert(response: CannedResponse) {
        editor.chain().focus().insertContent(response.content).run();
        setOpen(false);
    }

    function onKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlighted((h) => Math.min(h + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlighted((h) => Math.max(h - 1, 0));
        } else if (e.key === 'Enter' && results[highlighted]) {
            e.preventDefault();
            insert(results[highlighted]);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    }

    return (
        <div ref={wrapperRef} className="relative">
            <ToolbarButton onClick={() => setOpen((o) => !o)} active={open} title="Insert canned response">
                <ZapIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            {open && (
                <div className="absolute left-0 top-full mt-1 z-50 w-72 rounded-lg border border-border bg-popover shadow-lg overflow-hidden">
                    {/* Search input */}
                    <div className="flex items-center gap-2 px-3 py-2 border-b border-border">
                        <SearchIcon className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        <input
                            ref={inputRef}
                            value={query}
                            onChange={onQueryChange}
                            onKeyDown={onKeyDown}
                            placeholder="Search canned responses…"
                            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                    </div>

                    {/* Results */}
                    <div className="max-h-56 overflow-y-auto">
                        {loading && <p className="px-3 py-4 text-center text-xs text-muted-foreground">Searching…</p>}

                        {!loading && results.length === 0 && (
                            <p className="px-3 py-4 text-center text-xs text-muted-foreground">
                                {query ? 'No matches found.' : 'No canned responses yet.'}
                            </p>
                        )}

                        {!loading &&
                            results.map((r, i) => (
                                <button
                                    key={r.id}
                                    type="button"
                                    onMouseDown={(e) => {
                                        e.preventDefault();
                                        insert(r);
                                    }}
                                    onMouseEnter={() => setHighlighted(i)}
                                    className={cn(
                                        'w-full text-left px-3 py-2.5 transition-colors border-b border-border/50 last:border-0',
                                        highlighted === i ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/50',
                                    )}
                                >
                                    <p className="text-sm font-medium leading-tight">{r.name}</p>
                                    <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                                        {r.content.replace(/<[^>]+>/g, ' ').trim()}
                                    </p>
                                </button>
                            ))}
                    </div>

                    <div className="px-3 py-1.5 border-t border-border bg-muted/30">
                        <p className="text-[10px] text-muted-foreground">↑↓ navigate · Enter insert · Esc close</p>
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Toolbar ───────────────────────────────────────────────────────────────────

function Toolbar({ editor, mailboxId }: { editor: Editor; mailboxId?: number }) {
    const setLink = () => {
        const url = window.prompt('URL');
        if (!url) return;
        editor.chain().focus().setLink({ href: url }).run();
    };

    return (
        <div className="flex items-center gap-0.5 px-2 py-1.5 border-b border-border flex-wrap">
            <ToolbarButton onClick={() => editor.chain().focus().toggleBold().run()} active={editor.isActive('bold')} title="Bold">
                <BoldIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton onClick={() => editor.chain().focus().toggleItalic().run()} active={editor.isActive('italic')} title="Italic">
                <ItalicIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleUnderline().run()}
                active={editor.isActive('underline')}
                title="Underline"
            >
                <UnderlineIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="w-px h-4 bg-border mx-1" />

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBulletList().run()}
                active={editor.isActive('bulletList')}
                title="Bullet list"
            >
                <ListIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
                active={editor.isActive('orderedList')}
                title="Ordered list"
            >
                <ListOrderedIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
                active={editor.isActive('blockquote')}
                title="Quote"
            >
                <QuoteIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="w-px h-4 bg-border mx-1" />

            <ToolbarButton onClick={setLink} active={editor.isActive('link')} title="Insert link">
                <LinkIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="w-px h-4 bg-border mx-1" />

            <ToolbarButton
                onClick={() => editor.chain().focus().setTextAlign('left').run()}
                active={editor.isActive({ textAlign: 'left' })}
                title="Align left"
            >
                <AlignLeftIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <ToolbarButton
                onClick={() => editor.chain().focus().setTextAlign('center').run()}
                active={editor.isActive({ textAlign: 'center' })}
                title="Align center"
            >
                <AlignCenterIcon className="h-3.5 w-3.5" />
            </ToolbarButton>

            <div className="w-px h-4 bg-border mx-1" />

            <CannedResponsePicker editor={editor} mailboxId={mailboxId} />

            <div className="ml-auto flex items-center gap-0.5">
                <ToolbarButton onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()} title="Undo">
                    <Undo2Icon className="h-3.5 w-3.5" />
                </ToolbarButton>
                <ToolbarButton onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()} title="Redo">
                    <Redo2Icon className="h-3.5 w-3.5" />
                </ToolbarButton>
            </div>
        </div>
    );
}

// ── Main component ────────────────────────────────────────────────────────────

export default function RichTextEditor({
    value,
    onChange,
    placeholder = 'Write your message…',
    className,
    minHeight = '120px',
    onEditorReady,
    onKeyDown,
    mailboxId,
    agents = [],
    enableMentions = false,
}: Props) {
    // Use a ref so the suggestion `items` fn always reads the latest value
    // without needing to recreate the editor (useEditor only runs once).
    const enableMentionsRef = useRef(enableMentions);
    enableMentionsRef.current = enableMentions;

    // Build extensions once — Mention is always included when agents are provided.
    const extensions = useRef([
        StarterKit,
        Underline,
        Link.configure({ openOnClick: false }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Placeholder.configure({ placeholder }),
        ...(agents.length > 0
            ? [
                  Mention.configure({
                      HTMLAttributes: { class: 'mention' },
                      suggestion: buildMentionSuggestion(agents, enableMentionsRef),
                  }),
              ]
            : []),
    ]).current;

    const editor = useEditor({
        extensions,
        content: value,
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
    });

    useEffect(() => {
        if (editor && onEditorReady) onEditorReady(editor);
    }, [editor]);

    useEffect(() => {
        if (!editor || !onKeyDown) return;
        const dom = editor.view.dom as HTMLElement;
        dom.addEventListener('keydown', onKeyDown);
        return () => dom.removeEventListener('keydown', onKeyDown);
    }, [editor, onKeyDown]);

    if (!editor) return null;

    return (
        <div className={cn('rounded-md border border-input overflow-hidden', className)}>
            <Toolbar editor={editor} mailboxId={mailboxId} />
            <EditorContent
                editor={editor}
                className="prose prose-sm max-w-none px-3 py-2 focus-within:outline-none"
                style={{ minHeight }}
            />
        </div>
    );
}
