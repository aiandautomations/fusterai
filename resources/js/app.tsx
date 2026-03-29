import React from 'react';
import './bootstrap';
import '../css/editor.css';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Toaster } from '@/Components/ui/sonner';
import { registerSlot } from '@/Components/SlotRenderer';

// ── Module components ─────────────────────────────────────────────────────────
// Lazy-loaded so inactive modules never bloat the initial bundle.
// Each entry maps a module alias → the slots it wants to fill.
// To add a new module: import lazily and add an entry here.
const moduleSlots: Record<string, { slot: string; component: React.LazyExoticComponent<React.ComponentType<any>> }[]> = {
    SlaManager: [{
        slot:      'conversation.sidebar.bottom',
        component: React.lazy(() => import('../../Modules/SlaManager/Resources/js/SlaSidebarPanel')),
    }],
    SatisfactionSurvey: [{
        slot:      'conversation.sidebar.bottom',
        component: React.lazy(() => import('../../Modules/SatisfactionSurvey/Resources/js/SurveySidebarPanel')),
    }],
};

const appName = document.querySelector('meta[name="app-name"]')?.getAttribute('content') ?? 'FusterAI';

createInertiaApp({
    title: (title) => `${title} — ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        // Register slots only for modules the backend reports as active.
        // Because components are lazy, the actual JS chunk is only fetched
        // the first time the slot renders — not at page load.
        const activeModules: string[] = (props.initialPage.props as any).activeModules ?? [];
        activeModules.forEach((alias) => {
            moduleSlots[alias]?.forEach(({ slot, component }) => registerSlot(slot, component));
        });

        const root = createRoot(el);
        root.render(
            <>
                <App {...props} />
                <Toaster richColors position="top-right" />
            </>,
        );
    },
    progress: {
        color: '#3b82f6',
    },
});
