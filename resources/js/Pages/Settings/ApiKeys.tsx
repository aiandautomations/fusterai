import React, { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { type PageProps } from '@/types'
import { CopyIcon, CheckIcon, TrashIcon, KeyIcon } from 'lucide-react'

interface Token {
    id: string
    name: string
    created_at: string
}

interface Props { tokens: Token[] }

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

export default function ApiKeys({ tokens }: Props) {
    const { flash } = usePage<PageProps>().props
    const [copied, setCopied] = useState(false)

    const form = useForm({ name: '' })

    function copy() {
        if (!flash?.token) return
        navigator.clipboard.writeText(flash.token)
        setCopied(true)
        setTimeout(() => setCopied(false), 2000)
    }

    function revoke(id: string) {
        if (!confirm('Revoke this token? Any integrations using it will lose access immediately.')) return
        router.delete(`/settings/api-keys/${id}`)
    }

    return (
        <AppLayout title="API Keys">
            <div className="w-full max-w-2xl px-6 py-8 mx-auto space-y-10">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">API Keys</h1>
                    <p className="text-sm text-muted-foreground mt-1">Personal access tokens for the REST API. Treat them like passwords — they grant full access to your account.</p>
                </div>

                {/* One-time token reveal */}
                {flash?.token && (
                    <div className="rounded-xl border border-success/40 bg-success/5 p-5 space-y-3">
                        <p className="text-sm font-semibold text-success">Token created — copy it now. It won't be shown again.</p>
                        <div className="flex items-center gap-2">
                            <code className="flex-1 rounded-lg bg-muted px-3 py-2 text-xs font-mono break-all select-all">{flash.token}</code>
                            <Button size="sm" variant="outline" onClick={copy} className="shrink-0 w-9 h-9 p-0">
                                {copied ? <CheckIcon className="h-4 w-4 text-success" /> : <CopyIcon className="h-4 w-4" />}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Create */}
                <section className="space-y-4">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Create New Token</h2>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault()
                            form.post('/settings/api-keys', { onSuccess: () => form.reset() })
                        }}
                        className="flex gap-3"
                    >
                        <div className="flex-1">
                            <Label htmlFor="token-name" className="sr-only">Token name</Label>
                            <Input
                                id="token-name"
                                placeholder="e.g. My Integration, Zapier, CI"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            {form.errors.name && <p className="text-xs text-destructive mt-1">{form.errors.name}</p>}
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            <KeyIcon className="h-4 w-4 mr-1.5" />Generate
                        </Button>
                    </form>
                </section>

                {/* Token list */}
                <section className="space-y-3">
                    <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-[0.12em]">Active Tokens</h2>
                    <div className="rounded-xl border border-border/80 bg-card/75 overflow-hidden">
                        {tokens.length === 0 ? (
                            <p className="text-sm text-muted-foreground px-5 py-8 text-center">No active tokens. Create one above.</p>
                        ) : (
                            <ul className="divide-y">
                                {tokens.map((token) => (
                                    <li key={token.id} className="flex items-center gap-4 px-5 py-3">
                                        <KeyIcon className="h-4 w-4 text-muted-foreground shrink-0" />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium truncate">{token.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Created {formatDate(token.created_at)}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive hover:text-destructive hover:bg-destructive/10 shrink-0"
                                            onClick={() => revoke(token.id)}
                                        >
                                            <TrashIcon className="h-4 w-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    )
}
