import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Alert, AlertDescription } from '@/Components/ui/alert';

interface LoginProps {
    status?: string;
    canResetPassword?: boolean;
    canRegister?: boolean;
}

export default function Login({ status, canResetPassword, canRegister }: LoginProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/login');
    }

    return (
        <AuthLayout title="Welcome back" subtitle="Sign in to your workspace">
            <Head title="Sign in" />

            {status && (
                <Alert className="mb-5 border-success/30 bg-success/8 text-success">
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
                        className={errors.email ? 'border-destructive' : ''}
                    />
                    {errors.email && (
                        <p className="text-xs text-destructive">{errors.email}</p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                        <Label className="text-sm font-medium">Password</Label>
                        {canResetPassword && (
                            <a href="/forgot-password" className="text-xs text-primary hover:underline">
                                Forgot password?
                            </a>
                        )}
                    </div>
                    <Input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                        autoComplete="current-password"
                        className={errors.password ? 'border-destructive' : ''}
                    />
                    {errors.password && (
                        <p className="text-xs text-destructive">{errors.password}</p>
                    )}
                </div>

                <div className="flex items-center gap-2 pt-0.5">
                    <Checkbox
                        id="remember"
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', !!checked)}
                    />
                    <Label htmlFor="remember" className="text-sm font-normal text-muted-foreground cursor-pointer">
                        Keep me signed in for 30 days
                    </Label>
                </div>

                <div className="pt-1">
                    <Button type="submit" className="w-full font-medium" disabled={processing}>
                        {processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </div>

                {canRegister && (
                    <p className="text-center text-xs text-muted-foreground pt-1">
                        No workspace yet?{' '}
                        <a href="/register" className="text-primary font-medium hover:underline">
                            Create one free →
                        </a>
                    </p>
                )}
            </form>
        </AuthLayout>
    );
}
