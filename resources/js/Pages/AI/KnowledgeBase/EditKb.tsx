import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

interface KnowledgeBase {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
}

interface Props {
    kb: KnowledgeBase;
}

export default function EditKb({ kb }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        name: kb.name,
        description: kb.description ?? '',
        active: kb.active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('ai.knowledge-bases.update', kb.id));
    };

    return (
        <AppLayout>
            <Head title={`Settings — ${kb.name}`} />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex items-center gap-3 mb-6">
                    <Link href={route('ai.knowledge-bases.show', kb.id)} className="text-muted-foreground hover:text-foreground text-sm">
                        ← {kb.name}
                    </Link>
                </div>

                <h1 className="text-3xl font-semibold tracking-tight">Knowledge Base Settings</h1>

                <Card className="max-w-3xl bg-card/75">
                    <CardContent className="p-6">
                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
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
                        />
                        {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="active"
                            checked={data.active}
                            onCheckedChange={(checked) => setData('active', !!checked)}
                        />
                        <Label htmlFor="active">
                            Active — AI will use this knowledge base for reply suggestions
                        </Label>
                    </div>

                    <div className="flex gap-3 pt-2">
                        <Button type="submit" disabled={processing}>Save Changes</Button>
                        <Link href={route('ai.knowledge-bases.show', kb.id)}>
                            <Button type="button" variant="ghost">Cancel</Button>
                        </Link>
                    </div>
                </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
