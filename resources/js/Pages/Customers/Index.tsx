import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Input } from '@/Components/ui/input';
import { getInitials } from '@/lib/utils';
import type { Customer, Paginated } from '@/types';
import { SearchIcon, MailIcon, BuildingIcon, UsersIcon, ChevronRightIcon } from 'lucide-react';

interface Props {
    customers: Paginated<Customer & { conversations_count: number }>;
    filters: { search?: string };
}

export default function CustomersIndex({ customers, filters }: Props) {
    return (
        <AppLayout>
            <Head title="Customers" />

            <div className="w-full px-6 py-8 space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Customers</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Unified customer directory and conversation history.
                        </p>
                    </div>

                    <div className="relative w-72">
                        <SearchIcon className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-8"
                            placeholder="Search customers…"
                            defaultValue={filters.search}
                            onChange={(e) => {
                                router.get('/customers', { search: e.target.value }, {
                                    preserveScroll: true,
                                    replace: true,
                                });
                            }}
                        />
                    </div>
                </div>

                <div className="rounded-xl border border-border/80 bg-card/75 p-5 flex items-center gap-3 w-fit">
                    <UsersIcon className="h-4 w-4 text-muted-foreground" />
                    <div>
                        <p className="text-xs uppercase tracking-[0.12em] text-muted-foreground">Total Customers</p>
                        <p className="text-2xl font-bold mt-0.5">{customers.total}</p>
                    </div>
                </div>

                <div className="space-y-2">
                    {customers.data.map((customer) => (
                        <Link
                            key={customer.id}
                            href={`/customers/${customer.id}`}
                            className="group flex items-center gap-4 rounded-xl border border-border/80 bg-card/70 p-4 transition-colors hover:bg-muted/20"
                        >
                            <Avatar className="size-10">
                                <AvatarImage src={customer.avatar ?? undefined} alt={customer.name} />
                                <AvatarFallback>{getInitials(customer.name)}</AvatarFallback>
                            </Avatar>
                            <div className="flex-1 min-w-0">
                                <p className="text-base font-semibold group-hover:text-primary transition-colors">
                                    {customer.name}
                                </p>
                                <div className="mt-1 flex items-center gap-3 text-sm text-muted-foreground">
                                    {customer.email && (
                                        <span className="flex items-center gap-1">
                                            <MailIcon className="h-3 w-3" />
                                            {customer.email}
                                        </span>
                                    )}
                                    {customer.company && (
                                        <span className="flex items-center gap-1">
                                            <BuildingIcon className="h-3 w-3" />
                                            {customer.company}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <span className="shrink-0 text-sm text-muted-foreground">
                                {customer.conversations_count} conversation{customer.conversations_count !== 1 ? 's' : ''}
                            </span>
                            <ChevronRightIcon className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity shrink-0" />
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
