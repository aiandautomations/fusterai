import { Link, router } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { MailboxIcon, PlusIcon, SettingsIcon, InboxIcon, ArrowRightIcon, Clock3Icon, CircleDotIcon, MessageCircleIcon, GlobeIcon } from 'lucide-react'

interface Mailbox {
    id: number
    name: string
    email: string
    active: boolean
    channel_type: string | null
    conversations_count: number
    open_count: number
    pending_count: number
}

const channelBadge: Record<string, { label: string; className: string }> = {
    email:    { label: 'Email',    className: 'bg-blue-500/10 text-blue-600 border-blue-200' },
    whatsapp: { label: 'WhatsApp', className: 'bg-[#25D366]/10 text-[#25D366] border-[#25D366]/20' },
    chat:     { label: 'Live Chat',className: 'bg-violet-500/10 text-violet-600 border-violet-200' },
    api:      { label: 'API',      className: 'bg-orange-500/10 text-orange-600 border-orange-200' },
}

interface Props {
    mailboxes: Mailbox[]
}

const palette = ['#6366f1', '#14b8a6', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4']

export default function MailboxesIndex({ mailboxes }: Props) {
    const totalOpen = mailboxes.reduce((sum, mailbox) => sum + (mailbox.open_count ?? 0), 0)
    const totalPending = mailboxes.reduce((sum, mailbox) => sum + (mailbox.pending_count ?? 0), 0)
    const activeCount = mailboxes.filter((mailbox) => mailbox.active).length

    return (
        <AppLayout title="Mailboxes">
            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight">Mailboxes</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Manage inbox channels and open each mailbox workspace.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/mailboxes/create">
                            <PlusIcon className="h-4 w-4 mr-2" />
                            New Mailbox
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <Card className="bg-card/75">
                        <CardContent className="p-4">
                            <p className="text-xs uppercase tracking-[0.12em] text-muted-foreground">Active Mailboxes</p>
                            <p className="mt-2 text-2xl font-semibold">{activeCount}</p>
                            <p className="text-sm text-muted-foreground">{mailboxes.length} total</p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/75">
                        <CardContent className="p-4">
                            <p className="text-xs uppercase tracking-[0.12em] text-muted-foreground">Open Conversations</p>
                            <p className="mt-2 text-2xl font-semibold">{totalOpen}</p>
                            <p className="text-sm text-muted-foreground">Across all mailboxes</p>
                        </CardContent>
                    </Card>
                    <Card className="bg-card/75">
                        <CardContent className="p-4">
                            <p className="text-xs uppercase tracking-[0.12em] text-muted-foreground">Pending Conversations</p>
                            <p className="mt-2 text-2xl font-semibold">{totalPending}</p>
                            <p className="text-sm text-muted-foreground">Awaiting action</p>
                        </CardContent>
                    </Card>
                </div>

                {mailboxes.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <div className="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                                <MailboxIcon className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <h3 className="font-semibold text-lg mb-1">No mailboxes yet</h3>
                            <p className="text-sm text-muted-foreground mb-6 max-w-sm">
                                Add your first mailbox to start receiving and managing conversations.
                            </p>
                            <Button asChild>
                                <Link href="/mailboxes/create">
                                    <PlusIcon className="h-4 w-4 mr-2" />
                                    Add your first mailbox
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {mailboxes.map((mailbox, idx) => (
                            <Card
                                key={mailbox.id}
                                className="group relative cursor-pointer overflow-hidden border-border/80 bg-card/70 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                                onClick={() => router.get(`/conversations?mailbox=${mailbox.id}&status=open`)}
                            >
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className="flex h-10 w-10 items-center justify-center rounded-xl"
                                                style={{ backgroundColor: `${palette[idx % palette.length]}1f` }}
                                            >
                                                <MailboxIcon className="h-5 w-5" style={{ color: palette[idx % palette.length] }} />
                                            </div>
                                            <div>
                                                <h3 className="text-xl font-semibold leading-tight">{mailbox.name}</h3>
                                                <p className="text-sm text-muted-foreground truncate">{mailbox.email}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {mailbox.channel_type && channelBadge[mailbox.channel_type] && (
                                                <Badge variant="outline" className={`text-xs ${channelBadge[mailbox.channel_type].className}`}>
                                                    {channelBadge[mailbox.channel_type].label}
                                                </Badge>
                                            )}
                                            {!mailbox.active && (
                                                <Badge variant="secondary" className="text-xs">
                                                    Inactive
                                                </Badge>
                                            )}
                                            <button
                                                onClick={e => { e.stopPropagation(); router.get(`/mailboxes/${mailbox.id}/edit`) }}
                                                className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                title="Settings"
                                            >
                                                <SettingsIcon className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="rounded-lg bg-muted/55 p-3">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <InboxIcon className="h-3.5 w-3.5" />
                                                <span className="text-xs uppercase tracking-[0.1em]">Open</span>
                                            </div>
                                            <p className="mt-2 text-xl font-semibold">{mailbox.open_count ?? 0}</p>
                                        </div>
                                        <div className="rounded-lg bg-muted/55 p-3">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Clock3Icon className="h-3.5 w-3.5" />
                                                <span className="text-xs uppercase tracking-[0.1em]">Pending</span>
                                            </div>
                                            <p className="mt-2 text-xl font-semibold">{mailbox.pending_count ?? 0}</p>
                                        </div>
                                    </div>

                                    <div className="mt-4 flex items-center justify-between border-t border-border/80 pt-3">
                                        <div className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                            <CircleDotIcon className="h-3.5 w-3.5" />
                                            <span>{mailbox.conversations_count} total</span>
                                        </div>
                                        <span className="inline-flex items-center gap-1 text-sm font-medium text-foreground">
                                            Open inbox
                                            <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
