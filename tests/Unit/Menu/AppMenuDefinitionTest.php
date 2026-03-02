<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps only one canonical settings menu item per settings module', function (): void {
    $path = resource_path('js/menu/appMenuDefinition.js');

    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);

    preg_match_all('/route:\s*"([^"]+)"/', $content, $matches);

    $routes = $matches[1] ?? [];

    expect(array_count_values($routes)['admin.app_settings.index'] ?? 0)->toBe(1);
    expect(array_count_values($routes)['admin.company_settings.index'] ?? 0)->toBe(1);
    expect(array_count_values($routes)['admin.user_settings.index'] ?? 0)->toBe(1);

    expect($routes)->not->toContain('settings.app');
    expect($routes)->not->toContain('settings.company');
    expect($routes)->not->toContain('settings.user');
});
