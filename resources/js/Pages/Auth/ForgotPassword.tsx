import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Alert, AlertDescription } from '@/Components/ui/alert';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <AuthLayout
            title="Forgot your password?"
            subtitle="Enter your email and we'll send you a reset link."
        >
            <Head title="Forgot Password" />

            {status && (
                <Alert className="mb-4 border-success/30 bg-success/10 text-success">
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-1.5">
                    <Label className="text-sm font-medium">Email address</Label>
                    <Input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@company.com"
                        autoComplete="email"
                        autoFocus
                    />
                    {errors.email && (
                        <p className="text-xs text-destructive">{errors.email}</p>
                    )}
                </div>

                <div className="pt-1">
                    <Button type="submit" className="w-full font-medium" disabled={processing}>
                        {processing ? 'Sending…' : 'Send reset link'}
                    </Button>
                </div>

                <p className="text-center text-xs text-muted-foreground">
                    <a href="/login" className="text-primary font-medium hover:underline">
                        ← Back to sign in
                    </a>
                </p>
            </form>
        </AuthLayout>
    );
}
