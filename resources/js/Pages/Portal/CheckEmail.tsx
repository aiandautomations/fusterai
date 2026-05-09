import React from 'react';
import { Head, Link } from '@inertiajs/react';

interface Props {
    workspace: { name: string; slug: string };
}

export default function CheckEmail({ workspace }: Props) {
    return (
        <div
            className="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4"
            style={{ fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif' }}
        >
            <Head title={`Check your email — ${workspace.name}`} />

            <div className="w-full max-w-sm text-center">
                <div className="w-12 h-12 rounded-xl bg-violet-50 border border-violet-200 flex items-center justify-center mx-auto mb-5">
                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="#7c3aed"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <rect width="20" height="16" x="2" y="4" rx="2" />
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                    </svg>
                </div>

                <h1 className="text-lg font-semibold text-gray-900 mb-2">Check your email</h1>
                <p className="text-sm text-gray-500 mb-6">
                    We've sent a sign-in link to your email address. Click the link to access your support portal.
                </p>

                <p className="text-xs text-gray-400">
                    Didn't receive it?{' '}
                    <Link href={route('portal.login', workspace.slug)} className="text-violet-600 hover:underline">
                        Try again
                    </Link>
                </p>
            </div>
        </div>
    );
}
