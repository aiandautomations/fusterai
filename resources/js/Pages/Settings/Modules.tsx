import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import type { PageProps } from '@/types';
import { BoxIcon } from 'lucide-react';

interface ModuleItem {
    id: number | null;
    alias: string;
    name: string;
    active: boolean;
    version: string;
    description?: string;
}

interface Props extends PageProps {
    modules: ModuleItem[];
}

export default function ModulesSettings({ modules }: Props) {
    function toggleModule(module: ModuleItem) {
        router.patch(`/settings/modules/${module.alias}`, { active: !module.active });
    }

    return (
        <AppLayout>
            <Head title="Modules" />

            <div className="w-full px-6 py-8 space-y-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Modules</h1>
                    <p className="text-sm text-muted-foreground mt-1">Manage installed modules and integrations.</p>
                </div>

                <section className="bg-card border border-border rounded-lg overflow-hidden">
                    {modules.length === 0 ? (
                        <div className="text-center py-16 px-6">
                            <BoxIcon className="h-10 w-10 text-muted-foreground mx-auto mb-3" />
                            <p className="font-medium text-foreground mb-1">No modules installed</p>
                            <p className="text-sm text-muted-foreground max-w-sm mx-auto">
                                Drop a module folder into <code className="bg-muted px-1 rounded text-xs">Modules/</code> and it will appear
                                here automatically.
                            </p>
                            <p className="text-xs text-muted-foreground mt-3">
                                Example:{' '}
                                <code className="bg-muted px-1 rounded">
                                    Modules/SatisfactionSurvey/Providers/SatisfactionSurveyServiceProvider.php
                                </code>
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-border">
                            {modules.map((module) => (
                                <li key={module.id} className="flex items-center gap-4 px-6 py-5 hover:bg-muted/20 transition-colors">
                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted/60 text-muted-foreground">
                                        <BoxIcon className="h-4 w-4" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <p className="font-medium text-sm">{module.name}</p>
                                            <span className="text-xs text-muted-foreground bg-muted px-1.5 py-0.5 rounded">
                                                v{module.version}
                                            </span>
                                            {module.active ? (
                                                <span className="text-xs font-medium text-success bg-success/10 px-2 py-0.5 rounded-full">
                                                    Active
                                                </span>
                                            ) : (
                                                <span className="text-xs font-medium text-muted-foreground bg-muted px-2 py-0.5 rounded-full">
                                                    Inactive
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-0.5">
                                            {module.description ?? 'No description provided.'}
                                        </p>
                                    </div>

                                    {/* Toggle */}
                                    <button
                                        type="button"
                                        onClick={() => toggleModule(module)}
                                        className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
                                            module.active ? 'bg-primary' : 'bg-muted-foreground/30'
                                        }`}
                                        role="switch"
                                        aria-checked={module.active}
                                        aria-label={`${module.active ? 'Disable' : 'Enable'} ${module.name}`}
                                        title={module.active ? 'Active — click to disable' : 'Inactive — click to enable'}
                                    >
                                        <span
                                            className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-background shadow ring-0 transition duration-200 ease-in-out ${
                                                module.active ? 'translate-x-4' : 'translate-x-0'
                                            }`}
                                        />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
