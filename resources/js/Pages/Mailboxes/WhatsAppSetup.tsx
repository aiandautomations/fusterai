import React, { useState } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { ArrowLeftIcon, EyeIcon, EyeOffIcon, CopyIcon, CheckIcon, MessageSquareIcon } from 'lucide-react'

interface Props {
    mailbox: {
        id: number
        name: string
        webhook_token: string
        channel?: {
            config: {
                phone_number_id?: string
                access_token?: string
            }
        }
    }
    webhookUrl: string
}

function CopyButton({ value }: { value: string }) {
    const [copied, setCopied] = useState(false)
    function copy() {
        navigator.clipboard.writeText(value)
        setCopied(true)
        setTimeout(() => setCopied(false), 2000)
    }
    return (
        <button type="button" onClick={copy} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors">
            {copied ? <CheckIcon className="h-4 w-4 text-success" /> : <CopyIcon className="h-4 w-4" />}
        </button>
    )
}

function PasswordInput({ id, value, onChange, placeholder }: { id?: string; value: string; onChange: (v: string) => void; placeholder?: string }) {
    const [show, setShow] = useState(false)
    return (
        <div className="relative">
            <Input id={id} type={show ? 'text' : 'password'} value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder ?? '••••••••'} className="pr-10" />
            <button type="button" onClick={() => setShow(!show)} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors">
                {show ? <EyeOffIcon className="h-4 w-4" /> : <EyeIcon className="h-4 w-4" />}
            </button>
        </div>
    )
}

export default function WhatsAppSetup({ mailbox, webhookUrl }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        phone_number_id: mailbox.channel?.config?.phone_number_id ?? '',
        access_token: mailbox.channel?.config?.access_token ?? '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(`/mailboxes/${mailbox.id}/whatsapp`)
    }

    return (
        <AppLayout>
            <Head title="WhatsApp Setup" />
            <div className="w-full px-6 py-8">
                <div className="mb-8">
                    <Link href="/mailboxes" className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mb-4">
                        <ArrowLeftIcon className="h-3.5 w-3.5" />
                        All Mailboxes
                    </Link>
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-lg bg-green-500/10 flex items-center justify-center">
                            <MessageSquareIcon className="h-5 w-5 text-green-600" />
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">{mailbox.name} — WhatsApp</h1>
                            <p className="text-sm text-muted-foreground">Connect via WhatsApp Business Cloud API</p>
                        </div>
                    </div>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook Configuration</CardTitle>
                            <CardDescription>
                                Add these values in your Meta Developer Console under WhatsApp → Configuration.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Callback URL</label>
                                <div className="relative">
                                    <Input value={webhookUrl} readOnly className="pr-10 bg-muted font-mono text-xs" />
                                    <CopyButton value={webhookUrl} />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Verify Token</label>
                                <div className="relative">
                                    <Input value={mailbox.webhook_token} readOnly className="pr-10 bg-muted font-mono text-xs" />
                                    <CopyButton value={mailbox.webhook_token} />
                                </div>
                                <p className="text-xs text-muted-foreground">Use this as the verify token when configuring the webhook in Meta.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>API Credentials</CardTitle>
                            <CardDescription>
                                Found in your Meta Developer Console under WhatsApp → API Setup.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <label htmlFor="phone-number-id" className="text-sm font-medium">Phone Number ID</label>
                                    <Input
                                        id="phone-number-id"
                                        value={data.phone_number_id}
                                        onChange={e => setData('phone_number_id', e.target.value)}
                                        placeholder="1234567890123456"
                                    />
                                    {errors.phone_number_id && <p className="text-xs text-destructive">{errors.phone_number_id}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label htmlFor="access-token" className="text-sm font-medium">Permanent Access Token</label>
                                    <PasswordInput
                                        id="access-token"
                                        value={data.access_token}
                                        onChange={v => setData('access_token', v)}
                                        placeholder="EAAxxxxxxxx..."
                                    />
                                    {errors.access_token && <p className="text-xs text-destructive">{errors.access_token}</p>}
                                    <p className="text-xs text-muted-foreground">Credentials are encrypted before storage.</p>
                                </div>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save credentials'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    )
}
