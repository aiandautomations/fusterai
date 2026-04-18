import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import RichTextEditor from '@/Components/RichTextEditor';
import { cn } from '@/lib/utils';
import { LinkIcon, PenLineIcon, CheckCircleIcon, Loader2Icon } from 'lucide-react';

interface KnowledgeBase {
    id: number;
    name: string;
}

interface Document {
    id: number;
    title: string;
    content: string;
}

interface Props {
    kb: KnowledgeBase;
    document: Document | null;
}

export default function EditDocument({ kb, document }: Props) {
    const isEditing = !!document;
    const defaultTab = new URLSearchParams(window.location.search).get('tab') === 'url' ? 'url' : 'write';
    const [tab, setTab] = useState<'write' | 'url'>(defaultTab);

    const { data, setData, post, patch, processing, errors } = useForm({
        title: document?.title ?? '',
        content: document?.content ?? '',
    });

    const [url, setUrl] = useState('');
    const [urlStatus, setUrlStatus] = useState<'idle' | 'loading' | 'done' | 'error'>('idle');
    const [urlError, setUrlError] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            patch(route('ai.kb.documents.update', { knowledgeBase: kb.id, document: document!.id }));
        } else {
            post(route('ai.kb.documents.store', { knowledgeBase: kb.id }));
        }
    };

    async function importFromUrl(e: React.FormEvent) {
        e.preventDefault();
        if (!url.trim()) return;
        setUrlStatus('loading');
        setUrlError('');
        try {
            const csrfToken = (globalThis.document.querySelector('[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
            const res = await fetch(route('ai.kb.documents.import-url', { knowledgeBase: kb.id }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ url }),
            });

            if (res.ok) {
                setUrlStatus('done');
                setUrl('');
            } else {
                const body = await res.json().catch(() => ({}));
                setUrlError(body?.message ?? 'Failed to queue URL. Please check the URL and try again.');
                setUrlStatus('error');
            }
        } catch {
            setUrlError('Network error. Please try again.');
            setUrlStatus('error');
        }
    }

    return (
        <AppLayout>
            <Head title={isEditing ? `Edit: ${document!.title}` : 'New Document'} />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex items-center gap-3 mb-6">
                    <Link href={route('ai.knowledge-bases.show', kb.id)} className="text-muted-foreground hover:text-foreground text-sm">
                        ← {kb.name}
                    </Link>
                    <span className="text-muted-foreground">/</span>
                    <span className="text-sm">{isEditing ? 'Edit Document' : 'New Document'}</span>
                </div>

                <h1 className="text-3xl font-semibold tracking-tight">{isEditing ? 'Edit Document' : 'New Document'}</h1>

                {/* Tab switcher — only show on create */}
                {!isEditing && (
                    <div className="flex gap-1 p-1 bg-muted rounded-lg w-fit">
                        <button
                            type="button"
                            onClick={() => setTab('write')}
                            className={cn(
                                'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all',
                                tab === 'write' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <PenLineIcon className="h-3.5 w-3.5" />
                            Write manually
                        </button>
                        <button
                            type="button"
                            onClick={() => setTab('url')}
                            className={cn(
                                'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all',
                                tab === 'url' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <LinkIcon className="h-3.5 w-3.5" />
                            Import from URL
                        </button>
                    </div>
                )}

                {/* URL import panel */}
                {!isEditing && tab === 'url' && (
                    <Card className="bg-card/75">
                        <CardContent className="p-6">
                            <p className="text-sm text-muted-foreground mb-4">
                                Paste a URL and we'll fetch the page content, extract the text, and index it in your knowledge base
                                automatically. Great for help articles, docs pages, or FAQs.
                            </p>

                            {urlStatus === 'done' ? (
                                <div className="flex items-center gap-3 text-sm text-success bg-success/10 border border-success/20 rounded-lg px-4 py-3">
                                    <CheckCircleIcon className="h-4 w-4 shrink-0" />
                                    <div>
                                        <p className="font-medium">URL queued for indexing</p>
                                        <p className="text-xs text-muted-foreground mt-0.5">
                                            The page will be fetched and available in your knowledge base within a few seconds.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <form onSubmit={importFromUrl} className="space-y-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="url">Page URL</Label>
                                        <Input
                                            id="url"
                                            type="url"
                                            value={url}
                                            onChange={(e) => setUrl(e.target.value)}
                                            placeholder="https://help.example.com/articles/reset-password"
                                            autoFocus
                                        />
                                        {urlError && <p className="text-sm text-destructive">{urlError}</p>}
                                        <p className="text-xs text-muted-foreground">
                                            Supports any publicly accessible web page. JavaScript-rendered pages may not extract correctly.
                                        </p>
                                    </div>
                                    <div className="flex gap-3">
                                        <Button type="submit" disabled={urlStatus === 'loading' || !url.trim()}>
                                            {urlStatus === 'loading' && <Loader2Icon className="h-3.5 w-3.5 mr-1.5 animate-spin" />}
                                            {urlStatus === 'loading' ? 'Fetching…' : 'Import URL'}
                                        </Button>
                                        <Link href={route('ai.knowledge-bases.show', kb.id)}>
                                            <Button type="button" variant="ghost">
                                                Cancel
                                            </Button>
                                        </Link>
                                    </div>
                                </form>
                            )}

                            {urlStatus === 'done' && (
                                <div className="flex gap-3 mt-4">
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setUrlStatus('idle');
                                        }}
                                    >
                                        Import another URL
                                    </Button>
                                    <Link href={route('ai.knowledge-bases.show', kb.id)}>
                                        <Button variant="ghost">Back to Knowledge Base</Button>
                                    </Link>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Manual write form */}
                {(isEditing || tab === 'write') && (
                    <Card className="bg-card/75">
                        <CardContent className="p-6">
                            <form onSubmit={submit} className="space-y-5">
                                <div className="space-y-1.5">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                        placeholder="e.g. How do I reset my password?"
                                        autoFocus
                                    />
                                    {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Content</Label>
                                    <div className="overflow-hidden rounded-lg border border-border/80 bg-background">
                                        <RichTextEditor value={data.content} onChange={(html) => setData('content', html)} />
                                    </div>
                                    {errors.content && <p className="text-sm text-destructive">{errors.content}</p>}
                                </div>

                                <div className="flex gap-3 pt-2">
                                    <Button type="submit" disabled={processing}>
                                        {isEditing ? 'Save Changes' : 'Create Document'}
                                    </Button>
                                    <Link href={route('ai.knowledge-bases.show', kb.id)}>
                                        <Button type="button" variant="ghost">
                                            Cancel
                                        </Button>
                                    </Link>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
