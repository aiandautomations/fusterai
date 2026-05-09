import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Props {
    workspace: {
        name: string;
        slug: string;
        welcome_text: string;
        logo_url?: string | null;
    };
    status?: string;
}

export default function Login({ workspace, status }: Props) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('portal.magic-link', workspace.slug));
    }

    return (
        <div
            className="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4"
            style={{ fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif' }}
        >
            <Head title={`Sign in — ${workspace.name}`} />

            <div className="w-full max-w-sm">
                <div className="flex items-center gap-2.5 mb-8 justify-center">
                    {workspace.logo_url ? (
                        <img src={workspace.logo_url} alt={workspace.name} className="h-8 w-auto" />
                    ) : (
                        <div className="w-8 h-8 rounded-lg bg-violet-600 flex items-center justify-center">
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="white"
                                strokeWidth="2.5"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                            </svg>
                        </div>
                    )}
                    <span className="font-semibold text-base text-gray-900">{workspace.name}</span>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h1 className="text-lg font-semibold text-gray-900 mb-1">Sign in</h1>
                    <p className="text-sm text-gray-500 mb-5">{workspace.welcome_text}</p>

                    {status && (
                        <Alert className="mb-4 border-red-200 bg-red-50 text-red-700">
                            <AlertDescription>{status}</AlertDescription>
                        </Alert>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label className="text-sm font-medium text-gray-700">Email address</Label>
                            <Input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="you@example.com"
                                autoComplete="email"
                                autoFocus
                                className={errors.email ? 'border-red-400' : ''}
                            />
                            {errors.email && <p className="text-xs text-red-600">{errors.email}</p>}
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Sending…' : 'Send sign-in link'}
                        </Button>
                    </form>

                    <p className="text-xs text-gray-400 text-center mt-4">We'll email you a magic link — no password needed.</p>
                </div>
            </div>
        </div>
    );
}
