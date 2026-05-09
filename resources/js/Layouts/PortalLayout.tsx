import React from 'react';
import { Link, router } from '@inertiajs/react';

interface Workspace {
    name: string;
    slug: string;
    logo_url?: string | null;
}

interface PortalLayoutProps {
    children: React.ReactNode;
    workspace: Workspace;
    customer?: { name: string; email: string } | null;
    title?: string;
}

export default function PortalLayout({ children, workspace, customer, title }: PortalLayoutProps) {
    function logout() {
        router.post(route('portal.logout', workspace.slug));
    }

    return (
        <div className="min-h-screen bg-gray-50" style={{ fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif' }}>
            <header className="bg-white border-b border-gray-200">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                    <Link href={route('portal.tickets.index', workspace.slug)} className="flex items-center gap-2.5">
                        {workspace.logo_url ? (
                            <img src={workspace.logo_url} alt={workspace.name} className="h-7 w-auto" />
                        ) : (
                            <div className="w-7 h-7 rounded-lg bg-violet-600 flex items-center justify-center">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                </svg>
                            </div>
                        )}
                        <span className="font-semibold text-sm text-gray-900">{workspace.name}</span>
                    </Link>

                    <div className="flex items-center gap-4">
                        {customer && (
                            <>
                                <Link href={route('portal.kb.index', workspace.slug)} className="text-sm text-gray-600 hover:text-gray-900">
                                    Help articles
                                </Link>
                                <Link href={route('portal.tickets.create', workspace.slug)} className="text-sm text-gray-600 hover:text-gray-900">
                                    New ticket
                                </Link>
                                <span className="text-sm text-gray-500">{customer.email}</span>
                                <button onClick={logout} className="text-sm text-gray-500 hover:text-gray-900">
                                    Sign out
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="max-w-4xl mx-auto px-4 sm:px-6 py-8">
                {title && (
                    <h1 className="text-xl font-semibold text-gray-900 mb-6">{title}</h1>
                )}
                {children}
            </main>
        </div>
    );
}
