import { Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function MailboxCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/mailboxes');
    }

    return (
        <AppLayout title="New Mailbox">
            <div className="w-full px-6 py-8">
                <div className="max-w-2xl space-y-5">
                    <div>
                        <h1 className="text-3xl font-semibold tracking-tight">New Mailbox</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Add a mailbox channel so agents can manage customer conversations.
                        </p>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Mailbox Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="mailbox-name">Name</Label>
                                    <Input
                                        id="mailbox-name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Support"
                                        required
                                    />
                                    {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="mailbox-email">Email address</Label>
                                    <Input
                                        id="mailbox-email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="support@yourcompany.com"
                                        required
                                    />
                                    {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                                </div>

                                <div className="flex items-center gap-3 pt-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Creating…' : 'Create Mailbox'}
                                    </Button>
                                    <Button asChild variant="ghost">
                                        <Link href="/mailboxes">Cancel</Link>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
