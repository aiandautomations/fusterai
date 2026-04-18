import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface AcceptInviteProps {
    token: string;
    email: string;
}

export default function AcceptInvite({ token, email }: AcceptInviteProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        name: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/invite/accept');
    }

    return (
        <AuthLayout title="You're invited" subtitle="Complete your profile to join the workspace">
            <Head title="Accept Invite" />

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-1.5">
                    <Label className="text-sm font-medium">Email address</Label>
                    <Input value={data.email} disabled className="bg-muted/50 text-muted-foreground cursor-not-allowed" />
                </div>

                <div className="space-y-1.5">
                    <Label className="text-sm font-medium">Your name</Label>
                    <Input
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Jane Doe"
                        autoComplete="name"
                        autoFocus
                        className={errors.name ? 'border-destructive' : ''}
                    />
                    {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1.5">
                        <Label className="text-sm font-medium">Password</Label>
                        <Input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Min. 8 chars"
                            autoComplete="new-password"
                            className={errors.password ? 'border-destructive' : ''}
                        />
                        {errors.password && <p className="text-xs text-destructive">{errors.password}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-sm font-medium">Confirm</Label>
                        <Input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            placeholder="••••••••"
                            autoComplete="new-password"
                        />
                    </div>
                </div>

                <div className="pt-1">
                    <Button type="submit" className="w-full font-medium" disabled={processing}>
                        {processing ? 'Setting up account…' : 'Complete setup'}
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
