export type AppearanceMode = 'light' | 'dark' | 'system';
export type AppearanceColor =
    | 'neutral'
    | 'amber'
    | 'blue'
    | 'cyan'
    | 'emerald'
    | 'fuchsia'
    | 'green'
    | 'indigo'
    | 'lime'
    | 'orange'
    | 'pink'
    | 'purple'
    | 'red'
    | 'rose'
    | 'sky'
    | 'teal'
    | 'violet'
    | 'yellow';
export type AppearanceFont = 'inter' | 'figtree' | 'manrope' | 'system';
export type AppearanceRadius = 'sm' | 'md' | 'lg' | 'xl';
export type AppearanceContrast = 'soft' | 'balanced' | 'strong';

export interface AppearanceSettings {
    mode: AppearanceMode;
    color: AppearanceColor;
    font: AppearanceFont;
    radius: AppearanceRadius;
    contrast: AppearanceContrast;
}

const STORAGE_KEYS = {
    mode: 'fusterai-theme-mode',
    color: 'fusterai-theme-color',
    font: 'fusterai-theme-font',
    radius: 'fusterai-theme-radius',
    contrast: 'fusterai-theme-contrast',
} as const;

export function withAppearanceDefaults(input?: Partial<AppearanceSettings> | null): AppearanceSettings {
    return {
        mode: input?.mode ?? 'system',
        color: input?.color ?? 'violet',
        font: input?.font ?? 'figtree',
        radius: input?.radius ?? 'sm',
        contrast: input?.contrast ?? 'balanced',
    };
}

export function readStoredAppearance(fallback?: Partial<AppearanceSettings> | null): AppearanceSettings {
    const defaults = withAppearanceDefaults(fallback);

    const mode = window.localStorage.getItem(STORAGE_KEYS.mode) as AppearanceMode | null;
    const color = window.localStorage.getItem(STORAGE_KEYS.color) as AppearanceColor | null;
    const font = window.localStorage.getItem(STORAGE_KEYS.font) as AppearanceFont | null;
    const radius = window.localStorage.getItem(STORAGE_KEYS.radius) as AppearanceRadius | null;
    const contrast = window.localStorage.getItem(STORAGE_KEYS.contrast) as AppearanceContrast | null;

    return {
        mode: mode === 'light' || mode === 'dark' || mode === 'system' ? mode : defaults.mode,
        color: color ?? defaults.color,
        font: font ?? defaults.font,
        radius: radius === 'sm' || radius === 'md' || radius === 'lg' || radius === 'xl' ? radius : defaults.radius,
        contrast: contrast === 'soft' || contrast === 'balanced' || contrast === 'strong' ? contrast : defaults.contrast,
    };
}

export function persistAppearance(settings: AppearanceSettings) {
    window.localStorage.setItem(STORAGE_KEYS.mode, settings.mode);
    window.localStorage.setItem(STORAGE_KEYS.color, settings.color);
    window.localStorage.setItem(STORAGE_KEYS.font, settings.font);
    window.localStorage.setItem(STORAGE_KEYS.radius, settings.radius);
    window.localStorage.setItem(STORAGE_KEYS.contrast, settings.contrast);
}

export function applyAppearance(settings: AppearanceSettings) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = settings.mode === 'dark' || (settings.mode === 'system' && prefersDark);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = settings.mode;
    document.documentElement.dataset.themeColor = settings.color;
    document.documentElement.dataset.themeFont = settings.font;
    document.documentElement.dataset.themeRadius = settings.radius;
    document.documentElement.dataset.themeContrast = settings.contrast;
}
