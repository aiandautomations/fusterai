<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'fusterai:install
                            {--demo : Seed demo data and skip interactive prompts}
                            {--force : Re-run even if a workspace already exists}';

    protected $description = 'Run the FusterAI installation wizard';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=blue;options=bold> FusterAI </>  AI-First Customer Support Platform');
        $this->newLine();

        if (Workspace::exists() && ! $this->option('force') && ! $this->option('demo')) {
            $this->warn('  FusterAI is already installed.');
            if (! $this->confirm('  Run setup again?', false)) {
                return self::SUCCESS;
            }
        }

        // ── Ensure foundations are in place ──────────────────────────────────────

        $this->line('  <fg=yellow>→</> Running migrations...');
        $this->callSilently('migrate', ['--force' => true]);

        $this->line('  <fg=yellow>→</> Generating OAuth keys...');
        $this->callSilently('passport:keys', ['--force' => true]);

        $this->line('  <fg=yellow>→</> Linking storage...');
        $this->callSilently('storage:link', ['--force' => true]);

        $this->newLine();

        // ── Demo mode ─────────────────────────────────────────────────────────────

        if ($this->option('demo')) {
            $this->line('  <fg=yellow>→</> Seeding demo data...');
            $this->callSilently('db:seed');
            $this->printSuccess('admin@fusterai.com', 'password');

            return self::SUCCESS;
        }

        // ── Interactive wizard ────────────────────────────────────────────────────

        $workspaceName = $this->ask('  Workspace name', 'My Support Team');

        $adminName = $this->ask('  Your name');

        $adminEmail = $this->ask('  Admin email');
        while (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('  Please enter a valid email address.');
            $adminEmail = $this->ask('  Admin email');
        }

        $password = $this->secret('  Password');
        while (strlen((string) $password) < 8) {
            $this->error('  Password must be at least 8 characters.');
            $password = $this->secret('  Password');
        }

        $apiKey = $this->ask('  Anthropic API key <fg=gray>(Enter to skip — enables AI features)</>', null);

        $this->newLine();
        $this->line('  <fg=yellow>→</> Creating workspace and admin account...');

        $workspace = Workspace::create([
            'name' => $workspaceName,
            'slug' => Str::slug($workspaceName),
        ]);

        User::create([
            'workspace_id' => $workspace->id,
            'name' => $adminName,
            'email' => $adminEmail,
            'password' => Hash::make((string) $password),
            'role' => 'admin',
        ]);

        if ($apiKey) {
            $this->writeEnv('ANTHROPIC_API_KEY', (string) $apiKey);
            $this->line('  <fg=yellow>→</> Anthropic API key saved to .env');
        }

        $this->printSuccess($adminEmail, '(your chosen password)');

        return self::SUCCESS;
    }

    private function printSuccess(string $email, string $password): void
    {
        $url = config('app.url');

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ FusterAI is ready!</>');
        $this->newLine();
        $this->line("  <fg=white>URL:</>      <fg=cyan>{$url}</>");
        $this->line("  <fg=white>Email:</>    <fg=cyan>{$email}</>");
        $this->line("  <fg=white>Password:</> <fg=cyan>{$password}</>");
        $this->newLine();

        if (! config('ai.providers.anthropic.key')) {
            $this->line('  <fg=gray>Tip: Add ANTHROPIC_API_KEY to .env to enable AI reply suggestions.</>');
            $this->newLine();
        }
    }

    private function writeEnv(string $key, string $value): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return;
        }

        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents) ?? $contents;
        } else {
            $contents .= "\n{$key}={$value}\n";
        }

        file_put_contents($path, $contents);
    }
}
