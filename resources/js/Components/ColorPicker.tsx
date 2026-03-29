import { cn } from '@/lib/utils';

const SWATCHES = [
    '#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981',
    '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef',
    '#ec4899', '#64748b',
];

export default function ColorPicker({ value, onChange }: { value: string; onChange: (c: string) => void }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {SWATCHES.map(c => (
                <button
                    key={c}
                    type="button"
                    onClick={() => onChange(c)}
                    className={cn(
                        'h-6 w-6 rounded-full border-2 transition-all',
                        value === c ? 'border-foreground scale-110' : 'border-transparent hover:scale-105',
                    )}
                    style={{ backgroundColor: c }}
                    title={c}
                />
            ))}
            <label
                className="relative h-6 w-6 rounded-full border-2 border-dashed border-border cursor-pointer flex items-center justify-center hover:border-muted-foreground transition-colors overflow-hidden"
                title="Custom color"
            >
                <span className="text-[10px] text-muted-foreground font-bold">+</span>
                <input
                    type="color"
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                />
            </label>
        </div>
    );
}
