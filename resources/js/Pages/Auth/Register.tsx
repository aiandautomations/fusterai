import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        workspace_name: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/register');
    }

    return (
        <AuthLayout title="Create your workspace" subtitle="Set up FusterAI for your team in under a minute">
            <Head title="Get started" />

            <form onSubmit={submit} className="space-y-4">
                {/* Workspace name */}
                <div className="space-y-1.5">
                    <Label className="text-sm font-medium">Workspace name</Label>
                    <Input
                        value={data.workspace_name}
                        onChange={(e) => setData('workspace_name', e.target.value)}
                        placeholder="Acme Support"
                        autoFocus
                        className={errors.workspace_name ? 'border-destructive' : ''}
                    />
                    {errors.workspace_name && <p className="text-xs text-destructive">{errors.workspace_name}</p>}
                </div>

                {/* Divider */}
                <div className="flex items-center gap-3 py-1">
                    <div className="flex-1 h-px bg-border" />
                    <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-widest">Admin account</span>
                    <div className="flex-1 h-px bg-border" />
                </div>

                {/* Name */}
                <div className="space-y-1.5">
                    <Label className="text-sm font-medium">Your name</Label>
                    <Input
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Jane Doe"
                        autoComplete="name"
                        className={errors.name ? 'border-destructive' : ''}
                    />
                    {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                </div>

                {/* Email */}
                <div className="space-y-1.5">
                    <Label className="text-sm font-medium">Email address</Label>
                    <Input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="jane@acme.com"
                        autoComplete="email"
                        className={errors.email ? 'border-destructive' : ''}
                    />
                    {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                </div>

                {/* Password row — side by side */}
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
                        {processing ? 'Creating workspace…' : 'Create workspace'}
                    </Button>
                </div>

                <p className="text-center text-xs text-muted-foreground">
                    Already have an account?{' '}
                    <a href="/login" className="text-primary font-medium hover:underline">
                        Sign in
                    </a>
                </p>
            </form>
        </AuthLayout>
    );
}
