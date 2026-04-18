import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
    applyAppearance,
    persistAppearance,
    type AppearanceColor,
    type AppearanceContrast,
    type AppearanceFont,
    type AppearanceMode,
    type AppearanceRadius,
} from '@/lib/appearance';

interface Props {
    appearance: {
        mode: AppearanceMode;
        color: AppearanceColor;
        font: AppearanceFont;
        radius: AppearanceRadius;
        contrast: AppearanceContrast;
    };
}

const COLOR_OPTIONS: { value: AppearanceColor; label: string; swatch: string; isDefault?: boolean }[] = [
    { value: 'violet', label: 'Violet (Default)', swatch: 'oklch(0.58 0.23 292)', isDefault: true },
    { value: 'neutral', label: 'Neutral', swatch: 'oklch(0.42 0 0)' },
    { value: 'amber', label: 'Amber', swatch: 'oklch(0.72 0.17 79)' },
    { value: 'blue', label: 'Blue', swatch: 'oklch(0.63 0.19 259)' },
    { value: 'cyan', label: 'Cyan', swatch: 'oklch(0.71 0.12 221)' },
    { value: 'emerald', label: 'Emerald', swatch: 'oklch(0.66 0.16 162)' },
    { value: 'fuchsia', label: 'Fuchsia', swatch: 'oklch(0.67 0.28 327)' },
    { value: 'green', label: 'Green', swatch: 'oklch(0.62 0.19 145)' },
    { value: 'indigo', label: 'Indigo', swatch: 'oklch(0.56 0.22 278)' },
    { value: 'lime', label: 'Lime', swatch: 'oklch(0.8 0.18 126)' },
    { value: 'orange', label: 'Orange', swatch: 'oklch(0.71 0.2 52)' },
    { value: 'pink', label: 'Pink', swatch: 'oklch(0.72 0.18 8)' },
    { value: 'purple', label: 'Purple', swatch: 'oklch(0.58 0.25 305)' },
    { value: 'red', label: 'Red', swatch: 'oklch(0.61 0.25 28)' },
    { value: 'rose', label: 'Rose', swatch: 'oklch(0.64 0.2 16)' },
    { value: 'sky', label: 'Sky', swatch: 'oklch(0.74 0.12 237)' },
    { value: 'teal', label: 'Teal', swatch: 'oklch(0.65 0.12 193)' },
    { value: 'yellow', label: 'Yellow', swatch: 'oklch(0.84 0.16 96)' },
];

const FONT_OPTIONS: { value: AppearanceFont; label: string }[] = [
    { value: 'inter', label: 'Inter' },
    { value: 'figtree', label: 'Figtree' },
    { value: 'manrope', label: 'Manrope' },
    { value: 'system', label: 'System' },
];

const PRESETS: {
    name: string;
    isDefault?: boolean;
    mode: AppearanceMode;
    color: AppearanceColor;
    font: AppearanceFont;
    radius: AppearanceRadius;
    contrast: AppearanceContrast;
}[] = [
    { name: 'FusterAI Default', isDefault: true, mode: 'system', color: 'violet', font: 'figtree', radius: 'sm', contrast: 'balanced' },
    { name: 'Modern Indigo', mode: 'system', color: 'indigo', font: 'figtree', radius: 'lg', contrast: 'balanced' },
    { name: 'Soft Neutral', mode: 'light', color: 'neutral', font: 'inter', radius: 'xl', contrast: 'soft' },
    { name: 'Night Emerald', mode: 'dark', color: 'emerald', font: 'manrope', radius: 'md', contrast: 'strong' },
    { name: 'Bold Rose', mode: 'light', color: 'rose', font: 'figtree', radius: 'sm', contrast: 'strong' },
];

