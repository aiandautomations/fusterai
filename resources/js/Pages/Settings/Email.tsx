import AppLayout from '@/Layouts/AppLayout';

interface Mailbox {
    id: number;
    name: string;
    email: string;
}
interface Guidance {
    spf: string;
    dkim: string;
    dmarc: string;
}

interface Props {
    mailboxes: Mailbox[];
    guidance: Guidance;
}

export default function SettingsEmail({ mailboxes, guidance }: Props) {
    return (
        <AppLayout title="Email Deliverability">
            <div className="w-full px-6 py-8 space-y-8">
                <div>
                    <h1 className="text-xl font-semibold">Email Deliverability</h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        Configure DNS records to improve email deliverability and prevent messages from landing in spam.
                    </p>
                </div>

                {/* Mailboxes */}
                <div className="space-y-2">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Your Mailboxes</h2>
                    {mailboxes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No mailboxes configured yet.{' '}
                            <a href="/mailboxes/create" className="text-primary underline">
                                Create one
                            </a>
                            .
                        </p>
                    ) : (
                        <div className="space-y-2">
                            {mailboxes.map((m) => (
                                <div key={m.id} className="flex items-center gap-3 border rounded-md px-4 py-3">
                                    <div>
                                        <p className="text-sm font-medium">{m.name}</p>
                                        <p className="text-xs text-muted-foreground">{m.email}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* DNS Records */}
                <div className="space-y-4">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Recommended DNS Records</h2>

                    <div className="space-y-4">
                        <div className="border rounded-md p-4 space-y-2">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-mono bg-info/10 text-info px-2 py-0.5 rounded">SPF</span>
                                <span className="text-sm font-medium">Sender Policy Framework</span>
                            </div>
                            <p className="text-xs text-muted-foreground">Add a TXT record to your domain's DNS:</p>
                            <code className="block text-xs bg-muted px-3 py-2 rounded break-all">{guidance.spf}</code>
                        </div>

                        <div className="border rounded-md p-4 space-y-2">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-mono bg-success/15 text-success px-2 py-0.5 rounded">DKIM</span>
                                <span className="text-sm font-medium">DomainKeys Identified Mail</span>
                            </div>
                            <p className="text-xs text-muted-foreground">{guidance.dkim}</p>
                        </div>

                        <div className="border rounded-md p-4 space-y-2">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-mono bg-primary/10 text-primary px-2 py-0.5 rounded">DMARC</span>
                                <span className="text-sm font-medium">Domain-based Message Authentication</span>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Add a TXT record at <code>_dmarc.yourdomain.com</code>:
                            </p>
                            <code className="block text-xs bg-muted px-3 py-2 rounded break-all">{guidance.dmarc}</code>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
