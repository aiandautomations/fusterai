<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class WorkspaceSettingsService
{
    /**
     * Read a settings section from a workspace's JSONB settings column.
     */
    public function get(Workspace $workspace, string $key, mixed $default = []): mixed
    {
        return ($workspace->settings ?? [])[$key] ?? $default;
    }

    /**
     * Persist a settings section to the workspace's JSONB settings column.
     */
    public function update(Workspace $workspace, string $key, mixed $value): void
    {
        $settings = $workspace->settings ?? [];
        $settings[$key] = $value;
        $workspace->settings = $settings;
        $workspace->save();
    }

    /**
     * Store a branding logo, removing the previous file if one exists.
     * Returns the public URL of the stored file.
     */
    public function storeLogo(Workspace $workspace, UploadedFile $file): string
    {
        $branding = $this->get($workspace, 'branding', []);

        if (! empty($branding['logo_path'])) {
            Storage::disk('public')->delete($branding['logo_path']);
        }

        $path = $file->store('workspace/logos', 'public');

        $branding['logo_path'] = $path;
        $branding['logo_url'] = Storage::disk('public')->url($path);

        $this->update($workspace, 'branding', $branding);

        return $branding['logo_url'];
    }
}
