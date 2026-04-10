import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import type { PageProps } from '@/types';

type Provider = 'anthropic' | 'openai' | 'openai-compatible' | 'openrouter';

const OPENROUTER_MODELS = [
    { value: 'anthropic/claude-sonnet-4-5',      label: 'Claude Sonnet 4.5 (via OpenRouter)' },
    { value: 'anthropic/claude-3.5-sonnet',      label: 'Claude 3.5 Sonnet (via OpenRouter)' },
    { value: 'openai/gpt-4o',                    label: 'GPT-4o (via OpenRouter)' },
    { value: 'google/gemini-2.0-flash-001',      label: 'Gemini 2.0 Flash (via OpenRouter)' },
    { value: 'meta-llama/llama-3.3-70b-instruct',label: 'Llama 3.3 70B (via OpenRouter)' },
    { value: 'mistralai/mistral-small-3.1-24b-instruct', label: 'Mistral Small 3.1 (via OpenRouter)' },
];

const ANTHROPIC_MODELS = [
    { value: 'claude-opus-4-6',            label: 'Claude Opus 4.6 (most capable)' },
    { value: 'claude-sonnet-4-6',          label: 'Claude Sonnet 4.6' },
    { value: 'claude-haiku-4-5-20251001',  label: 'Claude Haiku 4.5 (fastest)' },
    { value: 'claude-opus-4-5',            label: 'Claude Opus 4.5' },
    { value: 'claude-sonnet-4-5',          label: 'Claude Sonnet 4.5' },
    { value: 'claude-3-5-sonnet-20241022', label: 'Claude 3.5 Sonnet' },
    { value: 'claude-3-haiku-20240307',    label: 'Claude 3 Haiku' },
];

const OPENAI_MODELS = [
    { value: 'gpt-4o',       label: 'GPT-4o' },
    { value: 'gpt-4o-mini',  label: 'GPT-4o Mini' },
    { value: 'gpt-4-turbo',  label: 'GPT-4 Turbo' },
    { value: 'o3-mini',      label: 'o3-mini' },
    { value: 'o1',           label: 'o1' },
];

const PRESETS = [
    { label: 'OpenRouter',    url: 'https://openrouter.ai/api/v1' },
    { label: 'Groq',          url: 'https://api.groq.com/openai/v1' },
    { label: 'Together',      url: 'https://api.together.xyz/v1' },
    { label: 'Ollama (local)', url: 'http://localhost:11434/v1' },
    { label: 'Mistral',       url: 'https://api.mistral.ai/v1' },
    { label: 'Perplexity',    url: 'https://api.perplexity.ai' },
];

const PROVIDER_LABELS: Record<Provider, string> = {
    'anthropic':         'Anthropic',
    'openai':            'OpenAI',
    'openai-compatible': 'OpenAI-compatible',
    'openrouter':        'OpenRouter',
};

interface AiConfig {
    provider: Provider;
    model: string | null;
    base_url: string | null;
    key_set: boolean;
    features: {
        reply_suggestions:   boolean;
        auto_categorization: boolean;
        summarization:       boolean;
    };
    rag: { top_k: number; min_score: number };
}

interface Props extends PageProps {
    aiConfig: AiConfig;
}

// ── Small select primitive (avoids dependency on shadcn Select for this form) ──
function NativeSelect({ id, value, onChange, children, className = '' }: {
    id?: string;
    value: string;
    onChange: (v: string) => void;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <select
            id={id}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className={`flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring ${className}`}
        >
            {children}
        </select>
    );
}

