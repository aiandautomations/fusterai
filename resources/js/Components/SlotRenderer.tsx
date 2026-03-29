import React from 'react';

// Module slot registry — modules register React components here
const slotRegistry: Record<string, React.ComponentType<any>[]> = {};

export function registerSlot(name: string, component: React.ComponentType<any>): void {
    if (!slotRegistry[name]) slotRegistry[name] = [];
    slotRegistry[name].push(component);
}

// ── Error boundary ────────────────────────────────────────────────────────────
// Prevents a crashing module component from taking down the whole page.
// Renders nothing on error so the rest of the UI stays intact.

interface BoundaryState {
    hasError: boolean;
}

class SlotErrorBoundary extends React.Component<
    { slotName: string; children: React.ReactNode },
    BoundaryState
> {
    constructor(props: { slotName: string; children: React.ReactNode }) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(): BoundaryState {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: React.ErrorInfo): void {
        console.error(`[SlotRenderer] Slot "${this.props.slotName}" crashed:`, error, info.componentStack);
    }

    render(): React.ReactNode {
        if (this.state.hasError) return null;
        return this.props.children;
    }
}

// ── SlotRenderer ──────────────────────────────────────────────────────────────

interface SlotRendererProps {
    name: string;
    props?: Record<string, unknown>;
}

export default function SlotRenderer({ name, props = {} }: SlotRendererProps) {
    const components = slotRegistry[name] ?? [];
    if (components.length === 0) return null;
    return (
        <>
            {components.map((Component, i) => (
                <SlotErrorBoundary key={i} slotName={name}>
                    <React.Suspense fallback={null}>
                        <Component {...props} />
                    </React.Suspense>
                </SlotErrorBoundary>
            ))}
        </>
    );
}
