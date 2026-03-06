<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('contains hierarchy menu item in HR group with expected route and permission', function (): void {
    $path = resource_path('js/menu/appMenuDefinition.js');
    expect(File::exists($path))->toBeTrue();

    $content = File::get($path);

    expect($content)->toContain('title: "HR"');
    expect($content)->toContain('title: "Hierarchia"');
    expect($content)->toContain('route: "org.hierarchy.index"');
    expect($content)->toContain('can: "org_hierarchy.viewAny"');

    $hrPos = strpos($content, 'title: "HR"');
    $hierarchyPos = strpos($content, 'title: "Hierarchia"');
    expect($hrPos)->not->toBeFalse();
    expect($hierarchyPos)->not->toBeFalse();
    expect($hierarchyPos)->toBeGreaterThan($hrPos);
});
