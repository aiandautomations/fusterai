export type AgentStatus = 'online' | 'away' | 'busy' | 'offline';

export const AGENT_STATUS_COLORS: Record<AgentStatus, string> = {
    online: 'bg-emerald-500',
    away: 'bg-amber-400',
    busy: 'bg-rose-500',
    offline: 'bg-muted-foreground/40',
};

export const AGENT_STATUS_LABELS: Record<AgentStatus, string> = {
    online: 'Online',
    away: 'Away',
    busy: 'Busy',
    offline: 'Offline',
};
