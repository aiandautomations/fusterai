import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

export default function CreateKb() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('ai.knowledge-bases.store'));
    };

    return (
        <AppLayout>
            <Head title="New Knowledge Base" />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex items-center gap-3 mb-6">
                    <Link href={route('ai.kb.index')} className="text-muted-foreground hover:text-foreground">
                        ← Knowledge Bases
                    </Link>
                </div>

                <h1 className="text-3xl font-semibold tracking-tight">New Knowledge Base</h1>

                <Card className="max-w-3xl bg-card/75">
                    <CardContent className="p-6">
                        <form onSubmit={submit} className="space-y-5">
                            <div className="space-y-1.5">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Product Documentation"
                                    autoFocus
                                />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="description">
                                    Description <span className="text-muted-foreground">(optional)</span>
                                </Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Brief description of what this knowledge base contains"
                                />
                                {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                            </div>

                            <div className="flex gap-3 pt-2">
                                <Button type="submit" disabled={processing}>
                                    Create Knowledge Base
                                </Button>
                                <Link href={route('ai.kb.index')}>
                                    <Button type="button" variant="ghost">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
