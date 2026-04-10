import { AxiosInstance } from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        axios: AxiosInstance;
        Echo: Echo;
        Pusher: typeof Pusher;
    }
}

// Shared Inertia props
export interface PageProps {
    auth: {
        user: User;
    };
    flash: {
        success?: string;
        error?: string;
    };
    folders?: Folder[];
    ziggy: {
        url: string;
        port: number | null;
        defaults: Record<string, unknown>;
        routes: Record<string, unknown>;
    };
    branding?: {
        name: string | null;
        logo_url: string | null;
        website: string | null;
    };
    appearance?: {
        mode: 'light' | 'dark' | 'system';
        color: 'neutral' | 'amber' | 'blue' | 'cyan' | 'emerald' | 'fuchsia' | 'green' | 'indigo' | 'lime' | 'orange' | 'pink' | 'purple' | 'red' | 'rose' | 'sky' | 'teal' | 'violet' | 'yellow';
        font: 'inter' | 'figtree' | 'manrope' | 'system';
        radius: 'sm' | 'md' | 'lg' | 'xl';
        contrast: 'soft' | 'balanced' | 'strong';
    };
}

// Core models
export interface User {
    id: number;
    name: string;
    email: string;
    role: 'super_admin' | 'admin' | 'manager' | 'agent';
    avatar?: string;
    preferences?: Record<string, unknown>;
    last_active_at?: string;
    created_at: string;
}

export interface Workspace {
    id: number;
    name: string;
    slug: string;
    settings?: Record<string, unknown>;
}

export interface Mailbox {
    id: number;
    workspace_id: number;
    name: string;
    email: string;
    active: boolean;
    channel_type: string;
    signature?: string;
    created_at: string;
}

export interface Customer {
    id: number;
    workspace_id: number;
    name: string;
    email: string;
    phone?: string;
    avatar?: string;
    company?: string;
    meta?: Record<string, unknown>;
    notes?: string;
    is_blocked?: boolean;
    created_at: string;
}

export interface Conversation {
    id: number;
    workspace_id: number;
    mailbox_id: number;
    customer_id: number;
    assigned_user_id?: number;
    status: 'open' | 'pending' | 'closed' | 'spam';
    priority: 'low' | 'normal' | 'high' | 'urgent';
    subject: string;
    channel_type: string;
    ai_summary?: string;
    ai_tags?: string[];
    last_reply_at?: string;
    snoozed_until?: string;
    is_unread?: boolean;
    created_at: string;

    // Relations
    customer?: Customer;
    mailbox?: Mailbox;
    assigned_user?: User;
    threads?: Thread[];
    tags?: Tag[];
    folders?: Folder[];
}

export interface Thread {
    id: number;
    conversation_id: number;
    user_id?: number;
    customer_id?: number;
    type: 'message' | 'note' | 'activity' | 'ai_suggestion';
    body: string;
    body_plain?: string;
    source: 'email' | 'web' | 'chat' | 'whatsapp' | 'slack' | 'api';
    meta?: Record<string, unknown>;
    created_at: string;

    // Relations
    user?: User;
    customer?: Customer;
    attachments?: Attachment[];
}

export interface Attachment {
    id: number;
    thread_id: number;
    filename: string;
    mime_type: string;
    size: number;
    url: string;
}

export interface Tag {
    id: number;
    workspace_id: number;
    name: string;
    color: string;
}

export interface Folder {
    id: number;
    workspace_id: number;
    name: string;
    color: string;
    icon: string;
    order: number;
    open_count?: number;
    conversations_count?: number;
}

export interface AiSuggestion {
    id: number;
    conversation_id: number;
    thread_id?: number;
    type: 'reply' | 'summary' | 'categorization';
    content: string;
    model: string;
    accepted?: boolean;
    created_at: string;
}

export interface KnowledgeBase {
    id: number;
    workspace_id: number;
    name: string;
    description?: string;
    active: boolean;
}

export interface Module {
    id: number;
    alias: string;
    name: string;
    active: boolean;
    version: string;
}

// Pagination
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}
