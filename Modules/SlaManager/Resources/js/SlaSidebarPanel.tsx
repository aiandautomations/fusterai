import { usePage } from '@inertiajs/react'

interface SlaData {
    policy: {
        name: string
        first_response_label: string
        resolution_label: string
    } | null
    first_response_status: 'achieved' | 'breached' | 'paused' | 'pending'
    resolution_status: 'achieved' | 'breached' | 'paused' | 'pending'
    first_response_due_at: string | null
    resolution_due_at: string | null
    first_response_remaining_minutes: number
    resolution_remaining_minutes: number
    is_paused: boolean
}

type SlaStatus = SlaData['first_response_status']

const STATUS_CONFIG: Record<SlaStatus, { label: string; className: string }> = {
    achieved: { label: 'Met',      className: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    breached: { label: 'Breached', className: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    paused:   { label: 'Paused',   className: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' },
    pending:  { label: 'Pending',  className: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-500' },
}

function StatusBadge({ status }: { status: SlaStatus }) {
    const { label, className } = STATUS_CONFIG[status]
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${className}`}>
            {label}
        </span>
    )
}

function formatRemaining(minutes: number): string {
    if (minutes <= 0) return 'Overdue'
    if (minutes < 60) return `${minutes}m left`
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return m > 0 ? `${h}h ${m}m left` : `${h}h left`
}

export default function SlaSidebarPanel() {
    // sla is injected as a top-level Inertia page prop by SlaManagerServiceProvider
    const sla = (usePage().props as any).sla as SlaData | undefined

    if (!sla?.policy) return null

    return (
        <div className="border-t border-border px-4 py-3">
            <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                SLA — {sla.policy.name}
            </p>

            {sla.is_paused && (
                <p className="mb-2 text-xs text-muted-foreground italic">
                    Clock paused — waiting for customer response.
                </p>
            )}

            <div className="space-y-2">
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">First response</span>
                    <div className="flex items-center gap-2">
                        {sla.first_response_status === 'pending' && (
                            <span className="text-xs text-muted-foreground">
                                {formatRemaining(sla.first_response_remaining_minutes)}
                            </span>
                        )}
                        <StatusBadge status={sla.first_response_status} />
                    </div>
                </div>

                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">Resolution</span>
                    <div className="flex items-center gap-2">
                        {sla.resolution_status === 'pending' && (
                            <span className="text-xs text-muted-foreground">
                                {formatRemaining(sla.resolution_remaining_minutes)}
                            </span>
                        )}
                        <StatusBadge status={sla.resolution_status} />
                    </div>
                </div>
            </div>

            <p className="mt-2 text-xs text-muted-foreground">
                Targets: {sla.policy.first_response_label} response · {sla.policy.resolution_label} resolution
            </p>
        </div>
    )
}
