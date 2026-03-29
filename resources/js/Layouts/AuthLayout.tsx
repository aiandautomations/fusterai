import React from 'react';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    subtitle?: string;
}

const features = [
    { icon: '⚡', text: 'AI-suggested replies in seconds' },
    { icon: '🔀', text: 'Real-time team collaboration' },
    { icon: '📚', text: 'Knowledge base with RAG retrieval' },
];

export default function AuthLayout({ children, title, subtitle }: AuthLayoutProps) {
    const { branding } = usePage<PageProps>().props;
    const logoName = branding?.name || 'FusterAI';

    return (
        <>
            {/* Instrument Serif for display headings — editorial, characterful */}
            <link
                rel="stylesheet"
                href="https://fonts.bunny.net/css?family=instrument-serif:400,400i"
            />

            <style>{`
                @keyframes auth-orb-1 {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    33% { transform: translate(30px, -20px) scale(1.05); }
                    66% { transform: translate(-15px, 15px) scale(0.97); }
                }
                @keyframes auth-orb-2 {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    40% { transform: translate(-25px, 20px) scale(1.08); }
                    70% { transform: translate(20px, -10px) scale(0.95); }
                }
                @keyframes auth-fade-up {
                    from { opacity: 0; transform: translateY(16px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes auth-fade-in {
                    from { opacity: 0; }
                    to   { opacity: 1; }
                }
                @keyframes auth-grid-drift {
                    0% { background-position: 0 0; }
                    100% { background-position: 48px 48px; }
                }
                .auth-panel-left {
                    background-color: #0d0b18;
                    background-image:
                        linear-gradient(135deg, rgba(139,92,246,0.15) 0%, transparent 50%),
                        linear-gradient(225deg, rgba(99,102,241,0.12) 0%, transparent 50%);
                }
                .auth-grid {
                    background-image:
                        linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
                    background-size: 48px 48px;
                    animation: auth-grid-drift 8s linear infinite;
                }
                .auth-orb-1 {
                    animation: auth-orb-1 12s ease-in-out infinite;
                }
                .auth-orb-2 {
                    animation: auth-orb-2 16s ease-in-out infinite;
                }
                .auth-stagger-1 { animation: auth-fade-up 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both; }
                .auth-stagger-2 { animation: auth-fade-up 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both; }
                .auth-stagger-3 { animation: auth-fade-up 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.3s both; }
                .auth-stagger-4 { animation: auth-fade-up 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.4s both; }
                .auth-stagger-5 { animation: auth-fade-up 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.5s both; }
                .auth-form-panel { animation: auth-fade-in 0.5s ease 0.15s both; }
                .auth-display { font-family: 'Instrument Serif', Georgia, serif; }
                .auth-input-ring:focus-within {
                    box-shadow: 0 0 0 3px oklch(0.58 0.23 292 / 0.2);
                }
            `}</style>

            <div className="min-h-screen flex" style={{ fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif' }}>

                {/* ── Left: brand panel ── */}
                <div className="auth-panel-left hidden lg:flex lg:w-[46%] xl:w-[42%] relative flex-col overflow-hidden">

                    {/* Animated grid */}
                    <div className="auth-grid absolute inset-0 opacity-100" />

                    {/* Ambient orbs */}
                    <div className="auth-orb-1 absolute top-[15%] left-[20%] w-80 h-80 rounded-full"
                        style={{ background: 'radial-gradient(circle, oklch(0.58 0.23 292 / 0.22) 0%, transparent 70%)' }} />
                    <div className="auth-orb-2 absolute bottom-[20%] right-[10%] w-64 h-64 rounded-full"
                        style={{ background: 'radial-gradient(circle, oklch(0.56 0.22 278 / 0.18) 0%, transparent 70%)' }} />

                    {/* Top dot */}
                    <div className="relative z-10 flex items-center gap-2.5 p-10 pb-0">
                        {branding?.logo_url ? (
                            <img src={branding.logo_url} alt={logoName} className="w-9 h-9 rounded-xl object-contain bg-white/10 p-1" />
                        ) : (
                            <div className="flex items-center justify-center w-9 h-9 rounded-xl"
                                style={{ background: 'oklch(0.58 0.23 292 / 0.25)', border: '1px solid oklch(0.58 0.23 292 / 0.4)' }}>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="oklch(0.8 0.15 292)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                </svg>
                            </div>
                        )}
                        <span className="text-white/90 font-semibold text-lg tracking-tight">{logoName}</span>
                    </div>

                    {/* Main brand copy */}
                    <div className="relative z-10 flex-1 flex flex-col justify-center px-10 py-8">
                        <div className="auth-stagger-1">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium mb-6"
                                style={{
                                    background: 'oklch(0.58 0.23 292 / 0.15)',
                                    border: '1px solid oklch(0.58 0.23 292 / 0.3)',
                                    color: 'oklch(0.82 0.14 292)',
                                    letterSpacing: '0.05em',
                                }}>
                                <span className="w-1.5 h-1.5 rounded-full bg-current animate-pulse" />
                                AI-first helpdesk · 2026
                            </div>
                        </div>

                        <h1 className="auth-display auth-stagger-2 text-white leading-[1.08] mb-5"
                            style={{ fontSize: 'clamp(2rem, 3.5vw, 2.75rem)', fontWeight: 400 }}>
                            Support that<br />
                            <span style={{ color: 'oklch(0.82 0.14 292)', fontStyle: 'italic' }}>thinks</span> with<br />
                            your team
                        </h1>

                        <p className="auth-stagger-3 text-sm leading-relaxed mb-10"
                            style={{ color: 'rgba(255,255,255,0.45)', maxWidth: '26rem' }}>
                            An AI-native helpdesk that drafts replies, categorises tickets, and
                            learns from your knowledge base — so your team focuses on what matters.
                        </p>

                        <div className="auth-stagger-4 space-y-3 mb-10">
                            {features.map((f, i) => (
                                <div key={i} className="flex items-center gap-3">
                                    <span className="text-base">{f.icon}</span>
                                    <span className="text-sm" style={{ color: 'rgba(255,255,255,0.65)' }}>{f.text}</span>
                                </div>
                            ))}
                        </div>

                        {/* Bottom stat strip */}
                        <div className="auth-stagger-5 flex items-center gap-6 pt-8"
                            style={{ borderTop: '1px solid rgba(255,255,255,0.08)' }}>
                            {[
                                { n: '12×', label: 'faster replies with AI' },
                                { n: '99.9%', label: 'uptime SLA' },
                                { n: 'Open', label: 'source, self-hosted' },
                            ].map((stat) => (
                                <div key={stat.n}>
                                    <div className="text-lg font-semibold text-white">{stat.n}</div>
                                    <div className="text-xs" style={{ color: 'rgba(255,255,255,0.4)' }}>{stat.label}</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Bottom noise vignette */}
                    <div className="absolute inset-x-0 bottom-0 h-32"
                        style={{ background: 'linear-gradient(to top, #0d0b18, transparent)' }} />
                </div>

                {/* ── Right: form panel ── */}
                <div className="auth-form-panel flex-1 flex flex-col items-center justify-center bg-background px-6 py-12 relative">

                    {/* Subtle top-right glow that bleeds in from the brand panel on mobile */}
                    <div className="pointer-events-none absolute inset-0 -z-10"
                        style={{ background: 'radial-gradient(ellipse at 80% 10%, oklch(0.58 0.23 292 / 0.07), transparent 60%)' }} />

                    <div className="w-full max-w-[22rem]">
                        {/* Mobile logo — hidden on desktop where brand panel shows */}
                        <div className="lg:hidden flex items-center gap-2.5 mb-10">
                            {branding?.logo_url ? (
                                <img src={branding.logo_url} alt={logoName} className="w-8 h-8 rounded-xl object-contain" />
                            ) : (
                                <div className="flex items-center justify-center w-8 h-8 rounded-xl"
                                    style={{ background: 'oklch(0.58 0.23 292 / 0.12)', border: '1px solid oklch(0.58 0.23 292 / 0.25)' }}>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="oklch(0.58 0.23 292)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                    </svg>
                                </div>
                            )}
                            <span className="font-semibold text-base text-foreground tracking-tight">{logoName}</span>
                        </div>

                        {/* Heading */}
                        <div className="mb-7">
                            <h2 className="text-xl font-semibold text-foreground mb-1">{title}</h2>
                            {subtitle && (
                                <p className="text-sm text-muted-foreground leading-relaxed">{subtitle}</p>
                            )}
                        </div>

                        {children}
                    </div>
                </div>
            </div>
        </>
    );
}
