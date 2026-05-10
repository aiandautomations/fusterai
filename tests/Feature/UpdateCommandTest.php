<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

test('check flag reports already up to date', function () {
    $version = currentComposerVersion();

    Http::fake([
        'api.github.com/repos/*/releases/latest' => Http::response(['tag_name' => $version], 200),
    ]);

    $this->artisan('fusterai:update --check')
        ->expectsOutputToContain('already on the latest version')
        ->assertExitCode(0);
});

test('check flag reports new version available without applying', function () {
    Http::fake([
        'api.github.com/repos/*/releases/latest' => Http::response(['tag_name' => 'v9.9.9'], 200),
    ]);

    $this->artisan('fusterai:update --check')
        ->expectsOutputToContain('v9.9.9')
        ->expectsOutputToContain('php artisan fusterai:update')
        ->assertExitCode(0);
});

test('exits with failure when github is unreachable', function () {
    Http::fake([
        'api.github.com/*' => Http::response(null, 500),
    ]);

    $this->artisan('fusterai:update --check')
        ->expectsOutputToContain('Could not reach GitHub')
        ->assertExitCode(1);
});

function currentComposerVersion(): string
{
    $data = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $version = $data['version'] ?? '1.0.0';

    return 'v'.ltrim($version, 'v');
}