function Field({ label, hint, children, error }: {
    label: string;
    hint?: string;
    children: React.ReactNode;
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <label className="text-sm font-medium">{label}</label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

export default function AIConfig({ aiConfig }: Props) {
    const [testStatus, setTestStatus] = useState<'idle' | 'loading' | 'ok' | 'fail'>('idle');
    const [testMessage, setTestMessage] = useState('');

    async function testConnection() {
        setTestStatus('loading');
        setTestMessage('');
        try {
            const res = await fetch(route('settings.ai.test'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                    'Accept': 'application/json',
                },
            });
            const body = await res.json();
            setTestStatus(body.ok ? 'ok' : 'fail');
            setTestMessage(body.message ?? '');
        } catch {
            setTestStatus('fail');
            setTestMessage('Network error. Could not reach the server.');
        }
    }

    const { data, setData, patch, processing, errors } = useForm({
        provider:                    aiConfig.provider   ?? 'anthropic' as Provider,
        api_key:                     '',
        model:                       aiConfig.model      ?? '',
        base_url:                    aiConfig.base_url   ?? '',
        feature_reply_suggestions:   aiConfig.features?.reply_suggestions   ?? true,
        feature_auto_categorization: aiConfig.features?.auto_categorization ?? true,
        feature_summarization:       aiConfig.features?.summarization        ?? true,
        rag_top_k:                   aiConfig.rag?.top_k     ?? 5,
        rag_min_score:               aiConfig.rag?.min_score ?? 0.7,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        patch('/settings/ai');
    }

    const isCompatible  = data.provider === 'openai-compatible';
    const isOpenRouter  = data.provider === 'openrouter';
    const models        = data.provider === 'anthropic'  ? ANTHROPIC_MODELS
                        : data.provider === 'openrouter' ? OPENROUTER_MODELS
                        : OPENAI_MODELS;

    return (
        <AppLayout>
            <Head title="AI Configuration" />

            <div className="w-full px-6 py-8 space-y-8">
                <div>
                    <h1 className="text-xl font-semibold">AI Configuration</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Choose any AI provider. Settings are stored securely in the database — no .env changes needed.
                    </p>
                </div>

                {/* Status banner */}
                <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/40 px-4 py-3">
                    <div className="flex-1 text-sm">
                        <span className="font-medium">{PROVIDER_LABELS[data.provider]}</span>
                        {data.model && (
                            <span className="text-muted-foreground"> · {data.model}</span>
                        )}
                    </div>
                    {aiConfig.key_set ? (
                        <Badge variant="success">API key saved</Badge>
                    ) : (
                        <Badge variant="destructive">No API key</Badge>
                    )}
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">

                    {/* ── Provider ─────────────────────────────────────────── */}
                    <section className="bg-white border border-border rounded-lg p-6 space-y-5">
                        <h2 className="font-medium text-base">Provider</h2>

                        <Field label="AI Provider">
                            <NativeSelect
                                value={data.provider}
                                onChange={(v) => {
                                    setData('provider', v as Provider);
                                    setData('model', '');
                                    setData('base_url', '');
                                }}
                            >
                                <option value="anthropic">Anthropic — Claude models</option>
                                <option value="openai">OpenAI — GPT models</option>
                                <option value="openrouter">OpenRouter — 200+ models (Claude, GPT, Gemini, Llama…)</option>
                                <option value="openai-compatible">OpenAI-compatible — Groq, Together, Ollama…</option>
                            </NativeSelect>
                        </Field>

                        {/* Base URL — only for openai-compatible (OpenRouter has a fixed URL) */}
                        {isCompatible && (
                            <Field
                                label="Base URL"
                                hint="Any provider that speaks the OpenAI API format works here."
                                error={errors.base_url}
                            >
                                <Input
                                    type="url"
                                    placeholder="https://openrouter.ai/api/v1"
                                    value={data.base_url}
                                    onChange={(e) => setData('base_url', e.target.value)}
                                    autoComplete="off"
                                />
                                {/* Quick-fill presets */}
                                <div className="flex flex-wrap gap-1.5 mt-2">
                                    {PRESETS.map((p) => (
                                        <button
                                            key={p.url}
                                            type="button"
                                            onClick={() => setData('base_url', p.url)}
                                            className="inline-flex items-center rounded-md border border-border bg-muted/60 px-2 py-0.5 text-xs font-medium hover:bg-muted transition-colors"
                                        >
                                            {p.label}
                                        </button>
                                    ))}
                                </div>
                            </Field>
                        )}

                        {/* API Key */}
                        <Field
                            label="API Key"
                            hint={aiConfig.key_set ? 'A key is already saved. Enter a new one to replace it, or leave blank to keep the existing key.' : 'Enter your API key. It will be encrypted before storage.'}
                            error={errors.api_key}
                        >
                            <div className="relative">
                                <Input
                                    type="password"
                                    placeholder={aiConfig.key_set ? '●●●●●●●● (saved)' : 'sk-…'}
                                    value={data.api_key}
                                    onChange={(e) => setData('api_key', e.target.value)}
                                    autoComplete="off"
                                />
                            </div>
                        </Field>

                        {/* Model */}
                        <Field
                            label="Model"
                            hint={isCompatible ? 'Enter the exact model identifier from your provider.' : undefined}
                            error={errors.model}
                        >
                            {isCompatible ? (
                                <div className="space-y-1.5">
                                    <Input
                                        placeholder="e.g. meta-llama/llama-3.1-70b-instruct"
                                        value={data.model}
                                        onChange={(e) => setData('model', e.target.value)}
                                        autoComplete="off"
                                    />
                                    <a
                                        href="https://openrouter.ai/models"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-xs text-primary hover:underline"
                                    >
                                        Browse models →
                                    </a>
                                </div>
                            ) : isOpenRouter ? (
                                <div className="space-y-1.5">
                                    <NativeSelect
                                        value={data.model}
                                        onChange={(v) => setData('model', v)}
                                    >
                                        <option value="">Select a model</option>
                                        {OPENROUTER_MODELS.map((m) => (
                                            <option key={m.value} value={m.value}>{m.label}</option>
                                        ))}
                                    </NativeSelect>
                                    <Input
                                        placeholder="Or type a custom model slug e.g. cohere/command-r-plus"
                                        value={data.model}
                                        onChange={(e) => setData('model', e.target.value)}
                                        autoComplete="off"
                                    />
                                    <a
                                        href="https://openrouter.ai/models"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-xs text-primary hover:underline"
                                    >
                                        Browse all 200+ OpenRouter models →
                                    </a>
                                </div>
                            ) : (
                                <NativeSelect
                                    value={data.model}
                                    onChange={(v) => setData('model', v)}
                                >
                                    <option value="">Select a model…</option>
                                    {models.map((m) => (
                                        <option key={m.value} value={m.value}>{m.label}</option>
                                    ))}
                                </NativeSelect>
                            )}
                        </Field>
                    </section>

                    {/* ── Feature Toggles ───────────────────────────────────── */}
                    <section className="bg-white border border-border rounded-lg p-6 space-y-4">
                        <h2 className="font-medium text-base">Feature Toggles</h2>

                        {([
                            { key: 'feature_reply_suggestions'   as const, label: 'Auto-suggest replies',     desc: 'Generate AI reply drafts when a new customer message arrives.' },
                            { key: 'feature_auto_categorization' as const, label: 'Auto-categorize',          desc: 'Automatically set priority and tags when a conversation is created.' },
                            { key: 'feature_summarization'       as const, label: 'Auto-summarize on close',  desc: 'Generate a brief summary when a conversation is closed.' },
                        ]).map(({ key, label, desc }) => (
                            <label key={key} className="flex items-start gap-3 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    checked={data[key]}
                                    onChange={(e) => setData(key, e.target.checked)}
                                    className="mt-0.5 h-4 w-4 rounded border-input accent-primary"
                                />
                                <div>
                                    <p className="text-sm font-medium">{label}</p>
                                    <p className="text-xs text-muted-foreground">{desc}</p>
                                </div>
                            </label>
                        ))}
                    </section>

                    {/* ── RAG ──────────────────────────────────────────────── */}
                    <section className="bg-white border border-border rounded-lg p-6 space-y-4">
                        <div>
                            <h2 className="font-medium text-base">RAG Settings</h2>
                            <p className="text-xs text-muted-foreground mt-0.5">Knowledge base retrieval configuration.</p>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Field label="Top K results" hint="1 – 10" error={errors.rag_top_k}>
                                <Input
                                    type="number"
                                    min={1} max={10} step={1}
                                    value={data.rag_top_k}
                                    onChange={(e) => setData('rag_top_k', parseInt(e.target.value, 10))}
                                />
                            </Field>
                            <Field label="Min similarity score" hint="0.0 – 1.0" error={errors.rag_min_score}>
                                <Input
                                    type="number"
                                    min={0} max={1} step={0.05}
                                    value={data.rag_min_score}
                                    onChange={(e) => setData('rag_min_score', parseFloat(e.target.value))}
                                />
                            </Field>
                        </div>
                    </section>

                    <div className="flex items-center justify-between gap-4 flex-wrap">
                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={testConnection}
                                disabled={testStatus === 'loading' || !aiConfig.key_set}
                                title={!aiConfig.key_set ? 'Save an API key first' : undefined}
                            >
                                {testStatus === 'loading' ? 'Testing…' : 'Test connection'}
                            </Button>
                            {testStatus === 'ok' && (
                                <span className="text-sm text-success font-medium">{testMessage}</span>
                            )}
                            {testStatus === 'fail' && (
                                <span className="text-sm text-destructive">{testMessage}</span>
                            )}
                        </div>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save AI settings'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
