<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    @php
        $appearanceDefaults = ['mode' => 'system', 'color' => 'neutral', 'font' => 'inter', 'radius' => 'lg', 'contrast' => 'balanced'];
        if (auth()->check()) {
            $workspace = \App\Models\Workspace::find(auth()->user()->workspace_id);
            $appearance = $workspace?->settings['appearance'] ?? [];
            $appearanceDefaults['mode'] = $appearance['mode'] ?? 'system';
            $appearanceDefaults['color'] = $appearance['color'] ?? 'neutral';
            $appearanceDefaults['font'] = $appearance['font'] ?? 'inter';
            $appearanceDefaults['radius'] = $appearance['radius'] ?? 'lg';
            $appearanceDefaults['contrast'] = $appearance['contrast'] ?? 'balanced';
        }
    @endphp
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <script>
            (function () {
                const defaults = {
                    mode: @json($appearanceDefaults['mode']),
                    color: @json($appearanceDefaults['color']),
                    font: @json($appearanceDefaults['font']),
                    radius: @json($appearanceDefaults['radius'] ?? 'lg'),
                    contrast: @json($appearanceDefaults['contrast'] ?? 'balanced'),
                };

                const storedMode = localStorage.getItem('fusterai-theme-mode');
                const storedColor = localStorage.getItem('fusterai-theme-color');
                const storedFont = localStorage.getItem('fusterai-theme-font');
                const storedRadius = localStorage.getItem('fusterai-theme-radius');
                const storedContrast = localStorage.getItem('fusterai-theme-contrast');

                const theme = storedMode === 'light' || storedMode === 'dark' || storedMode === 'system'
                    ? storedMode
                    : defaults.mode;
                const color = storedColor || defaults.color;
                const font = storedFont || defaults.font;
                const radius = storedRadius || defaults.radius;
                const contrast = storedContrast || defaults.contrast;
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = theme === 'dark' || (theme === 'system' && prefersDark);

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.dataset.theme = theme;
                document.documentElement.dataset.themeColor = color;
                document.documentElement.dataset.themeFont = font;
                document.documentElement.dataset.themeRadius = radius;
                document.documentElement.dataset.themeContrast = contrast;
            })();
        </script>

        <title inertia>{{ config('app.name', 'FusterAI') }}</title>

        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased bg-background text-foreground">
        @inertia
        <style>#app { height: 100%; }</style>
    </body>
</html>
