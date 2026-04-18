import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/reset-password');
    }

    return (
        <AuthLayout title="Set a new password" subtitle="Choose a strong password for your account.">
            <Head title="Reset Password" />

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label className="mb-1.5">Email address</Label>
                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="email" />
                    {errors.email && <p className="mt-1 text-xs text-destructive">{errors.email}</p>}
                </div>

                <div>
                    <Label className="mb-1.5">New password</Label>
                    <Input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                        autoComplete="new-password"
                        autoFocus
                    />
                    {errors.password && <p className="mt-1 text-xs text-destructive">{errors.password}</p>}
                </div>

                <div>
                    <Label className="mb-1.5">Confirm password</Label>
                    <Input
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        placeholder="••••••••"
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && <p className="mt-1 text-xs text-destructive">{errors.password_confirmation}</p>}
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? 'Saving…' : 'Reset password'}
                </Button>
            </form>
        </AuthLayout>
    );
}
