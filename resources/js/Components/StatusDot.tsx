import { cn } from '@/lib/utils';
import { AGENT_STATUS_COLORS, type AgentStatus } from '@/lib/agentStatus';

interface StatusDotProps {
    status: string | undefined;
    /** 'sm' = size-1.5 ring-1 (inside small avatars), 'md' = size-2.5 ring-2 (default) */
    size?: 'sm' | 'md';
}

export default function StatusDot({ status, size = 'md' }: StatusDotProps) {
    const color = AGENT_STATUS_COLORS[(status ?? 'offline') as AgentStatus] ?? AGENT_STATUS_COLORS.offline;
    return (
        <span className={cn(
            'absolute -bottom-0.5 -right-0.5 rounded-full ring-background',
            size === 'sm' ? 'size-1.5 ring-1' : 'size-2.5 ring-2',
            color,
        )} />
    );
}
