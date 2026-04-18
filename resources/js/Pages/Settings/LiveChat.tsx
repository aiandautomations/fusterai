import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { CheckIcon, CopyIcon, MessageSquareIcon } from 'lucide-react';

interface Props {
    workspaceId: number;
    themeColorHex: string;
    config: {
        greeting: string;
        color: string;
        position: 'bottom-right' | 'bottom-left';
        launcher_text: string;
    };
    snippet: {
        wsKey: string;
        wsHost: string;
        wsPort: number;
        wsScheme: string;
        apiBase: string;
    };
}

export default function LiveChatSettings({ workspaceId, themeColorHex, config, snippet }: Props) {
    const { data, setData, patch, processing } = useForm({ ...config });
    const [copied, setCopied] = React.useState(false);

    const embedCode = `<!-- FusterAI Live Chat Widget -->
<script>
  window.FusterAIChat = {
    workspaceId: ${workspaceId},
    wsKey:       '${snippet.wsKey}',
    wsHost:      '${snippet.wsHost}',
    wsPort:      ${snippet.wsPort},
    wsScheme:    '${snippet.wsScheme}',
    apiBase:     '${snippet.apiBase}',
  };
</script>
<script src="${snippet.apiBase}/livechat/widget.js" async></script>`;

    function copy() {
        navigator.clipboard.writeText(embedCode);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch('/settings/live-chat');
    }

    return (
        <AppLayout>
            <Head title="Live Chat" />
            <div className="w-full px-6 py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Live Chat</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Embed the widget on your site and configure the chat experience.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary">
                            <MessageSquareIcon className="h-3 w-3 mr-1" />
                            Workspace #{workspaceId}
                        </Badge>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    {/* Embed Snippet */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Embed Snippet</CardTitle>
                            <CardDescription>
                                Paste this before the <code className="text-xs bg-muted px-1 py-0.5 rounded">&lt;/body&gt;</code> tag of
                                every page where you want the chat widget.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="relative group">
                                <pre className="rounded-xl bg-muted/60 border border-border p-4 text-[11.5px] leading-relaxed text-foreground/85 overflow-x-auto font-mono">
                                    {embedCode}
                                </pre>
                                <button
                                    type="button"
                                    onClick={copy}
                                    className="absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-lg bg-background border border-border px-2.5 py-1.5 text-xs font-medium text-foreground/70 shadow-sm hover:text-foreground transition-all opacity-0 group-hover:opacity-100"
                                >
                                    {copied ? <CheckIcon className="h-3.5 w-3.5 text-emerald-500" /> : <CopyIcon className="h-3.5 w-3.5" />}
                                    {copied ? 'Copied!' : 'Copy'}
                                </button>
                            </div>

                            {/* Connection details */}
                            <div className="rounded-xl border border-border divide-y divide-border text-[13px]">
                                {[
                                    { label: 'Workspace ID', value: String(workspaceId) },
                                    { label: 'Reverb Host', value: `${snippet.wsScheme}://${snippet.wsHost}:${snippet.wsPort}` },
                                    { label: 'API Base', value: snippet.apiBase },
                                    { label: 'Widget URL', value: `${snippet.apiBase}/livechat/widget.js` },
                                ].map(({ label, value }) => (
                                    <div key={label} className="flex items-center justify-between px-4 py-2.5 gap-4">
                                        <span className="text-muted-foreground shrink-0">{label}</span>
                                        <span className="font-mono text-[12px] text-foreground/80 truncate text-right">{value}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Widget Config */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Widget Settings</CardTitle>
                            <CardDescription>Customise the chat widget's look and greeting.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                <div className="space-y-1.5">
                                    <Label htmlFor="greeting">Greeting message</Label>
                                    <Input
                                        id="greeting"
                                        value={data.greeting}
                                        onChange={(e) => setData('greeting', e.target.value)}
                                        placeholder="Hi there! How can we help?"
                                        maxLength={200}
                                    />
                                    <p className="text-xs text-muted-foreground">Shown to visitors when they open the chat widget.</p>
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="launcher_text">Launcher button text</Label>
                                    <Input
                                        id="launcher_text"
                                        value={data.launcher_text}
                                        onChange={(e) => setData('launcher_text', e.target.value)}
                                        placeholder="Chat with us"
                                        maxLength={60}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="color">Brand colour</Label>
                                            {data.color.toLowerCase() !== themeColorHex.toLowerCase() && (
                                                <button
                                                    type="button"
                                                    onClick={() => setData('color', themeColorHex)}
                                                    className="flex items-center gap-1 text-[11px] text-primary hover:underline"
                                                >
                                                    <span
                                                        className="inline-block h-2 w-2 rounded-full border border-primary/30"
                                                        style={{ backgroundColor: themeColorHex }}
                                                    />
                                                    Use theme
                                                </button>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="color"
                                                id="color"
                                                value={data.color}
                                                onChange={(e) => setData('color', e.target.value)}
                                                className="h-9 w-12 cursor-pointer rounded-md border border-input bg-background p-0.5"
                                            />
                                            <Input
                                                value={data.color}
                                                onChange={(e) => setData('color', e.target.value)}
                                                className="font-mono text-sm"
                                                maxLength={20}
                                            />
                                        </div>
                                        {data.color.toLowerCase() === themeColorHex.toLowerCase() && (
                                            <p className="text-[11px] text-muted-foreground flex items-center gap-1">
                                                <span
                                                    className="inline-block h-2 w-2 rounded-full"
                                                    style={{ backgroundColor: themeColorHex }}
                                                />
                                                Synced with theme
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="position">Widget position</Label>
                                        <Select
                                            value={data.position}
                                            onValueChange={(v: 'bottom-right' | 'bottom-left') => setData('position', v)}
                                        >
                                            <SelectTrigger id="position" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="bottom-right">Bottom right</SelectItem>
                                                <SelectItem value="bottom-left">Bottom left</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                {/* Preview */}
                                <div className="rounded-xl border border-border bg-muted/30 p-4 relative overflow-hidden min-h-[100px]">
                                    <p className="text-xs text-muted-foreground mb-3">Preview</p>
                                    <div
                                        className="absolute bottom-4 flex items-center gap-2 rounded-full px-4 py-2 text-white text-sm font-medium shadow-lg cursor-pointer"
                                        style={{
                                            backgroundColor: data.color,
                                            [data.position === 'bottom-right' ? 'right' : 'left']: '16px',
                                        }}
                                    >
                                        <MessageSquareIcon className="h-4 w-4" />
                                        {data.launcher_text}
                                    </div>
                                </div>

                                <Button type="submit" disabled={processing} className="w-full">
                                    {processing ? 'Saving…' : 'Save Settings'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
