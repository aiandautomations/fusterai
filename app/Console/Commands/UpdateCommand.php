<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateCommand extends Command
{
    protected $signature = 'fusterai:update
                            {--check : Only check for updates, do not apply}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Update FusterAI to the latest release';

    private const GITHUB_REPO = 'aiandautomations/fusterai';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold> FusterAI </>  Updater');
        $this->newLine();

        $current = $this->currentVersion();
        $latest = $this->fetchLatestVersion();

        if ($latest === null) {
            $this->error('  Could not reach GitHub to check for updates. Check your internet connection.');

            return self::FAILURE;
        }

        $this->line("  Current version: <fg=cyan>{$current}</>");
        $this->line("  Latest version:  <fg=cyan>{$latest}</>");
        $this->newLine();

        if ($current === $latest) {
            $this->line('  <fg=green>✓ You are already on the latest version.</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->line("  <fg=yellow>A new version is available: {$latest}</>");
        $this->newLine();

        if ($this->option('check')) {
            $this->line('  Run <fg=cyan>php artisan fusterai:update</> to apply the update.');
            $this->newLine();

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('  Apply this update now?', true)) {
            $this->line('  Update cancelled.');
            $this->newLine();

            return self::SUCCESS;
        }

        return $this->applyUpdate($latest);
    }

    private function applyUpdate(string $latest): int
    {
        // ── Pre-flight checks ─────────────────────────────────────────────────────

        if (! $this->isGitRepo()) {
            $this->error('  This installation is not a git repository. Cannot auto-update.');
            $this->line('  Please update manually by downloading the release from GitHub.');
            $this->newLine();

            return self::FAILURE;
        }

        if ($this->hasUncommittedChanges()) {
            $this->warn('  You have local uncommitted changes.');
            $this->line('  These will be stashed before pulling and restored after.');
            $this->newLine();

            if (! $this->option('force') && ! $this->confirm('  Continue?', true)) {
                return self::SUCCESS;
            }
        }

        $stashed = false;

        // ── Stash local changes ───────────────────────────────────────────────────

        if ($this->hasUncommittedChanges()) {
            $this->line('  <fg=yellow>→</> Stashing local changes...');
            exec('git stash push -m "fusterai:update auto-stash" 2>&1', $out, $code);
            if ($code !== 0) {
                $this->error('  Failed to stash changes: '.implode("\n", $out));

                return self::FAILURE;
            }
            $stashed = true;
        }

        // ── Pull latest code ──────────────────────────────────────────────────────

        $this->line("  <fg=yellow>→</> Pulling {$latest} from GitHub...");
        exec('git pull origin main 2>&1', $out, $code);
        if ($code !== 0) {
            $this->error('  git pull failed:');
            $this->line('  '.implode("\n  ", $out));

            if ($stashed) {
                $this->line('  Restoring your stashed changes...');
                exec('git stash pop 2>&1');
            }

            return self::FAILURE;
        }

        // ── Restore stash ─────────────────────────────────────────────────────────

        if ($stashed) {
            $this->line('  <fg=yellow>→</> Restoring local changes...');
            exec('git stash pop 2>&1', $popOut, $popCode);
            if ($popCode !== 0) {
                $this->warn('  Could not automatically restore stash — resolve manually with: git stash pop');
            }
        }

        // ── PHP dependencies ──────────────────────────────────────────────────────

        $this->line('  <fg=yellow>→</> Installing PHP dependencies...');
        exec('composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev 2>&1', $out, $code);
        if ($code !== 0) {
            $this->warn('  composer install reported issues — check output above.');
        }

        // ── Database migrations (safe — never fresh) ──────────────────────────────

        $this->line('  <fg=yellow>→</> Running database migrations...');
        $this->callSilently('migrate', ['--force' => true]);

        // ── Clear & warm caches ───────────────────────────────────────────────────

        $this->line('  <fg=yellow>→</> Clearing caches...');
        $this->callSilently('optimize:clear');
        $this->callSilently('optimize');

        // ── Restart queue workers (Horizon or plain queue) ────────────────────────

        $this->line('  <fg=yellow>→</> Restarting queue workers...');
        if ($this->isHorizonInstalled()) {
            $this->callSilently('horizon:terminate');
        } else {
            $this->callSilently('queue:restart');
        }

        // ── Done ──────────────────────────────────────────────────────────────────

        $this->newLine();
        $this->line("  <fg=green;options=bold>✓ FusterAI updated to {$latest} successfully!</>");
        $this->newLine();
        $horizon = $this->isHorizonInstalled() ? 'Horizon' : 'queue workers';
        $this->line("  <fg=gray>Tip: If you use Supervisor, restart it to reload {$horizon}.</>");
        $this->newLine();

        return self::SUCCESS;
    }

    private function currentVersion(): string
    {
        $composer = base_path('composer.json');

        if (file_exists($composer)) {
            $data = json_decode((string) file_get_contents($composer), true);
            $version = $data['version'] ?? null;
            if ($version) {
                return 'v'.ltrim($version, 'v');
            }
        }

        $tag = trim((string) shell_exec('git describe --tags --abbrev=0 2>/dev/null'));

        return $tag ?: 'unknown';
    }

    private function fetchLatestVersion(): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases/latest');

            if ($response->successful()) {
                return $response->json('tag_name');
            }
        } catch (\Throwable) {
            // fall through to null
        }

        return null;
    }

    private function isGitRepo(): bool
    {
        exec('git rev-parse --is-inside-work-tree 2>&1', $out, $code);

        return $code === 0;
    }

    private function hasUncommittedChanges(): bool
    {
        exec('git status --porcelain 2>&1', $out, $code);

        return $code === 0 && ! empty($out);
    }

    private function isHorizonInstalled(): bool
    {
        return class_exists(\Laravel\Horizon\Horizon::class);
    }
}
