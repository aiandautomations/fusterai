import React, { useRef, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import type { PageProps, Workspace } from '@/types';
import { ImageIcon, XIcon, UploadIcon, ZapIcon } from 'lucide-react';

const TIMEZONES = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Anchorage',
    'America/Honolulu',
    'America/Toronto',
    'America/Vancouver',
    'America/Sao_Paulo',
    'America/Mexico_City',
    'America/Buenos_Aires',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Madrid',
    'Europe/Rome',
    'Europe/Amsterdam',
    'Europe/Stockholm',
    'Europe/Warsaw',
    'Europe/Istanbul',
    'Europe/Moscow',
    'Asia/Dubai',
    'Asia/Kolkata',
    'Asia/Colombo',
    'Asia/Dhaka',
    'Asia/Karachi',
    'Asia/Kathmandu',
    'Asia/Bangkok',
    'Asia/Jakarta',
    'Asia/Singapore',
    'Asia/Kuala_Lumpur',
    'Asia/Shanghai',
    'Asia/Hong_Kong',
    'Asia/Tokyo',
    'Asia/Seoul',
    'Australia/Sydney',
    'Australia/Melbourne',
    'Australia/Brisbane',
    'Australia/Perth',
    'Pacific/Auckland',
];

interface BrandingData {
    name: string;
    logo_url: string | null;
    website: string;
}

interface Props extends PageProps {
    workspace: Workspace;
    branding: BrandingData;
}

export default function GeneralSettings({ workspace, branding }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        name: workspace.name ?? '',
        timezone: (workspace.settings?.timezone as string) ?? 'UTC',
    });

    // Branding form uses post() with FormData for file upload
    const brandingForm = useForm<{
        branding_name: string;
        branding_website: string;
        branding_logo: File | null;
    }>({
        branding_name: branding.name ?? '',
        branding_website: branding.website ?? '',
        branding_logo: null,
    });

    const [logoPreview, setLogoPreview] = useState<string | null>(branding.logo_url ?? null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        patch('/settings/general');
    }

    function handleLogoChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        brandingForm.setData('branding_logo', file);
        const reader = new FileReader();
        reader.onload = (ev) => setLogoPreview(ev.target?.result as string);
        reader.readAsDataURL(file);
    }

    function removeLogo() {
        brandingForm.setData('branding_logo', null);
        setLogoPreview(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    function handleBrandingSubmit(e: React.FormEvent) {
        e.preventDefault();
        brandingForm.post('/settings/branding', {
            forceFormData: true,
        });
    }

    return (
        <AppLayout>
            <Head title="General Settings" />

            <div className="w-full px-6 py-8 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">General Settings</h1>
                    <p className="text-sm text-muted-foreground mt-1">Manage your workspace configuration.</p>
                </div>

                {/* Workspace Settings */}
                <div className="rounded-xl border border-border bg-card">
                    <div className="flex items-start gap-4 px-6 py-5 border-b border-border">
                        <div>
                            <h2 className="text-[15px] font-semibold">Workspace</h2>
                            <p className="text-xs text-muted-foreground mt-0.5">Configure your workspace name and timezone.</p>
                        </div>
                    </div>
                    <div className="px-6 py-5">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="ws-name">Workspace name</Label>
                                <Input
                                    id="ws-name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="My workspace"
                                />
                                {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="ws-slug">Workspace slug</Label>
                                <Input id="ws-slug" value={workspace.slug} readOnly disabled className="bg-muted cursor-not-allowed" />
                                <p className="text-xs text-muted-foreground">Slug cannot be changed.</p>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="ws-tz">Timezone</Label>
                                <select
                                    id="ws-tz"
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    {TIMEZONES.map((tz) => (
                                        <option key={tz} value={tz}>
                                            {tz}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : 'Save changes'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Branding */}
                <div className="rounded-xl border border-border bg-card">
                    <div className="flex items-start gap-4 px-6 py-5 border-b border-border">
                        <div>
                            <h2 className="text-[15px] font-semibold">Branding</h2>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Customise the logo and company name shown in the app sidebar.
                            </p>
                        </div>
                    </div>
                    <div className="px-6 py-5">
                        <form onSubmit={handleBrandingSubmit} className="space-y-5">
                            {/* Logo upload */}
                            <div className="space-y-2">
                                <Label>Company logo</Label>
                                <div className="flex items-center gap-4">
                                    {/* Preview */}
                                    <div className="h-16 w-16 rounded-xl border-2 border-dashed border-border flex items-center justify-center bg-muted/30 overflow-hidden shrink-0">
                                        {logoPreview ? (
                                            <img src={logoPreview} alt="Logo preview" className="h-full w-full object-contain p-1" />
                                        ) : (
                                            <div className="flex flex-col items-center gap-1">
                                                <div className="h-7 w-7 rounded-lg bg-primary/10 flex items-center justify-center">
                                                    <ZapIcon className="h-3.5 w-3.5 text-primary" />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex-1 space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="gap-1.5"
                                                onClick={() => fileInputRef.current?.click()}
                                            >
                                                <UploadIcon className="h-3.5 w-3.5" />
                                                {logoPreview ? 'Change logo' : 'Upload logo'}
                                            </Button>
                                            {logoPreview && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="gap-1.5 text-muted-foreground hover:text-destructive"
                                                    onClick={removeLogo}
                                                >
                                                    <XIcon className="h-3.5 w-3.5" />
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">PNG, JPG or SVG. Recommended 200×200px, max 2 MB.</p>
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={handleLogoChange}
                                        />
                                    </div>
                                </div>
                                {brandingForm.errors.branding_logo && (
                                    <p className="text-xs text-destructive">{brandingForm.errors.branding_logo}</p>
                                )}
                            </div>

                            {/* Company name */}
                            <div className="space-y-1.5">
                                <Label htmlFor="branding-name">Company name</Label>
                                <Input
                                    id="branding-name"
                                    value={brandingForm.data.branding_name}
                                    onChange={(e) => brandingForm.setData('branding_name', e.target.value)}
                                    placeholder="Acme Corp"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Shown in the sidebar next to your logo. Defaults to workspace name if empty.
                                </p>
                                {brandingForm.errors.branding_name && (
                                    <p className="text-xs text-destructive">{brandingForm.errors.branding_name}</p>
                                )}
                            </div>

                            {/* Website */}
                            <div className="space-y-1.5">
                                <Label htmlFor="branding-website">Company website</Label>
                                <Input
                                    id="branding-website"
                                    type="url"
                                    value={brandingForm.data.branding_website}
                                    onChange={(e) => brandingForm.setData('branding_website', e.target.value)}
                                    placeholder="https://yourcompany.com"
                                />
                                {brandingForm.errors.branding_website && (
                                    <p className="text-xs text-destructive">{brandingForm.errors.branding_website}</p>
                                )}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={brandingForm.processing}>
                                    {brandingForm.processing ? 'Saving…' : 'Save branding'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Team Management */}
                <div className="rounded-xl border border-border bg-card">
                    <div className="flex items-start gap-4 px-6 py-5 border-b border-border">
                        <div>
                            <h2 className="text-[15px] font-semibold">Team Members</h2>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Add or remove team members and manage their mailbox access.
                            </p>
                        </div>
                    </div>
                    <div className="px-6 py-5 flex gap-2">
                        <Button size="sm" variant="outline" onClick={() => router.visit('/settings/users')}>
                            Manage Team
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => router.visit('/settings/appearance')}>
                            Appearance
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