export default function AppearanceSettings({ appearance }: Props) {
    const { data, setData, patch, processing } = useForm({
        mode: appearance.mode,
        color: appearance.color,
        font: appearance.font,
        radius: appearance.radius,
        contrast: appearance.contrast,
    });

    React.useEffect(() => {
        const settings = {
            mode: data.mode,
            color: data.color,
            font: data.font,
            radius: data.radius,
            contrast: data.contrast,
        };
        applyAppearance(settings);
        persistAppearance(settings);
    }, [data.mode, data.color, data.font, data.radius, data.contrast]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch('/settings/appearance');
    }

    function applyPreset(preset: (typeof PRESETS)[number]) {
        setData('mode', preset.mode);
        setData('color', preset.color);
        setData('font', preset.font);
        setData('radius', preset.radius);
        setData('contrast', preset.contrast);
    }

    return (
        <AppLayout>
            <Head title="Appearance" />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="rounded-2xl border border-border bg-card/70 p-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold tracking-tight">Theme Studio</h1>
                            <p className="text-sm text-muted-foreground mt-1">
                                Full control over mode, color, typography, contrast, and shape.
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant="secondary">Live</Badge>
                            <Badge variant="outline">Workspace-wide</Badge>
                        </div>
                    </div>
                </div>

                <form onSubmit={submit}>
                    <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                        <Card className="xl:sticky xl:top-4 h-fit">
                            <CardHeader>
                                <CardTitle>Controls</CardTitle>
                                <CardDescription>Pick settings and save.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="space-y-2">
                                    <Label htmlFor="appearance-mode">Mode</Label>
                                    <Select value={data.mode} onValueChange={(value: AppearanceMode) => setData('mode', value)}>
                                        <SelectTrigger id="appearance-mode" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="light">Light</SelectItem>
                                            <SelectItem value="dark">Dark</SelectItem>
                                            <SelectItem value="system">System</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="appearance-color">Theme Color</Label>
                                    <Select value={data.color} onValueChange={(value: AppearanceColor) => setData('color', value)}>
                                        <SelectTrigger id="appearance-color" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {COLOR_OPTIONS.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    <span className="flex items-center gap-2">
                                                        <span
                                                            className="size-2.5 rounded-full"
                                                            style={{ backgroundColor: option.swatch }}
                                                        />
                                                        {option.label}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="appearance-font">Font</Label>
                                    <Select value={data.font} onValueChange={(value: AppearanceFont) => setData('font', value)}>
                                        <SelectTrigger id="appearance-font" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {FONT_OPTIONS.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="appearance-radius">Radius</Label>
                                        <Select value={data.radius} onValueChange={(value: AppearanceRadius) => setData('radius', value)}>
                                            <SelectTrigger id="appearance-radius" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="sm">Compact</SelectItem>
                                                <SelectItem value="md">Medium</SelectItem>
                                                <SelectItem value="lg">Comfort</SelectItem>
                                                <SelectItem value="xl">Rounded</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="appearance-contrast">Contrast</Label>
                                        <Select
                                            value={data.contrast}
                                            onValueChange={(value: AppearanceContrast) => setData('contrast', value)}
                                        >
                                            <SelectTrigger id="appearance-contrast" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="soft">Soft</SelectItem>
                                                <SelectItem value="balanced">Balanced</SelectItem>
                                                <SelectItem value="strong">Strong</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>Presets</Label>
                                    <div className="grid grid-cols-2 gap-2">
                                        {PRESETS.map((preset) => (
                                            <button
                                                key={preset.name}
                                                type="button"
                                                onClick={() => applyPreset(preset)}
                                                className={
                                                    preset.isDefault
                                                        ? 'col-span-2 rounded-md border border-primary/40 bg-primary/8 px-3 py-2 text-left text-xs font-medium text-primary hover:bg-primary/12 transition-colors flex items-center justify-between'
                                                        : 'rounded-md border border-border bg-background px-3 py-2 text-left text-xs hover:bg-muted/40 transition-colors'
                                                }
                                            >
                                                <span>{preset.name}</span>
                                                {preset.isDefault && (
                                                    <span className="text-[10px] font-semibold uppercase tracking-wide opacity-60">
                                                        Default
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <Button type="submit" disabled={processing} className="w-full">
                                    {processing ? 'Saving…' : 'Save Appearance'}
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Live Preview</CardTitle>
                                <CardDescription>How your product UI will feel with this theme.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
                                    <div className="rounded-xl border border-border bg-sidebar text-sidebar-foreground p-3 space-y-3">
                                        <div className="rounded-md bg-sidebar-accent text-sidebar-accent-foreground px-3 py-2 text-sm font-medium">
                                            Inbox
                                        </div>
                                        <div className="rounded-md px-3 py-2 text-sm opacity-80">Customers</div>
                                        <div className="rounded-md px-3 py-2 text-sm opacity-80">Reports</div>
                                        <div className="h-px bg-sidebar-muted" />
                                        <div className="rounded-md px-3 py-2 text-xs opacity-70">Theme: {data.mode}</div>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="rounded-xl border border-border bg-card p-4 space-y-3">
                                                <p className="text-sm font-semibold">Compose Panel</p>
                                                <p className="text-sm text-muted-foreground">
                                                    The quick brown fox jumps over the lazy dog.
                                                </p>
                                                <div className="flex gap-2">
                                                    <span className="inline-flex rounded-md bg-primary px-3 py-1.5 text-xs text-primary-foreground">
                                                        Send
                                                    </span>
                                                    <span className="inline-flex rounded-md bg-secondary px-3 py-1.5 text-xs text-secondary-foreground">
                                                        Draft
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="rounded-xl border border-border bg-popover p-4 space-y-2">
                                                <p className="text-sm font-semibold">Stats</p>
                                                <div className="grid grid-cols-2 gap-2">
                                                    <div className="rounded-md bg-muted p-2 text-xs">Open: 24</div>
                                                    <div className="rounded-md bg-muted p-2 text-xs">SLA: 98%</div>
                                                    <div className="rounded-md bg-muted p-2 text-xs">CSAT: 4.8</div>
                                                    <div className="rounded-md bg-muted p-2 text-xs">Backlog: 7</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-border bg-background p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-semibold">Conversation Preview</p>
                                                    <p className="text-xs text-muted-foreground mt-0.5">Typography and spacing sample</p>
                                                </div>
                                                <Badge variant="secondary">{data.font}</Badge>
                                            </div>
                                            <div className="mt-3 space-y-2">
                                                <div className="max-w-[75%] rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
                                                    Hi team, can you help with my invoice?
                                                </div>
                                                <div className="ml-auto max-w-[75%] rounded-lg bg-primary px-3 py-2 text-sm text-primary-foreground">
                                                    Absolutely. I am checking it now.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
