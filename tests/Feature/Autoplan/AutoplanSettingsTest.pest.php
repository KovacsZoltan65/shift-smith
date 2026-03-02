<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Services\Autoplan\AutoplanSettings;
use Illuminate\Support\Facades\Log;

it('uses min_rest_minutes when present', function (): void {
    AppSetting::query()->updateOrCreate(
        ['key' => 'autoplan.min_rest_minutes'],
        [
            'value' => 660,
            'type' => 'int',
            'group' => 'autoplan',
            'label' => 'Minimum pihenőidő',
            'description' => 'Percekben.',
        ]
    );

    expect(app(AutoplanSettings::class)->minRestMinutes())->toBe(660);
});

it('falls back to legacy min_rest_hours and resolves minutes', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->with(
            'autoplan.settings.legacy_min_rest_hours_used',
            \Mockery::on(static function (array $context): bool {
                return ($context['legacy_hours'] ?? null) === 11
                    && ($context['resolved_minutes'] ?? null) === 660;
            })
        );

    AppSetting::query()->where('key', 'autoplan.min_rest_minutes')->delete();

    AppSetting::query()->updateOrCreate(
        ['key' => 'autoplan.min_rest_hours'],
        [
            'value' => 11,
            'type' => 'int',
            'group' => 'autoplan',
            'label' => 'Legacy minimum pihenőidő',
            'description' => 'Órában.',
        ]
    );

    expect(app(AutoplanSettings::class)->minRestMinutes())->toBe(660);
});
