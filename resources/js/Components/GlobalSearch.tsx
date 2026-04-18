import React, { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';
import { SearchIcon, InboxIcon } from 'lucide-react';

interface SearchResult {
    id: number;
    subject: string;
    status: string;
    customer?: string;
    mailbox?: string;
    url: string;
}

const statusColors: Record<string, string> = {
    open:    'text-info bg-info/10',
    pending: 'text-warning bg-warning/15',
    closed:  'text-muted-foreground bg-muted',
    spam:    'text-destructive bg-destructive/10',
};

export default function GlobalSearch() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState(-1);
    const [dropdownStyle, setDropdownStyle] = useState<React.CSSProperties>({});
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // Recalculate dropdown position whenever it opens
    useEffect(() => {
        if (open && inputRef.current) {
            const rect = inputRef.current.getBoundingClientRect();
            setDropdownStyle({
                position: 'fixed',
                top: rect.bottom + 4,
                left: rect.left,
                width: rect.width,
                zIndex: 9999,
            });
        }
    }, [open]);

    useEffect(() => {
        if (query.length < 2) {
            setResults([]);
            setOpen(false);
            return;
        }

        if (debounce.current) {
            clearTimeout(debounce.current);
        }
        setLoading(true);

        debounce.current = setTimeout(async () => {
            try {
                const res = await fetch(`/search?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                setResults(data.results ?? []);
                setOpen(true);
            } finally {
                setLoading(false);
            }
        }, 300);
    }, [query]);

    // Close on outside click
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (!containerRef.current?.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    // Keyboard navigation
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (!open) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelected((s) => Math.min(s + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelected((s) => Math.max(s - 1, 0));
        } else if (e.key === 'Enter' && selected >= 0) {
            e.preventDefault();
            router.visit(results[selected].url);
            setOpen(false);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    const dropdown = open ? (
        <div
            style={dropdownStyle}
            className="bg-popover border border-border rounded-lg shadow-xl overflow-hidden max-h-80 overflow-y-auto"
        >
            {loading ? (
                <div className="px-3 py-4 text-xs text-muted-foreground text-center">Searching…</div>
            ) : results.length === 0 ? (
                <div className="px-3 py-4 text-xs text-muted-foreground text-center">No results found</div>
            ) : (
                results.map((result, i) => (
                    <button
                        key={result.id}
                        onClick={() => { router.visit(result.url); setOpen(false); }}
                        onMouseEnter={() => setSelected(i)}
                        className={cn(
                            'w-full text-left px-3 py-2.5 flex items-start gap-2.5 transition-colors',
                            selected === i ? 'bg-muted' : 'hover:bg-muted/50',
                        )}
                    >
                        <InboxIcon className="h-3.5 w-3.5 text-muted-foreground mt-0.5 shrink-0" />
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-medium truncate">{result.subject}</p>
                            <p className="text-xs text-muted-foreground truncate">
                                {result.customer} · {result.mailbox}
                            </p>
                        </div>
                        <span className={cn('text-xs px-1.5 py-0.5 rounded font-medium shrink-0', statusColors[result.status])}>
                            {result.status}
                        </span>
                    </button>
                ))
            )}
        </div>
    ) : null;

    return (
        <div ref={containerRef} className="relative w-full">
            <SearchIcon className="absolute left-2.5 top-2 h-4 w-4 text-sidebar-foreground/40 pointer-events-none" />
            <Input
                ref={inputRef}
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={handleKeyDown}
                onFocus={() => results.length > 0 && setOpen(true)}
                placeholder="Search conversations…"
                className="h-8 pl-8 bg-sidebar border-sidebar-muted text-sidebar-foreground placeholder:text-sidebar-foreground/45"
            />
            {typeof document !== 'undefined' && createPortal(dropdown, document.body)}
        </div>
    );
}
